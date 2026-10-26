<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for [OI]-compatible chat completion calls.
 *
 * Centralised because some gateways (e.g. 9Router) answer with an SSE stream
 * even when the request sets `stream: false`, so the assistant text has to be
 * assembled from `data:` deltas instead of `choices.0.message.content`.
 */
class ChatCompletionClient
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $baseUrl = null,
        private readonly ?string $model = null,
        private readonly ?int $timeout = null,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->key());
    }

    public function modelName(): string
    {
        return (string) ($this->model ?? config('services.ai.model', 'gpt-4o-mini'));
    }

    /**
     * Send a prompt and return the assistant message content.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $extra  extra top-level payload keys
     *
     * @throws \RuntimeException with the code `provider_unavailable` on transport/HTTP failure
     */
    public function complete(array $messages, float $temperature = 0.2, array $extra = []): string
    {
        $key = $this->key();

        if (blank($key)) {
            throw new \RuntimeException('provider_unavailable');
        }

        $response = Http::timeout((int) ($this->timeout ?? config('services.ai.timeout', 90)))
            ->withToken($key)
            ->post($this->endpoint(), [
                'model' => $this->modelName(),
                'temperature' => $temperature,
                'stream' => false,
                'response_format' => ['type' => 'json_object'],
                'messages' => $messages,
                ...$extra,
            ]);

        if (! $response->successful()) {
            Log::warning('AI provider request failed', ['status' => $response->status()]);

            throw new \RuntimeException('provider_unavailable');
        }

        return $this->messageContent($response);
    }

    /**
     * Read the assistant message from a regular completion or an SSE stream.
     */
    public function messageContent(Response $response): string
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

    private function key(): ?string
    {
        return $this->apiKey ?? config('services.ai.key');
    }

    private function endpoint(): string
    {
        $base = rtrim((string) ($this->baseUrl ?? config('services.ai.base', 'https://api.openai.com/v1')), '/');

        return $base.'/chat/completions';
    }
}
