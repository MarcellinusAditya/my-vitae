<?php

namespace App\Services;

use App\Models\DocumentRevision;
use App\Models\GeneratedDocument;
use Illuminate\Support\Facades\DB;

/**
 * Editing, autosave, revisions and restore for generated documents.
 *
 * Every write goes through here so word counts, timestamps and revision rows
 * stay consistent no matter which endpoint triggered the change.
 */
class DocumentEditorService
{
    /** How many autosave revisions to keep per document before pruning. */
    private const KEEP_AUTO_REVISIONS = 20;

    /**
     * Persist content and/or section order.
     *
     * @param  array<string, mixed>  $content
     * @param  list<string>|null  $sectionOrder
     * @param  string  $source  one of DocumentRevision::SOURCE_*
     */
    public function save(
        GeneratedDocument $document,
        array $content,
        ?array $sectionOrder = null,
        string $source = DocumentRevision::SOURCE_AUTO,
    ): GeneratedDocument {
        return DB::transaction(function () use ($document, $content, $sectionOrder, $source) {
            $wordCount = $this->wordCount($content, $document->type);

            $document->update([
                'content_json' => $content,
                'section_order' => $sectionOrder ?? $document->section_order,
                'word_count' => $wordCount,
                'last_saved_at' => now(),
                'generation_status' => GeneratedDocument::STATUS_COMPLETED,
                'error_code' => null,
            ]);

            $this->recordRevision($document->refresh(), $content, $source);

            return $document->refresh();
        });
    }

    /**
     * Restore a past revision by writing it as a new revision (append-only).
     */
    public function restore(GeneratedDocument $document, DocumentRevision $revision): GeneratedDocument
    {
        return DB::transaction(function () use ($document, $revision) {
            $wordCount = $this->wordCount($revision->content_json, $document->type);

            $document->update([
                'content_json' => $revision->content_json,
                'section_order' => $revision->section_order ?? $document->section_order,
                'word_count' => $wordCount,
                'last_saved_at' => now(),
                'generation_status' => GeneratedDocument::STATUS_COMPLETED,
                'error_code' => null,
            ]);

            $this->recordRevision($document->refresh(), $revision->content_json, DocumentRevision::SOURCE_RESTORE);

            return $document->refresh();
        });
    }

    /**
     * Count words across the user-visible text of a document.
     *
     * @param  array<string, mixed>|null  $content
     */
    public function wordCount(?array $content, string $type): int
    {
        if ($content === null) {
            return 0;
        }

        $text = $type === GeneratedDocument::TYPE_COVER_LETTER
            ? $this->coverLetterText($content)
            : $this->resumeText($content);

        $words = preg_split('/\s+/u', trim($text)) ?: [];

        return count(array_filter($words, fn ($word) => $word !== ''));
    }

    /**
     * Soft overflow warning for the editor banner. Null when within limits.
     */
    public function overflowWarning(GeneratedDocument $document): ?string
    {
        $limit = $this->wordLimit($document);
        $count = $document->word_count;

        if ($count <= $limit) {
            return null;
        }

        return "Dokumen ini {$count} kata, melewati batas nyaman {$limit} kata. "
            .'Saat diekspor nanti, isinya berisiko terpotong atau menjadi lebih dari satu halaman.';
    }

    public function wordLimit(GeneratedDocument $document): int
    {
        if ($document->type === GeneratedDocument::TYPE_COVER_LETTER) {
            return GeneratedDocument::WORD_LIMITS['cover_letter']['default'];
        }

        $length = $document->jobApplication?->careerProfile?->resume_length ?? 'concise';

        return GeneratedDocument::WORD_LIMITS['resume'][$length]
            ?? GeneratedDocument::WORD_LIMITS['resume']['concise'];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function resumeText(array $content): string
    {
        $parts = [(string) ($content['summary'] ?? '')];

        foreach (['skills', 'experiences', 'projects', 'education', 'certifications', 'languages'] as $key) {
            foreach ($content[$key] ?? [] as $row) {
                if (! is_array($row)) {
                    $parts[] = (string) $row;

                    continue;
                }

                foreach (['name', 'organization', 'role', 'institution', 'degree', 'field_of_study', 'issuer', 'category'] as $field) {
                    if (! empty($row[$field])) {
                        $parts[] = (string) $row[$field];
                    }
                }

                foreach (['bullets', 'technologies'] as $listField) {
                    foreach ($row[$listField] ?? [] as $item) {
                        $parts[] = (string) $item;
                    }
                }
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function coverLetterText(array $content): string
    {
        $parts = [
            (string) ($content['recipient_line'] ?? ''),
            (string) ($content['opening'] ?? ''),
            (string) ($content['closing'] ?? ''),
            (string) ($content['signature'] ?? ''),
        ];

        foreach ($content['body_paragraphs'] ?? [] as $paragraph) {
            $parts[] = is_array($paragraph) ? (string) ($paragraph['text'] ?? '') : (string) $paragraph;
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function recordRevision(GeneratedDocument $document, array $content, string $source): DocumentRevision
    {
        $latest = $document->revisions()->first();

        // Skip identical autosaves so the history stays readable.
        if ($source === DocumentRevision::SOURCE_AUTO
            && $latest !== null
            && $latest->content_json === $content
            && $latest->section_order === $document->section_order) {
            return $latest;
        }

        $revision = $document->revisions()->create([
            'content_json' => $content,
            'section_order' => $document->section_order,
            'word_count' => $document->word_count,
            'source' => $source,
        ]);

        $this->pruneAutoRevisions($document);

        return $revision;
    }

    /**
     * Keep autosave noise bounded while never deleting manual/restore rows.
     */
    private function pruneAutoRevisions(GeneratedDocument $document): void
    {
        $autoIds = $document->revisions()
            ->where('source', DocumentRevision::SOURCE_AUTO)
            ->orderByDesc('id')
            ->pluck('id');

        $excess = $autoIds->slice(self::KEEP_AUTO_REVISIONS);

        if ($excess->isNotEmpty()) {
            DocumentRevision::whereIn('id', $excess->all())->delete();
        }
    }
}
