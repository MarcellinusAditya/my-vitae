<?php

namespace App\Services\Exports;

use App\Models\GeneratedDocument;
use App\Services\DocumentTemplateRenderer;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders a document to PDF from the exact same HTML the preview shows.
 *
 * Remote resources are disabled (no SSRF, no external fonts), the chroot is
 * limited to storage, and the resulting text stays selectable because dompdf
 * lays out real text with embedded fonts.
 */
class PdfExporter
{
    public function __construct(private readonly DocumentTemplateRenderer $renderer) {}

    /**
     * @return string raw PDF bytes
     *
     * @throws \RuntimeException
     */
    public function render(GeneratedDocument $document): string
    {
        try {
            $options = new Options;
            $options->setIsRemoteEnabled(false);
            $options->setIsJavascriptEnabled(false);
            $options->setChroot([storage_path()]);
            $options->setDefaultFont('Helvetica');
            $options->setDpi(110);
            $options->setTempDir(storage_path('app/private/tmp'));

            $dompdf = new Dompdf($options);

            $html = $this->renderer->renderPage($document);

            // dompdf needs a physical paper size; Indonesian documents fit A4.
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $output = $dompdf->output();

            if (! is_string($output) || strlen($output) < 400) {
                throw new \RuntimeException('render_failed');
            }

            return $output;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException('render_failed', 0, $e);
        }
    }
}
