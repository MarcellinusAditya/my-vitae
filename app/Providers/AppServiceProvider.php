<?php

namespace App\Providers;

use App\Services\Ai\CvExtractionProvider;
use App\Services\Ai\DocumentGenerationProvider;
use App\Services\Ai\JobFitAnalysisProvider;
use App\Services\Ai\OpenAiCompatibleCvExtraction;
use App\Services\Ai\OpenAiCompatibleDocumentGeneration;
use App\Services\Ai\OpenAiCompatibleJobFitAnalysis;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Provider-agnostic AI layer: swap this binding to change LLM vendors
        // without touching domain logic (PROJECT_PLAN.md §6).
        $this->app->bind(CvExtractionProvider::class, OpenAiCompatibleCvExtraction::class);
        $this->app->bind(JobFitAnalysisProvider::class, OpenAiCompatibleJobFitAnalysis::class);
        $this->app->bind(DocumentGenerationProvider::class, OpenAiCompatibleDocumentGeneration::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
