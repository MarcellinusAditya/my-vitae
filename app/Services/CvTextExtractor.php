<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory as PhpWordIOFactory;
use Smalot\PdfParser\Parser;

/**
 * Reads the plain text out of an uploaded CV file.
 *
 * Supports PDF (text layer only) and DOCX. A scanned PDF has no text layer;
 * we return an empty string and the caller reports "no_text" instead of failing.
 */
class CvTextExtractor
{
    public const SUPPORTED_EXTENSIONS = ['pdf', 'docx'];

    /**
     * Extract text from an absolute file path.
     *
     * @throws \RuntimeException when the file is unsupported, corrupt, or unreadable
     */
    public function extract(string $absolutePath): string
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => $this->clean($this->fromPdf($absolutePath)),
            'docx' => $this->clean($this->fromDocx($absolutePath)),
            default => throw new \RuntimeException('unsupported_type'),
        };
    }

    private function fromPdf(string $absolutePath): string
    {
        try {
            $parser = new Parser;
            $document = $parser->parseFile($absolutePath);

            return $document->getText();
        } catch (\Throwable $e) {
            throw new \RuntimeException('extraction_failed', 0, $e);
        }
    }

    private function fromDocx(string $absolutePath): string
    {
        try {
            $reader = PhpWordIOFactory::load($absolutePath, 'Word2007');
        } catch (\Throwable $e) {
            throw new \RuntimeException('extraction_failed', 0, $e);
        }

        $lines = [];

        foreach ($reader->getSections() as $section) {
            foreach ($this->elementTexts($section) as $text) {
                $lines[] = $text;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Walk a container (section, table, cell, textrun container) collecting text.
     *
     * @return list<string>
     */
    private function elementTexts(object $container): array
    {
        $texts = [];

        foreach ($container->getElements() as $element) {
            if (method_exists($element, 'getRows')) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        array_push($texts, ...$this->elementTexts($cell));
                    }
                }

                continue;
            }

            if (method_exists($element, 'getElements') && ! method_exists($element, 'getText')) {
                array_push($texts, ...$this->elementTexts($element));

                continue;
            }

            if (method_exists($element, 'getText')) {
                $text = trim((string) $element->getText());

                if ($text !== '') {
                    $texts[] = $text;
                }
            }
        }

        return $texts;
    }

    private function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text ?? '');
    }
}
