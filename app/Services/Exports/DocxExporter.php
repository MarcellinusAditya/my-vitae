<?php

namespace App\Services\Exports;

use App\Models\GeneratedDocument;
use App\Services\DocumentOutlineBuilder;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\ListItem;

/**
 * Builds a DOCX from the same outline the preview/PDF use.
 *
 * One column, standard headings, real bullet lists and clickable hyperlinks so
 * the file stays editable in Word/LibreOffice without breaking ATS parsing.
 */
class DocxExporter
{
    public function __construct(private readonly DocumentOutlineBuilder $outline) {}

    /**
     * @return string raw DOCX bytes
     *
     * @throws \RuntimeException
     */
    public function render(GeneratedDocument $document): string
    {
        try {
            $outline = $this->outline->build($document);

            $phpWord = new PhpWord;
            $this->defineStyles($phpWord);

            $section = $phpWord->addSection([
                'marginTop' => 900,
                'marginBottom' => 900,
                'marginLeft' => 1000,
                'marginRight' => 1000,
            ]);

            $this->renderHeader($section, $outline);

            foreach ($outline['sections'] ?? [] as $block) {
                $this->renderSection($section, $block);
            }

            $writer = IOFactory::createWriter($phpWord, 'Word2007');

            ob_start();
            $writer->save('php://output');
            $output = (string) ob_get_clean();

            if (strlen($output) < 400) {
                throw new \RuntimeException('render_failed');
            }

            return $output;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException('render_failed', 0, $e);
        }
    }

    private function defineStyles(PhpWord $phpWord): void
    {
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 18, 'name' => 'Arial'], ['spaceAfter' => 60]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => 11, 'name' => 'Arial', 'allCaps' => true], ['spaceBefore' => 180, 'spaceAfter' => 60]);
        $phpWord->addTitleStyle(3, ['bold' => true, 'size' => 11, 'name' => 'Arial'], ['spaceBefore' => 120, 'spaceAfter' => 0]);

        $phpWord->addFontStyle('bodyText', ['size' => 10.5, 'name' => 'Arial'], ['spaceAfter' => 80]);
        $phpWord->addFontStyle('metaText', ['size' => 9.5, 'name' => 'Arial', 'color' => '444444'], ['spaceAfter' => 40]);
        $phpWord->addFontStyle('bulletText', ['size' => 10.5, 'name' => 'Arial'], ['spaceAfter' => 40]);
    }

    /**
     * @param  array<string, mixed>  $outline
     */
    private function renderHeader(Section $section, array $outline): void
    {
        $name = trim((string) ($outline['header']['name'] ?? ''));

        if ($name !== '') {
            $section->addTitle($name, 1);
        }

        $contact = array_values(array_filter(
            (array) ($outline['header']['contact'] ?? []),
            fn ($bit) => trim((string) $bit) !== '',
        ));

        if ($contact !== []) {
            $run = $section->addTextRun(['size' => 9.5, 'name' => 'Arial', 'color' => '333333']);

            foreach (array_values($contact) as $index => $bit) {
                if ($index > 0) {
                    $run->addText(' · ', ['size' => 9.5, 'name' => 'Arial', 'color' => '333333']);
                }

                $this->addContactBit($run, (string) $bit);
            }
        }
    }

    /**
     * Emails and URLs become real hyperlinks in the DOCX.
     */
    private function addContactBit(TextRun $run, string $value): void
    {
        $value = trim($value);
        $style = ['size' => 9.5, 'name' => 'Arial', 'color' => '333333'];

        if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
            $run->addLink('mailto:'.$value, $value, $style);

            return;
        }

        if (preg_match('#^https?://#i', $value) === 1 && filter_var($value, FILTER_VALIDATE_URL) !== false) {
            $run->addLink($value, $value, $style);

            return;
        }

        $run->addText($value, $style);
    }

    /**
     * @param  array<string, mixed>  $sectionData
     */
    private function renderSection(Section $section, array $sectionData): void
    {
        $heading = trim((string) ($sectionData['heading'] ?? ''));
        $blocks = $sectionData['blocks'] ?? [];

        if ($blocks === []) {
            return;
        }

        if ($heading !== '') {
            $section->addTitle($heading, 2);
        }

        foreach ($blocks as $block) {
            match ($block['type'] ?? '') {
                'paragraph' => $section->addText((string) $block['text'], 'bodyText'),
                'inline_list' => $section->addText(implode(' · ', (array) $block['items']), 'bodyText'),
                'bullets' => $this->addBullets($section, (array) $block['items']),
                'entry' => $this->addEntry($section, $block),
                default => null,
            };
        }
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function addEntry(Section $section, array $block): void
    {
        $title = trim((string) ($block['title'] ?? ''));
        $suffix = trim((string) ($block['title_suffix'] ?? ''));

        if ($title !== '' || $suffix !== '') {
            $section->addTitle($title.($suffix !== '' ? ' — '.$suffix : ''), 3);
        }

        $subtitle = trim((string) ($block['subtitle'] ?? ''));
        $dates = trim((string) ($block['dates'] ?? ''));
        $meta = $subtitle.($dates !== '' ? ($subtitle !== '' ? ' | ' : '').$dates : '');

        if ($meta !== '') {
            $section->addText($meta, 'metaText');
        }

        $this->addBullets($section, (array) ($block['items'] ?? []));

        $after = array_values(array_filter((array) ($block['items_after'] ?? []), fn ($v) => trim((string) $v) !== ''));

        if ($after !== []) {
            $section->addText(implode(', ', $after), 'metaText');
        }
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function addBullets(Section $section, array $items): void
    {
        foreach ($items as $item) {
            $text = is_string($item) ? trim($item) : '';

            if ($text === '') {
                continue;
            }

            $section->addListItem($text, 0, 'bulletText', ['listType' => ListItem::TYPE_BULLET_FILLED]);
        }
    }
}
