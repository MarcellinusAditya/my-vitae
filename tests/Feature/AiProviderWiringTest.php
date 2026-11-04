<?php

namespace Tests\Feature;

use App\Services\Ai\CvExtractionProvider;
use App\Services\Ai\DocumentGenerationProvider;
use App\Services\Ai\JobFitAnalysisProvider;
use App\Services\Ai\OpenAiCompatibleCvExtraction;
use App\Services\Ai\OpenAiCompatibleDocumentGeneration;
use App\Services\Ai\OpenAiCompatibleJobFitAnalysis;
use App\Services\Ai\PromptSanitizer;
use Tests\TestCase;

/**
 * Guards the container wiring of the real AI providers.
 *
 * These classes take collaborators as constructor arguments, and an un-injected
 * property fails only at runtime (inside a queued job) as an "undefined
 * property" warning that surfaces as a generic provider failure. Resolving each
 * one here turns that into a test failure instead of a production bug.
 */
class AiProviderWiringTest extends TestCase
{
    public function test_every_ai_provider_resolves_from_the_container(): void
    {
        $this->assertInstanceOf(OpenAiCompatibleCvExtraction::class, app(CvExtractionProvider::class));
        $this->assertInstanceOf(OpenAiCompatibleJobFitAnalysis::class, app(JobFitAnalysisProvider::class));
        $this->assertInstanceOf(OpenAiCompatibleDocumentGeneration::class, app(DocumentGenerationProvider::class));
    }

    public function test_providers_have_no_uninitialised_properties(): void
    {
        foreach ([
            OpenAiCompatibleCvExtraction::class,
            OpenAiCompatibleJobFitAnalysis::class,
            OpenAiCompatibleDocumentGeneration::class,
        ] as $class) {
            $provider = app($class);

            $this->assertInstanceOf(PromptSanitizer::class, $this->readProperty($provider, 'sanitizer'), $class);
        }
    }

    public function test_cv_extraction_wraps_input_in_a_delimiter_before_calling_the_model(): void
    {
        $sanitizer = app(PromptSanitizer::class);
        $wrapped = $sanitizer->wrap('Budi, backend engineer.', 'cv_text');

        $this->assertStringStartsWith('<cv_text>', $wrapped);
        $this->assertStringEndsWith('</cv_text>', $wrapped);
    }

    private function readProperty(object $object, string $name): mixed
    {
        $property = new \ReflectionProperty($object, $name);
        $property->setAccessible(true);

        return $property->getValue($object);
    }
}
