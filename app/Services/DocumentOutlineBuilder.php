<?php

namespace App\Services;

use App\Models\GeneratedDocument;

/**
 * Normalised, format-agnostic outline of a generated document.
 *
 * HTML preview and DOCX export both render from this outline, so the section
 * order and headings the user reviews are exactly what gets exported.
 *
 * Shape:
 * [
 *   'type' => 'resume'|'cover_letter',
 *   'language' => 'id'|'en',
 *   'header' => ['name' => string, 'contact' => list<string>],
 *   'sections' => [
 *     ['key' => 'summary', 'heading' => 'RINGKASAN', 'blocks' => [ ['type' => 'paragraph', 'text' => '...'], ['type' => 'bullets', 'items' => [...]], ... ]],
 *   ],
 * ]
 */
class DocumentOutlineBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(GeneratedDocument $document): array
    {
        $content = $document->content_json ?? [];

        return $document->type === GeneratedDocument::TYPE_COVER_LETTER
            ? $this->coverLetter($document, $content)
            : $this->resume($document, $content);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function resume(GeneratedDocument $document, array $content): array
    {
        $headings = DocumentTemplateRenderer::HEADINGS[$document->language]
            ?? DocumentTemplateRenderer::HEADINGS['id'];

        $contact = $document->jobApplication?->careerProfile?->contact;
        $name = $contact?->full_name ?: ($document->jobApplication?->user?->name ?? '');

        $contactBits = array_values(array_filter([
            $contact?->email,
            $contact?->phone,
            $contact?->location,
            $contact?->linkedin_url,
            $contact?->github_url,
            $contact?->portfolio_url,
        ]));

        $sections = [];

        foreach ($document->resolvedSectionOrder() as $key) {
            $blocks = $this->sectionBlocks($key, $content);

            if ($blocks === []) {
                continue;
            }

            $sections[] = [
                'key' => $key,
                'heading' => $headings[$key] ?? strtoupper($key),
                'blocks' => $blocks,
            ];
        }

        return [
            'type' => GeneratedDocument::TYPE_RESUME,
            'language' => $document->language,
            'header' => ['name' => $name, 'contact' => $contactBits],
            'sections' => $sections,
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function coverLetter(GeneratedDocument $document, array $content): array
    {
        $blocks = [];

        foreach (['recipient_line', 'opening'] as $field) {
            $text = $this->text($content[$field] ?? null);

            if ($text !== null) {
                $blocks[] = ['type' => 'paragraph', 'text' => $text];
            }
        }

        foreach ((array) ($content['body_paragraphs'] ?? []) as $paragraph) {
            $text = $this->text(is_array($paragraph) ? ($paragraph['text'] ?? null) : $paragraph);

            if ($text !== null) {
                $blocks[] = ['type' => 'paragraph', 'text' => $text];
            }
        }

        foreach (['closing', 'signature'] as $field) {
            $text = $this->text($content[$field] ?? null);

            if ($text !== null) {
                $blocks[] = ['type' => 'paragraph', 'text' => $text];
            }
        }

        return [
            'type' => GeneratedDocument::TYPE_COVER_LETTER,
            'language' => $document->language,
            'header' => ['name' => '', 'contact' => []],
            'sections' => $blocks === [] ? [] : [[
                'key' => 'letter',
                'heading' => '',
                'blocks' => $blocks,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<array<string, mixed>>
     */
    private function sectionBlocks(string $key, array $content): array
    {
        return match ($key) {
            'summary' => $this->paragraphBlock($content['summary'] ?? null),
            'skills' => $this->skillBlock($content['skills'] ?? []),
            'experiences' => $this->experienceBlocks($content['experiences'] ?? []),
            'projects' => $this->projectBlocks($content['projects'] ?? []),
            'education' => $this->educationBlocks($content['education'] ?? []),
            'certifications' => $this->certificationBlocks($content['certifications'] ?? []),
            'languages' => is_array($content['languages'] ?? null)
                ? $this->paragraphBlock(implode(', ', array_filter(array_map('strval', $content['languages']))))
                : $this->paragraphBlock($content['languages'] ?? null),
            default => [],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paragraphBlock(mixed $value): array
    {
        $text = $this->text($value);

        return $text === null ? [] : [['type' => 'paragraph', 'text' => $text]];
    }

    /**
     * @param  array<int, mixed>  $skills
     * @return list<array<string, mixed>>
     */
    private function skillBlock(array $skills): array
    {
        $names = [];

        foreach ($skills as $skill) {
            $name = is_array($skill) ? ($skill['name'] ?? null) : $skill;
            $text = $this->text($name);

            if ($text !== null) {
                $names[] = $text;
            }
        }

        return $names === [] ? [] : [['type' => 'inline_list', 'items' => $names]];
    }

    /**
     * @param  array<int, mixed>  $experiences
     * @return list<array<string, mixed>>
     */
    private function experienceBlocks(array $experiences): array
    {
        $blocks = [];

        foreach ($experiences as $experience) {
            if (! is_array($experience)) {
                continue;
            }

            $role = $this->text($experience['role'] ?? null);
            $organization = $this->text($experience['organization'] ?? null);

            if ($role === null && $organization === null) {
                continue;
            }

            $blocks[] = [
                'type' => 'entry',
                'title' => $role ?? $organization,
                'subtitle' => $organization,
                'dates' => $this->dates($experience),
                'items' => $this->textList($experience['bullets'] ?? []),
            ];
        }

        return $blocks;
    }

    /**
     * @param  array<int, mixed>  $projects
     * @return list<array<string, mixed>>
     */
    private function projectBlocks(array $projects): array
    {
        $blocks = [];

        foreach ($projects as $project) {
            if (! is_array($project)) {
                continue;
            }

            $name = $this->text($project['name'] ?? null);

            if ($name === null) {
                continue;
            }

            $blocks[] = [
                'type' => 'entry',
                'title' => $name,
                'title_suffix' => $this->text($project['role'] ?? null),
                'subtitle' => '',
                'dates' => '',
                'items' => $this->textList($project['bullets'] ?? []),
                'items_after' => $this->textList($project['technologies'] ?? []),
            ];
        }

        return $blocks;
    }

    /**
     * @param  array<int, mixed>  $educations
     * @return list<array<string, mixed>>
     */
    private function educationBlocks(array $educations): array
    {
        $blocks = [];

        foreach ($educations as $education) {
            if (! is_array($education)) {
                continue;
            }

            $institution = $this->text($education['institution'] ?? null);

            if ($institution === null) {
                continue;
            }

            $degree = $this->text($education['degree'] ?? null);
            $field = $this->text($education['field_of_study'] ?? null);
            $subtitle = implode(', ', array_filter([$degree, $field]));

            $blocks[] = [
                'type' => 'entry',
                'title' => $institution,
                'subtitle' => $subtitle,
                'dates' => $this->dates($education),
                'items' => [],
            ];
        }

        return $blocks;
    }

    /**
     * @param  array<int, mixed>  $certifications
     * @return list<array<string, mixed>>
     */
    private function certificationBlocks(array $certifications): array
    {
        $items = [];

        foreach ($certifications as $certification) {
            if (! is_array($certification)) {
                continue;
            }

            $name = $this->text($certification['name'] ?? null);

            if ($name === null) {
                continue;
            }

            $suffix = implode(' · ', array_filter([
                $this->text($certification['issuer'] ?? null),
                $this->text($certification['issue_date'] ?? null),
            ]));

            $items[] = $suffix === '' ? $name : $name.' — '.$suffix;
        }

        return $items === [] ? [] : [['type' => 'bullets', 'items' => $items]];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function dates(array $row): string
    {
        $start = $this->text($row['start_date'] ?? null) ?? '';
        $end = ! empty($row['is_current']) ? 'sekarang' : ($this->text($row['end_date'] ?? null) ?? '');

        if ($start === '' && $end === '') {
            return '';
        }

        return trim($start.' – '.$end, ' –');
    }

    /**
     * @return list<string>
     */
    private function textList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $item) {
            $text = $this->text($item);

            if ($text !== null) {
                $out[] = $text;
            }
        }

        return $out;
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
