<?php

namespace App\Services;

use App\Models\GeneratedDocument;
use App\Models\JobApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates document versions and resolves the context a generation job needs.
 */
class DocumentGenerationService
{
    public function __construct(private readonly ProfileSnapshotService $snapshots) {}

    public function snapshots(): ProfileSnapshotService
    {
        return $this->snapshots;
    }

    /**
     * Queue a new version of a document for the application.
     *
     * @param  array{tone?: string|null, language?: string|null, recipient_name?: string|null}  $options
     */
    public function queue(JobApplication $application, string $type, array $options = []): GeneratedDocument
    {
        return DB::transaction(function () use ($application, $type, $options) {
            $version = (int) $application->generatedDocuments()
                ->where('type', $type)
                ->max('version') + 1;

            $analysis = $application->analyses()->first();

            $language = $options['language']
                ?? $application->language
                ?? $application->careerProfile?->default_language
                ?? 'id';

            return $application->generatedDocuments()->create([
                'job_requirement_analysis_id' => $analysis?->id,
                'type' => $type,
                'title' => $this->title($application, $type, $language, $version),
                'template_key' => 'ats_plain',
                'language' => $language,
                'tone' => $type === GeneratedDocument::TYPE_COVER_LETTER
                    ? ($options['tone'] ?? $application->careerProfile?->writing_style ?? 'professional')
                    : null,
                'version' => $version,
                'generation_status' => GeneratedDocument::STATUS_PENDING,
            ]);
        });
    }

    /**
     * Snapshot + preferences for a generation run.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}|null
     */
    public function generationContext(GeneratedDocument $document): ?array
    {
        $application = $document->jobApplication;
        $profile = $application?->careerProfile;

        if ($application === null || $profile === null) {
            return null;
        }

        $analysis = $document->analysis ?? $application->analyses()->first();

        // Generation targets the job, so a fit analysis is required context.
        if ($analysis === null || ! $analysis->isCompleted()) {
            return null;
        }

        $profileSnapshot = $this->snapshots->build($profile);

        return [
            $profileSnapshot,
            $this->jobSnapshot($application),
            [
                'language' => $document->language,
                'tone' => $document->tone ?? $profile->writing_style,
                'resume_length' => $profile->resume_length,
                'writing_style' => $profile->writing_style,
                'analysis_requirements' => $analysis->analysis_json['requirements'] ?? [],
                'analysis_summary' => $analysis->analysis_json['summary'] ?? '',
            ],
        ];
    }

    /**
     * Persist user edits on the current version.
     *
     * @param  array<string, mixed>  $content
     */
    public function updateContent(GeneratedDocument $document, array $content): GeneratedDocument
    {
        $document->update([
            'content_json' => $content,
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
            'error_code' => null,
        ]);

        return $document->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function jobSnapshot(JobApplication $application): array
    {
        return [
            'company_name' => $application->company_name,
            'job_title' => $application->job_title,
            'job_location' => $application->job_location,
            'job_description' => $application->job_description,
            'job_qualifications' => $application->job_qualifications,
            'language' => $application->language,
            'notes' => $application->notes,
        ];
    }

    private function title(JobApplication $application, string $type, string $language, int $version): string
    {
        $label = GeneratedDocument::TYPES[$type] ?? $type;

        return Str::limit(
            "{$label} — {$application->job_title} @ {$application->company_name} (v{$version})",
            255,
            '',
        );
    }
}
