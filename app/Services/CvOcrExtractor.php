<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * OCR for scanned PDFs, using the local Tesseract + Poppler binaries.
 *
 * Everything runs on this server: CV content never leaves the host. Requires
 * `tesseract-ocr` (plus `tesseract-ocr-ind` for Indonesian) and
 * `poppler-utils` (`pdftoppm`) to be installed; when they are missing the
 * caller gets `ocr_unavailable` and reports a clear message instead of failing
 * silently.
 *
 * Images are rasterised at 300 DPI because Tesseract is materially more accurate
 * there than at the 72 DPI a PDF's native resolution implies.
 */
class CvOcrExtractor
{
    public const MAX_PAGES = 10;

    public const DPI = 300;

    public const TIMEOUT_SECONDS = 300;

    /** Below this, OCR output is treated as noise. */
    public const MIN_OUTPUT_CHARS = 40;

    /**
     * OCR a PDF file. Returns the recognised text, or an empty string when
     * nothing legible was found.
     *
     * @throws \RuntimeException with code ocr_unavailable when the binaries are missing
     */
    public function extract(string $absolutePdfPath, string $language = 'id'): string
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('ocr_unavailable');
        }

        $workDir = $this->makeWorkDir();

        try {
            $this->rasterise($absolutePdfPath, $workDir);
            $pages = glob($workDir.'/page-*.png') ?: [];
            sort($pages);

            if ($pages === []) {
                return '';
            }

            $chunks = [];

            foreach (array_slice($pages, 0, self::MAX_PAGES) as $page) {
                $text = $this->recognise($page, $language);

                if ($text !== '') {
                    $chunks[] = $text;
                }
            }

            $text = $this->clean(implode("\n\n", $chunks));

            return mb_strlen($text) >= self::MIN_OUTPUT_CHARS ? $text : '';
        } finally {
            $this->cleanUp($workDir);
        }
    }

    public function isAvailable(): bool
    {
        return $this->binaryExists('tesseract') && $this->binaryExists('pdftoppm');
    }

    private function binaryExists(string $binary): bool
    {
        $result = Process::timeout(10)->run(['which', $binary]);

        return $result->successful() && trim($result->output()) !== '';
    }

    /**
     * Language pack must match what Tesseract actually has installed; unknown
     * codes fall back to the first available language rather than erroring.
     */
    private function tessLanguage(string $language): string
    {
        $available = $this->availableLanguages();
        $preferred = match ($language) {
            'en' => 'eng',
            'ms' => 'msa',
            default => 'ind',
        };

        if (in_array($preferred, $available, true)) {
            return $preferred;
        }

        // Indonesian CVs with an English-only install still read better than nothing.
        if (in_array('eng', $available, true)) {
            return 'eng';
        }

        return $available[0] ?? 'eng';
    }

    /**
     * @return list<string>
     */
    private function availableLanguages(): array
    {
        $result = Process::timeout(15)->run(['tesseract', '--list-langs']);

        if (! $result->successful()) {
            return [];
        }

        $lines = preg_split('/\R/', trim($result->output())) ?: [];

        // First line is the "List of available languages" header.
        return array_values(array_filter(array_map('trim', array_slice($lines, 1))));
    }

    private function makeWorkDir(): string
    {
        $dir = sys_get_temp_dir().'/myvitae-ocr-'.bin2hex(random_bytes(8));

        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new \RuntimeException('extraction_failed');
        }

        return $dir;
    }

    private function rasterise(string $pdfPath, string $workDir): void
    {
        $result = Process::timeout(self::TIMEOUT_SECONDS)->run([
            'pdftoppm',
            '-r', (string) self::DPI,
            '-png',
            '-l', (string) self::MAX_PAGES,
            $pdfPath,
            $workDir.'/page',
        ]);

        if (! $result->successful()) {
            Log::warning('OCR rasterisation failed', ['exit' => $result->exitCode()]);

            throw new \RuntimeException('extraction_failed');
        }
    }

    private function recognise(string $imagePath, string $language): string
    {
        $result = Process::timeout(self::TIMEOUT_SECONDS)->run([
            'tesseract',
            $imagePath,
            'stdout',
            '-l', $this->tessLanguage($language),
            '--psm', '3',
        ]);

        if (! $result->successful()) {
            // One unreadable page must not sink the whole document.
            Log::info('OCR page failed', ['exit' => $result->exitCode()]);

            return '';
        }

        return trim($result->output());
    }

    private function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Tesseract often returns single-word lines from tables/columns.
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function cleanUp(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }
}
