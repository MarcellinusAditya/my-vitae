<?php

namespace Tests\Feature;

use App\Models\CareerProfile;
use App\Models\JobApplication;
use App\Models\Languages;
use App\Models\User;
use App\Services\Ai\OpenAiCompatibleDocumentGeneration;
use App\Services\DocumentGenerationService;
use App\Services\ProfileSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferencesAndLanguagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_registry_is_the_single_source_of_truth(): void
    {
        $this->assertSame(['id', 'en', 'ms'], Languages::codes());
        $this->assertTrue(Languages::isSupported('ms'));
        $this->assertFalse(Languages::isSupported('fr'));

        // Models must derive their allowed lists from the registry, not hardcode.
        $this->assertSame(Languages::codes(), CareerProfile::LANGUAGES);
        $this->assertSame(Languages::codes(), JobApplication::LANGUAGES);
    }

    public function test_prompt_names_match_the_documented_labels(): void
    {
        $this->assertSame('Bahasa Indonesia', Languages::promptName('id'));
        $this->assertSame('English', Languages::promptName('en'));
        $this->assertSame('Bahasa Melayu', Languages::promptName('ms'));
    }

    public function test_target_role_is_validated_and_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('career-profile.update'), [
            'title' => 'Engineer',
            'target_role' => 'Backend Engineer',
            'target_industry' => 'Fintech',
            'default_language' => 'ms',
            'writing_style' => 'professional',
            'resume_length' => 'concise',
            'date_format' => 'month_year',
        ])->assertRedirect();

        $profile = $user->careerProfile()->first();
        $this->assertSame('Backend Engineer', $profile->target_role);
        $this->assertSame('Fintech', $profile->target_industry);
        $this->assertSame('ms', $profile->default_language);
    }

    public function test_unsupported_language_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('career-profile.update'), [
            'default_language' => 'fr',
            'writing_style' => 'professional',
            'resume_length' => 'concise',
            'date_format' => 'month_year',
        ])->assertSessionHasErrors('default_language');
    }

    public function test_generation_context_carries_target_role(): void
    {
        $user = User::factory()->create();
        $profile = $user->careerProfile()->create([
            'summary' => 'Engineer.',
            'target_role' => 'Platform Engineer',
            'target_industry' => 'Cloud',
        ]);

        $application = $profile->jobApplications()->create([
            'user_id' => $user->id,
            'company_name' => 'Acme',
            'job_title' => 'Backend',
            'job_description' => str_repeat('text ', 30),
            'language' => 'id',
        ]);

        $document = $application->generatedDocuments()->create([
            'type' => 'resume',
            'title' => 'CV',
            'version' => 1,
            'language' => 'id',
            'generation_status' => 'completed',
            'content_json' => ['summary' => 'S'],
        ]);

        // Analysis must be completed for a context to exist.
        $application->analyses()->create([
            'status' => 'completed',
            'analysis_json' => ['requirements' => [], 'summary' => 'ok'],
            'profile_snapshot' => [],
        ]);

        $context = app(DocumentGenerationService::class)->generationContext($document->fresh());
        $this->assertNotNull($context);
        $this->assertSame('Platform Engineer', $context[2]['target_role']);
        $this->assertSame('Cloud', $context[2]['target_industry']);
    }

    public function test_target_role_appears_in_the_resume_prompt(): void
    {
        $method = new \ReflectionMethod(OpenAiCompatibleDocumentGeneration::class, 'resumePrompt');
        $method->setAccessible(true);

        $generator = app(OpenAiCompatibleDocumentGeneration::class);

        $prompt = $method->invoke(
            $generator,
            app(ProfileSnapshotService::class)->build(
                User::factory()->create()->careerProfile()->create(['summary' => 'S']),
            ),
            ['title' => 'Backend', 'description' => 'Job text', 'language' => 'id'],
            ['language' => 'id', 'target_role' => 'Platform Engineer', 'target_industry' => 'Cloud'],
        );

        $this->assertStringContainsString('CANDIDATE TARGET', $prompt);
        $this->assertStringContainsString('Platform Engineer', $prompt);
        $this->assertStringContainsString('never invent experience', $prompt);
    }
}
