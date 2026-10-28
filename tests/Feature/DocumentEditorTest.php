<?php

namespace Tests\Feature;

use App\Jobs\GenerateDocumentJob;
use App\Models\DocumentRevision;
use App\Models\GeneratedDocument;
use App\Models\JobApplication;
use App\Models\JobRequirementAnalysis;
use App\Models\User;
use App\Services\Ai\AiUsageLogger;
use App\Services\Ai\DocumentGenerationProvider;
use App\Services\DocumentEditorService;
use App\Services\DocumentGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DocumentEditorTest extends TestCase
{
    use RefreshDatabase;

    private const JD = 'We are hiring a Senior Backend Engineer to design and scale payment APIs. Requirements: 5+ years PHP, strong Laravel, MySQL performance tuning, and mentoring junior engineers.';

    private function document(User $user, array $overrides = []): GeneratedDocument
    {
        $profile = $user->careerProfile()->create(['summary' => 'Backend engineer.', 'resume_length' => 'concise']);
        $profile->skills()->create(['name' => 'PHP', 'category' => 'Backend', 'sort_order' => 0]);
        $profile->experiences()->create([
            'organization' => 'Acme Corp', 'job_title' => 'Senior Engineer',
            'start_date' => '2022-01-01', 'is_current' => true, 'sort_order' => 0,
        ]);

        $application = JobApplication::create([
            'user_id' => $user->id,
            'career_profile_id' => $profile->id,
            'company_name' => 'Global Fintech',
            'job_title' => 'Senior Backend Engineer',
            'job_description' => self::JD,
            'language' => 'id',
        ]);

        $analysis = $application->analyses()->create([
            'status' => JobRequirementAnalysis::STATUS_COMPLETED,
            'analysis_json' => ['requirements' => [], 'keyword_suggestions' => [], 'summary' => 'ok'],
        ]);

        return $application->generatedDocuments()->create([
            'job_requirement_analysis_id' => $analysis->id,
            'type' => GeneratedDocument::TYPE_RESUME,
            'title' => 'CV v1',
            'version' => 1,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
            'content_json' => [
                'summary' => 'Original summary',
                'skills' => [['name' => 'PHP', 'category' => 'Backend', 'evidence_refs' => ['skill:1']]],
                'experiences' => [[
                    'organization' => 'Acme Corp', 'role' => 'Senior Engineer',
                    'start_date' => '2022-01-01', 'end_date' => null, 'is_current' => true,
                    'bullets' => ['Led a team of 4 engineers.'], 'evidence_refs' => ['experience:1'],
                ]],
                'unverified_claims' => [],
                'prompt_version' => 'v2',
            ],
            ...$overrides,
        ]);
    }

    public function test_autosave_persists_content_and_returns_status(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user);

        $response = $this->actingAs($user)->patchJson(route('documents.autosave', $document), [
            'content' => ['summary' => 'Edited summary'],
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'saved'])
            ->assertJsonStructure(['saved_at', 'saved_at_label', 'word_count', 'word_limit']);

        $fresh = $document->fresh();
        $this->assertSame('Edited summary', $fresh->content_json['summary']);
        $this->assertNotNull($fresh->last_saved_at);
        $this->assertGreaterThan(0, $fresh->word_count);
        // Untouched sections survive a partial autosave payload.
        $this->assertCount(1, $fresh->content_json['experiences']);
    }

    public function test_autosave_creates_revision_and_preserves_guardrail_meta(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user, [
            'content_json' => [
                'summary' => 'Original',
                'unverified_claims' => ['Angka "99" tidak ditemukan di profil'],
            ],
        ]);

        $this->actingAs($user)->patchJson(route('documents.autosave', $document), [
            'content' => ['summary' => 'New'],
        ])->assertOk();

        $fresh = $document->fresh();

        $this->assertSame(['Angka "99" tidak ditemukan di profil'], $fresh->unverifiedClaimList());
        $this->assertSame(1, DocumentRevision::where('generated_document_id', $document->id)->count());

        $revision = DocumentRevision::first();
        $this->assertSame(DocumentRevision::SOURCE_AUTO, $revision->source);
        $this->assertSame('New', $revision->content_json['summary']);
    }

    public function test_section_order_is_saved_and_returned(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user);

        $order = ['experiences', 'skills', 'summary'];

        $this->actingAs($user)->patchJson(route('documents.autosave', $document), [
            'content' => ['summary' => 'x'],
            'section_order' => $order,
        ])->assertOk();

        $fresh = $document->fresh();
        // Missing sections are appended so nothing disappears from the render.
        $this->assertSame(
            ['experiences', 'skills', 'summary', 'projects', 'education', 'certifications', 'languages'],
            $fresh->resolvedSectionOrder(),
        );

        $this->actingAs($user)->get(route('documents.show', $document))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('document.section_order.0', 'experiences'));
    }

    public function test_template_key_can_be_changed_via_autosave(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user);

        $this->actingAs($user)->patchJson(route('documents.autosave', $document), [
            'content' => ['summary' => 'x'],
            'template_key' => 'ats_compact',
        ])->assertOk();

        $this->assertSame('ats_compact', $document->fresh()->template_key);
    }

    public function test_restore_brings_back_old_content_as_new_revision(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user);

        // Create a revision holding the original content.
        $revision = $document->revisions()->create([
            'content_json' => ['summary' => 'Old text', 'unverified_claims' => []],
            'word_count' => 2,
            'source' => DocumentRevision::SOURCE_AUTO,
        ]);

        $document->update(['content_json' => ['summary' => 'Current text', 'unverified_claims' => []]]);

        $this->actingAs($user)
            ->post(route('documents.revisions.restore', [$document, $revision]))
            ->assertRedirect();

        $fresh = $document->fresh();
        $this->assertSame('Old text', $fresh->content_json['summary']);

        // Append-only: a restore revision is recorded, the old one still exists.
        $this->assertSame(2, DocumentRevision::where('generated_document_id', $document->id)->count());
        $this->assertSame(1, DocumentRevision::where('source', DocumentRevision::SOURCE_RESTORE)->count());
        $this->assertNotNull(DocumentRevision::find($revision->id));
    }

    public function test_restore_rejects_a_revision_from_another_document(): void
    {
        $user = User::factory()->create();
        $mine = $this->document($user);

        // A second document on the same application (one profile per user).
        $other = $mine->jobApplication->generatedDocuments()->create([
            'type' => GeneratedDocument::TYPE_COVER_LETTER,
            'title' => 'CL other',
            'version' => 1,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
            'content_json' => ['opening' => 'hi'],
        ]);

        $foreign = $other->revisions()->create([
            'content_json' => ['summary' => 'foreign'],
            'word_count' => 1,
            'source' => DocumentRevision::SOURCE_AUTO,
        ]);

        $this->actingAs($user)
            ->post(route('documents.revisions.restore', [$mine, $foreign]))
            ->assertNotFound();
    }

    public function test_overflow_warning_appears_above_word_limit(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user);

        $longSummary = str_repeat('kata ', 800);

        $this->actingAs($user)->patchJson(route('documents.autosave', $document), [
            'content' => ['summary' => $longSummary],
        ])->assertOk()->assertJsonStructure(['overflow_warning']);

        $response = $this->actingAs($user)->patchJson(route('documents.autosave', $document), [
            'content' => ['summary' => $longSummary],
        ]);

        $this->assertNotNull($response->json('overflow_warning'));
        $this->assertGreaterThan($response->json('word_limit'), $response->json('word_count'));
    }

    public function test_editor_service_counts_words_for_both_types(): void
    {
        $editor = app(DocumentEditorService::class);

        $resume = [
            'summary' => 'one two three',
            'experiences' => [['role' => 'Dev', 'organization' => 'Acme', 'bullets' => ['four five']]],
            'skills' => [['name' => 'PHP']],
        ];

        // 3 (summary) + 2 (role/organization) + 2 (bullet) + 1 (skill)
        $this->assertSame(8, $editor->wordCount($resume, GeneratedDocument::TYPE_RESUME));

        $coverLetter = [
            'recipient_line' => 'Dear Hiring Manager,',
            'opening' => 'Saya melamar posisi ini.',
            'body_paragraphs' => [['text' => 'Satu dua tiga empat.']],
            'closing' => 'Terima kasih.',
            'signature' => 'Jane Doe',
        ];

        // 3 + 4 + 4 + 2 + 2
        $this->assertSame(15, $editor->wordCount($coverLetter, GeneratedDocument::TYPE_COVER_LETTER));
    }

    public function test_section_scoped_regeneration_keeps_other_sections(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $document = $this->document($user);

        $this->actingAs($user)
            ->post(route('documents.regenerate', $document), ['section' => 'summary'])
            ->assertRedirect();

        $new = GeneratedDocument::where('id', '!=', $document->id)->firstOrFail();

        $this->assertSame(2, $new->version);
        $this->assertSame($document->id, $new->parent_version_id);
        $this->assertSame('summary', $new->content_json['_regenerate_section']);
        $this->assertSame('Original summary', $new->content_json['_parent_content']['summary']);
        Queue::assertPushed(GenerateDocumentJob::class, fn ($job) => $job->documentId === $new->id);
    }

    public function test_job_merges_only_the_regenerated_section(): void
    {
        $user = User::factory()->create();
        $parent = $this->document($user);

        $child = app(DocumentGenerationService::class)->queue(
            $parent->jobApplication,
            GeneratedDocument::TYPE_RESUME,
            [],
            parent: $parent,
            section: 'summary',
        );

        $provider = new class implements DocumentGenerationProvider
        {
            public function name(): string
            {
                return 'stub';
            }

            public function generateResume(array $profileSnapshot, array $jobSnapshot, array $preferences): array
            {
                return [
                    'summary' => 'Brand new summary',
                    'skills' => [['name' => 'Invented', 'evidence_refs' => []]],
                    'experiences' => [],
                    'unverified_claims' => ['new flag'],
                    'prompt_version' => 'v2',
                ];
            }

            public function generateCoverLetter(array $profileSnapshot, array $jobSnapshot, array $preferences): array
            {
                return [];
            }
        };

        (new GenerateDocumentJob($child->id))->handle(
            $provider,
            app(DocumentGenerationService::class),
            app(AiUsageLogger::class),
        );

        $result = $child->fresh()->content_json;

        // Requested section replaced...
        $this->assertSame('Brand new summary', $result['summary']);
        // ...everything else preserved from the parent.
        $this->assertSame('Senior Engineer', $result['experiences'][0]['role']);
        $this->assertSame('PHP', $result['skills'][0]['name']);
        // Internal merge keys never leak into stored content.
        $this->assertArrayNotHasKey('_regenerate_section', $result);
        $this->assertArrayNotHasKey('_parent_content', $result);

        // The AI output is snapshotted so the user can restore it later.
        $revision = $child->fresh()->revisions()->first();
        $this->assertNotNull($revision);
        $this->assertSame(DocumentRevision::SOURCE_GENERATED, $revision->source);
        $this->assertSame('Brand new summary', $revision->content_json['summary']);
    }

    public function test_other_user_cannot_autosave_reorder_restore_or_preview(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $other->careerProfile()->create();

        $document = $this->document($owner);
        $revision = $document->revisions()->create([
            'content_json' => ['summary' => 'x'],
            'word_count' => 1,
            'source' => DocumentRevision::SOURCE_AUTO,
        ]);

        $this->actingAs($other)->patchJson(route('documents.autosave', $document), ['content' => ['summary' => 'hack']])->assertForbidden();
        $this->actingAs($other)->patch(route('documents.reorder', $document), ['section_order' => ['summary']])->assertForbidden();
        $this->actingAs($other)->post(route('documents.revisions.restore', [$document, $revision]))->assertForbidden();
        $this->actingAs($other)->get(route('documents.preview', $document))->assertForbidden();
    }
}
