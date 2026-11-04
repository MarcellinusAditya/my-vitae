<?php

namespace Tests\Feature;

use App\Services\CvOcrExtractor;
use App\Services\CvTextExtractor;
use Tests\TestCase;

/**
 * Exercises the real OCR pipeline against a genuinely image-only PDF.
 *
 * The fixture is drawn with GD and wrapped in a minimal PDF containing a single
 * JPEG stream (DCTDecode), so there is no text layer to cheat with: passing
 * means Tesseract actually read pixels. Skips when the host lacks the binaries.
 */
class CvOcrTest extends TestCase
{
    private CvOcrExtractor $ocr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ocr = app(CvOcrExtractor::class);

        if (! $this->ocr->isAvailable()) {
            $this->markTestSkipped('tesseract/pdftoppm not installed on this host.');
        }
    }

    public function test_scanned_pdf_without_text_layer_is_read_by_ocr(): void
    {
        $path = $this->scannedPdf();

        try {
            // Proves the fixture is a real scan: no text layer at all.
            $layer = (new CvTextExtractor)->extract($path);
            $this->assertLessThan(100, mb_strlen($layer), 'Fixture unexpectedly has a text layer.');

            $text = $this->ocr->extract($path, 'id');

            $this->assertGreaterThan(100, mb_strlen($text));
            $this->assertStringContainsString('BUDI', mb_strtoupper($text));
            $this->assertStringContainsString('LARAVEL', mb_strtoupper($text));
        } finally {
            @unlink($path);
        }
    }

    public function test_missing_binaries_raise_a_typed_error(): void
    {
        $stub = new class extends CvOcrExtractor
        {
            public function isAvailable(): bool
            {
                return false;
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ocr_unavailable');

        $stub->extract('/nonexistent.pdf');
    }

    /**
     * Build an image-only PDF: GD draws the text, the JPEG bytes are embedded
     * directly as an /XObject with DCTDecode.
     */
    private function scannedPdf(): string
    {
        $lines = [
            'BUDI SANTOSO',
            'Backend Engineer',
            'budi.santoso@example.com',
            'PENGALAMAN KERJA',
            'PT Maju Jaya - Backend Engineer 2021 sekarang',
            'Membangun API Laravel untuk 12000 pengguna aktif',
            'PENDIDIKAN',
            'Universitas Indonesia - Ilmu Komputer',
            'KEAHLIAN',
            'PHP Laravel MySQL Redis Docker',
        ];

        $width = 1240;
        $height = 1754;
        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $width, $height, $white);

        $y = 120;
        foreach ($lines as $line) {
            imagestring($img, 5, 120, $y, $line, $black);
            $y += 60;
        }

        ob_start();
        imagejpeg($img, null, 92);
        $jpeg = (string) ob_get_clean();
        imagedestroy($img);

        $pdf = $this->wrapJpegInPdf($jpeg, $width, $height);
        $path = sys_get_temp_dir().'/myvitae-ocr-fixture-'.bin2hex(random_bytes(6)).'.pdf';
        file_put_contents($path, $pdf);

        return $path;
    }

    private function wrapJpegInPdf(string $jpeg, int $width, int $height): string
    {
        $content = "q\n{$width} 0 0 {$height} 0 0 cm\n/Im0 Do\nQ\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>',
                $width,
                $height,
            ),
            sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $width,
                $height,
                strlen($jpeg),
                $jpeg,
            ),
            sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($content), $content),
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $object) {
            $offsets[$i] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
