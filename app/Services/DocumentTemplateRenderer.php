<?php

namespace App\Services;

use App\Models\GeneratedDocument;

/**
 * Renders a generated document as ATS-friendly HTML.
 *
 * Single column, standard headings, no tables/icons/graphics. The same markup
 * is reused for the in-app preview and (Phase 7) for PDF/DOCX export, so the
 * user never sees a layout that differs from the exported file.
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
        $content = $document->content_json ?? [];

        return $document->type === GeneratedDocument::TYPE_COVER_LETTER
            ? $this->renderCoverLetter($content)
            : $this->renderResume($document, $content);
    }

    /**
     * Standalone printable page, used by the preview endpoint.
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
     * @param  array<string, mixed>  $content
     */
    private function renderResume(GeneratedDocument $document, array $content): string
    {
        $headings = self::HEADINGS[$document->language] ?? self::HEADINGS['id'];
        $contact = $document->jobApplication?->careerProfile?->contact;
        $name = $contact?->full_name ?: ($document->jobApplication?->user?->name ?? '');

        $html = '';

        if ($name !== '') {
            $html .= '<h1>'.e($name).'</h1>';
        }

        $contactBits = array_filter([
            $contact?->email,
            $contact?->phone,
            $contact?->location,
            $contact?->linkedin_url,
            $contact?->github_url,
            $contact?->portfolio_url,
        ]);

        if ($contactBits !== []) {
            $html .= '<p class="contact">'.e(implode(' · ', $contactBits)).'</p>';
        }

        foreach ($document->resolvedSectionOrder() as $section) {
            $html .= $this->renderSection($section, $content, $headings);
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, string>  $headings
     */
    private function renderSection(string $section, array $content, array $headings): string
    {
        return match ($section) {
            'summary' => $this->block($headings['summary'], $this->paragraph($content['summary'] ?? null)),
            'skills' => $this->block($headings['skills'], $this->skills($content['skills'] ?? [])),
            'experiences' => $this->block($headings['experiences'], $this->experiences($content['experiences'] ?? [])),
            'projects' => $this->block($headings['projects'], $this->projects($content['projects'] ?? [])),
            'education' => $this->block($headings['education'], $this->education($content['education'] ?? [])),
            'certifications' => $this->block($headings['certifications'], $this->certifications($content['certifications'] ?? [])),
            'languages' => $this->block($headings['languages'], $this->paragraph(
                is_array($content['languages'] ?? null) ? implode(', ', $content['languages']) : ($content['languages'] ?? null),
            )),
            default => '',
        };
    }

    private function block(string $heading, string $inner): string
    {
        if (trim($inner) === '') {
            return '';
        }

        return '<h2>'.e($heading).'</h2>'.$inner;
    }

    private function paragraph(mixed $value): string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? '' : '<p>'.nl2br(e($text)).'</p>';
    }

    /**
     * @param  array<int, mixed>  $skills
     */
    private function skills(array $skills): string
    {
        $names = [];

        foreach ($skills as $skill) {
            $name = is_array($skill) ? ($skill['name'] ?? null) : $skill;

            if (is_string($name) && trim($name) !== '') {
                $names[] = trim($name);
            }
        }

        return $names === [] ? '' : '<p class="skills">'.e(implode(' · ', $names)).'</p>';
    }

    /**
     * @param  array<int, mixed>  $experiences
     */
    private function experiences(array $experiences): string
    {
        $html = '';

        foreach ($experiences as $experience) {
            if (! is_array($experience)) {
                continue;
            }

            $role = trim((string) ($experience['role'] ?? ''));
            $organization = trim((string) ($experience['organization'] ?? ''));

            if ($role === '' && $organization === '') {
                continue;
            }

            $html .= '<h3>'.e($role !== '' ? $role : $organization).'</h3>';
            $html .= '<p class="meta">'.e($organization).$this->dates($experience).'</p>';
            $html .= $this->bullets($experience['bullets'] ?? []);
        }

        return $html;
    }

    /**
     * @param  array<int, mixed>  $projects
     */
    private function projects(array $projects): string
    {
        $html = '';

        foreach ($projects as $project) {
            if (! is_array($project)) {
                continue;
            }

            $name = trim((string) ($project['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $role = trim((string) ($project['role'] ?? ''));
            $html .= '<h3>'.e($name).($role !== '' ? ' — '.e($role) : '').'</h3>';
            $html .= $this->bullets($project['bullets'] ?? []);

            $technologies = array_filter(array_map('strval', (array) ($project['technologies'] ?? [])));

            if ($technologies !== []) {
                $html .= '<p class="meta">'.e(implode(', ', $technologies)).'</p>';
            }
        }

        return $html;
    }

    /**
     * @param  array<int, mixed>  $educations
     */
    private function education(array $educations): string
    {
        $html = '';

        foreach ($educations as $education) {
            if (! is_array($education)) {
                continue;
            }

            $institution = trim((string) ($education['institution'] ?? ''));

            if ($institution === '') {
                continue;
            }

            $degree = trim((string) ($education['degree'] ?? ''));
            $field = trim((string) ($education['field_of_study'] ?? ''));
            $line = $degree !== '' ? $degree : '';
            $line .= $field !== '' ? ($line !== '' ? ', ' : '').$field : '';

            $html .= '<h3>'.e($institution).'</h3>';
            $html .= '<p class="meta">'.e($line).$this->dates($education).'</p>';
        }

        return $html;
    }

    /**
     * @param  array<int, mixed>  $certifications
     */
    private function certifications(array $certifications): string
    {
        $items = '';

        foreach ($certifications as $certification) {
            if (! is_array($certification)) {
                continue;
            }

            $name = trim((string) ($certification['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $issuer = trim((string) ($certification['issuer'] ?? ''));
            $date = trim((string) ($certification['issue_date'] ?? ''));
            $suffix = implode(' · ', array_filter([$issuer, $date]));

            $items .= '<li>'.e($name).($suffix !== '' ? ' — '.e($suffix) : '').'</li>';
        }

        return $items === '' ? '' : '<ul>'.$items.'</ul>';
    }

    /**
     * @param  array<int, mixed>  $bullets
     */
    private function bullets(array $bullets): string
    {
        $items = '';

        foreach ($bullets as $bullet) {
            $text = is_string($bullet) ? trim($bullet) : '';

            if ($text !== '') {
                $items .= '<li>'.e($text).'</li>';
            }
        }

        return $items === '' ? '' : '<ul>'.$items.'</ul>';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function dates(array $row): string
    {
        $start = trim((string) ($row['start_date'] ?? ''));
        $end = ! empty($row['is_current']) ? 'sekarang' : trim((string) ($row['end_date'] ?? ''));

        if ($start === '' && $end === '') {
            return '';
        }

        return ' | '.trim($start.' – '.$end, ' –');
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function renderCoverLetter(array $content): string
    {
        $body = '';

        foreach ($content['body_paragraphs'] ?? [] as $paragraph) {
            $text = is_array($paragraph) ? ($paragraph['text'] ?? null) : $paragraph;
            $body .= $this->paragraph($text);
        }

        return $this->paragraph($content['recipient_line'] ?? null)
            .$this->paragraph($content['opening'] ?? null)
            .$body
            .$this->paragraph($content['closing'] ?? null)
            .$this->paragraph($content['signature'] ?? null);
    }
}
