<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Calls any OpenAI-compatible chat completions endpoint (OpenAI, 9Router, ...)
 * configured through config/services.php → 'ai'.
 */
class OpenAiCompatibleCvExtraction implements CvExtractionProvider
{
    private const MAX_INPUT_CHARS = 16000;

    public function name(): string
    {
        return 'openai-compatible';
    }

    public function extractDraft(string $cvText): array
    {
        $apiKey = config('services.ai.key');
        $baseUrl = rtrim((string) config('services.ai.base', 'https://api.openai.com/v1'), '/');
        $model = (string) config('services.ai.model', 'gpt-4o-mini');

        if (empty($apiKey)) {
            throw new \RuntimeException('provider_unavailable');
        }

        $chunk = mb_substr($this->normalize($cvText), 0, self::MAX_INPUT_CHARS);

        $response = Http::timeout((int) config('services.ai.timeout', 90))
            ->withToken($apiKey)
            ->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'stream' => false,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => 'You only output one valid JSON object. Never add commentary or markdown.'],
                    ['role' => 'user', 'content' => $this->prompt($chunk)],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('CV extraction provider failed', [
                'status' => $response->status(),
            ]);

            throw new \RuntimeException('provider_unavailable');
        }

        $content = $this->messageContent($response);

        return CvDraftValidator::validate($content);
    }

    /**
     * Read the assistant message from either a regular completion or an SSE
     * stream that some gateways (e.g. 9Router) return regardless of `stream:false`.
     */
    private function messageContent(Response $response): string
    {
        $content = (string) $response->json('choices.0.message.content', '');

        if ($content !== '') {
            return $content;
        }

        $body = (string) $response->body();

        if (! str_contains($body, 'data:')) {
            return '';
        }

        $assembled = '';

        foreach (preg_split('/\R/', $body) as $line) {
            $line = trim($line);

            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $payload = trim(substr($line, 5));

            if ($payload === '' || $payload === '[DONE]') {
                continue;
            }

            $decoded = json_decode($payload, true);

            if (! is_array($decoded)) {
                continue;
            }

            $delta = $decoded['choices'][0]['delta']['content'] ?? null;

            if (is_string($delta)) {
                $assembled .= $delta;
            }
        }

        return $assembled;
    }

    private function prompt(string $cvText): string
    {
        return <<<'PROMPT'
You are a resume parser. Extract the data from the CV below into JSON. Rules:

- Copy facts EXACTLY as written. NEVER invent, guess, or "improve" anything.
- If a value is absent, use null. Do not fill gaps with plausible data.
- Dates: output as "YYYY-MM-DD", "YYYY-MM" or "YYYY" exactly as found; null if unclear.
- Achievements/technologies: arrays of short strings copied from the CV.
- "confidence" is your 0-100 estimate that your extraction for that section is correct.
- Detect the language of the CV; write "summary" text as found (do not translate).

Respond with a single JSON object with exactly these keys:

{
  "contact": {"full_name": string|null, "email": string|null, "phone": string|null, "location": string|null, "linkedin_url": string|null, "portfolio_url": string|null, "github_url": string|null, "confidence": int},
  "summary": {"text": string|null, "confidence": int},
  "experiences": [{"organization": string, "job_title": string, "employment_type": string|null, "location": string|null, "start_date": string|null, "end_date": string|null, "is_current": bool, "description": string|null, "achievements": [string]}],
  "educations": [{"institution": string, "degree": string|null, "field_of_study": string|null, "start_date": string|null, "end_date": string|null, "description": string|null}],
  "skills": [{"name": string, "category": string|null, "proficiency": string|null}],
  "projects": [{"name": string, "role": string|null, "description": string|null, "technologies": [string], "url": string|null, "start_date": string|null, "end_date": string|null}],
  "certifications": [{"name": string, "issuer": string|null, "issue_date": string|null, "expiry_date": string|null, "credential_url": string|null}],
  "confidence": {"contact": int, "summary": int, "experiences": int, "educations": int, "skills": int, "projects": int, "certifications": int}
}

Empty array for sections with no data. Use the confidence map for per-section 0-100 scores.

CV TEXT:
PROMPT
            ."\n".$cvText;
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }
}
