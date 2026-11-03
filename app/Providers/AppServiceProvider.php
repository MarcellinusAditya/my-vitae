<?php

namespace App\Providers;

use App\Services\Ai\CvExtractionProvider;
use App\Services\Ai\DocumentGenerationProvider;
use App\Services\Ai\JobFitAnalysisProvider;
use App\Services\Ai\OpenAiCompatibleCvExtraction;
use App\Services\Ai\OpenAiCompatibleDocumentGeneration;
use App\Services\Ai\OpenAiCompatibleJobFitAnalysis;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->defineRateLimiters();
    }

    /**
     * Per-user limits. AI operations are the expensive ones, so they get the
     * tightest budget; uploads and exports are cheap but still bounded.
     */
    private function defineRateLimiters(): void
    {
        RateLimiter::for('ai', function (Request $request) {
            return Limit::perHour((int) config('ai.limits.per_hour', 10))
                ->by($this->userKey($request))
                ->response(fn () => $this->tooManyResponse('Batas operasi AI per jam tercapai. Coba lagi nanti.'));
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perHour((int) config('ai.limits.uploads_per_hour', 20))
                ->by($this->userKey($request))
                ->response(fn () => $this->tooManyResponse('Terlalu banyak unggahan. Coba lagi nanti.'));
        });

        RateLimiter::for('exports', function (Request $request) {
            return Limit::perHour((int) config('ai.limits.exports_per_hour', 30))
                ->by($this->userKey($request))
                ->response(fn () => $this->tooManyResponse('Terlalu banyak ekspor. Coba lagi nanti.'));
        });
    }

    private function userKey(Request $request): string
    {
        return 'user:'.($request->user()?->id ?? $request->ip());
    }

    private function tooManyResponse(string $message): Response
    {
        return response($message, 429, ['Retry-After' => '3600']);
    }
}
