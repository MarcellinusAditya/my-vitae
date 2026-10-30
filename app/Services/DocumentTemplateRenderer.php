<?php

namespace App\Services;

use App\Models\GeneratedDocument;

/**
 * Renders a generated document as ATS-friendly HTML.
 *
 * The layout comes from DocumentOutlineBuilder, so the preview, the PDF export
 * and the DOCX export all render the same section order and headings.
 * Single column, standard headings, no tables/icons/graphics.
 */
class DocumentTemplateRenderer
{
    public const HEADINGS = [
        'id' => [
            'summary' => 'RINGKASAN',
            'skills' => 'KEAHLIAN',
            'experiences' => 'PENGALAMAN KERJA',
            'projects' => 'PROYEK',
            'education' => 'PENDIDIKAN',
            'certifications' => 'SERTIFIKASI',
            'languages' => 'BAHASA',
        ],
        'en' => [
            'summary' => 'SUMMARY',
            'skills' => 'SKILLS',
            'experiences' => 'EXPERIENCE',
            'projects' => 'PROJECTS',
            'education' => 'EDUCATION',
            'certifications' => 'CERTIFICATIONS',
            'languages' => 'LANGUAGES',
        ],
    ];

    /**
     * Render the document body (no <html> wrapper) for embedding.
     */
    public function render(GeneratedDocument $document): string
    {
        return $this->renderOutline(app(DocumentOutlineBuilder::class)->build($document));
    }

    /**
     * Render an already-built outline (used by the exporters).
     *
     * @param  array<string, mixed>  $outline
     */
    public function renderOutline(array $outline): string
    {
        $html = '';

        $name = trim((string) ($outline['header']['name'] ?? ''));

        if ($name !== '') {
            $html .= '<h1>'.e($name).'</h1>';
        }

        $contact = array_filter((array) ($outline['header']['contact'] ?? []), fn ($bit) => trim((string) $bit) !== '');

        if ($contact !== []) {
            $bits = array_map(fn ($bit) => $this->contactBit((string) $bit), $contact);
            $html .= '<p class="contact">'.implode(' · ', $bits).'</p>';
        }

        foreach ($outline['sections'] ?? [] as $section) {
            $heading = trim((string) ($section['heading'] ?? ''));
            $blocks = $this->renderBlocks($section['blocks'] ?? []);

            if (trim($blocks) === '') {
                continue;
            }

            if ($heading !== '') {
                $html .= '<h2>'.e($heading).'</h2>';
            }

            $html .= $blocks;
        }

        return $html;
    }

    /**
     * Standalone printable page, used by the preview endpoint and the PDF exporter.
     */
    public function renderPage(GeneratedDocument $document): string
    {
        $compact = $document->template_key === 'ats_compact';
        $body = $this->render($document);
        $title = e($document->title);

        $baseCss = $compact
            ? 'font-size:10.5pt;line-height:1.35'
            : 'font-size:11pt;line-height:1.5';

        return <<<HTML
<!DOCTYPE html>
<html lang="{$document->language}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;padding:32px 28px;font-family:Helvetica,Arial,sans-serif;color:#111;max-width:820px;background:#fff;{$baseCss}}
  h1{font-size:19pt;margin:0 0 2px;letter-spacing:.01em}
  .contact{font-size:10pt;color:#333;margin:0 0 4px}
  h2{font-size:11pt;text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid #ccc;padding-bottom:2px}
  h3{font-size:10.5pt;margin:10px 0 0}
  .meta{font-size:9.5pt;color:#444;margin:0 0 4px}
  ul{margin:4px 0 0 18px;padding:0}
  li{margin:0 0 3px}
  p{margin:0 0 8px}
  .skills{display:block}
  @page{margin:18mm 16mm}
  @media print{body{padding:0}}
</style>
</head>
<body>
{$body}
</body>
</html>
HTML;
    }

    /**
     * Contact values stay clickable: mailto for emails, real anchors for URLs.
     * ATS parsers read the visible text either way.
     */
    private function contactBit(string $value): string
    {
        $value = trim($value);

        if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
            return '<a href="mailto:'.e($value).'">'.e($value).'</a>';
        }

        if (preg_match('#^https?://#i', $value) === 1 && filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return '<a href="'.e($value).'">'.e($value).'</a>';
        }

        return e($value);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function renderBlocks(array $blocks): string
    {
        $html = '';

        foreach ($blocks as $block) {
            $html .= match ($block['type'] ?? '') {
                'paragraph' => $this->paragraph($block['text'] ?? null),
                'inline_list' => $this->inlineList($block['items'] ?? []),
                'bullets' => $this->bullets($block['items'] ?? []),
                'entry' => $this->entry($block),
                default => '',
            };
        }

        return $html;
    }

    private function paragraph(mixed $value): string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? '' : '<p>'.nl2br(e($text)).'</p>';
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function inlineList(array $items): string
    {
        $clean = array_values(array_filter(array_map(
            fn ($item) => is_string($item) ? trim($item) : '',
            $items,
        ), fn ($item) => $item !== ''));

        return $clean === [] ? '' : '<p class="skills">'.e(implode(' · ', $clean)).'</p>';
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function bullets(array $items): string
    {
        $html = '';

        foreach ($items as $item) {
            $text = is_string($item) ? trim($item) : '';

            if ($text !== '') {
                $html .= '<li>'.e($text).'</li>';
            }
        }

        return $html === '' ? '' : '<ul>'.$html.'</ul>';
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function entry(array $block): string
    {
        $title = trim((string) ($block['title'] ?? ''));
        $suffix = trim((string) ($block['title_suffix'] ?? ''));

        if ($title === '' && $suffix === '') {
            return '';
        }

        $subtitle = trim((string) ($block['subtitle'] ?? ''));
        $dates = trim((string) ($block['dates'] ?? ''));

        $html = '<h3>'.e($title.($suffix !== '' ? ' — '.$suffix : '')).'</h3>';

        $meta = $subtitle;

        if ($dates !== '') {
            $meta .= ($meta !== '' ? ' | ' : '').$dates;
        }

        if ($meta !== '') {
            $html .= '<p class="meta">'.e($meta).'</p>';
        }

        $html .= $this->bullets($block['items'] ?? []);

        $after = array_filter((array) ($block['items_after'] ?? []), fn ($item) => trim((string) $item) !== '');

        if ($after !== []) {
            $html .= '<p class="meta">'.e(implode(', ', $after)).'</p>';
        }

        return $html;
    }
}
