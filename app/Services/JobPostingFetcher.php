<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches a job posting page and turns it into plain text for the intake form.
 *
 * Security posture: the URL comes from the user, so this is an SSRF surface.
 * We only allow http/https, resolve the host and refuse private, loopback,
 * link-local and reserved addresses, cap the response size, and never follow a
 * redirect to a blocked address.
 */
class JobPostingFetcher
{
    public const MAX_BYTES = 2_000_000;

    public const MAX_TEXT_CHARS = 20_000;

    public const TIMEOUT_SECONDS = 10;

    /**
     * @return array{title: string, company: string, location: string, description: string, qualifications: string}
     *
     * @throws \RuntimeException with a stable code: invalid_url, blocked_host, fetch_failed, no_text
     */
    public function fetch(string $url): array
    {
        $url = trim($url);

        if (! $this->isAllowedUrl($url)) {
            throw new \RuntimeException('invalid_url');
        }

        $host = (string) parse_url($url, PHP_URL_HOST);

        if (! $this->hostIsPublic($host)) {
            throw new \RuntimeException('blocked_host');
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'User-Agent' => 'MyVitae/1.0 (+job posting import)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->withOptions(['allow_redirects' => ['max' => 3, 'strict' => true]])
                ->get($url);
        } catch (\Throwable $e) {
            Log::info('Job posting fetch failed', ['error' => $e::class]);

            throw new \RuntimeException('fetch_failed');
        }

        if (! $response->successful()) {
            // 401/403/406 almost always means bot protection, which is common on
            // job boards; say so instead of a generic failure.
            throw new \RuntimeException(
                in_array($response->status(), [401, 403, 406, 429], true) ? 'blocked_by_site' : 'fetch_failed',
            );
        }

        $body = (string) $response->body();

        if (strlen($body) > self::MAX_BYTES) {
            $body = substr($body, 0, self::MAX_BYTES);
        }

        $html = $this->decode($body, (string) $response->header('Content-Type'));

        if ($html === '') {
            throw new \RuntimeException('no_text');
        }

        return $this->parse($html, $url);
    }

    public function isAllowedUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * Reject anything that resolves to a non-public address, so the server
     * cannot be used to probe its own network.
     */
    public function hostIsPublic(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        $addresses = $this->resolve($host);

        // A host that does not resolve cannot be fetched at all; report it as a
        // blocked host rather than a transport failure so the message is honest.
        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        $addresses = [];

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        foreach (@gethostbynamel($host) ?: [] as $record) {
            $addresses[] = $record;
        }

        if ($addresses === []) {
            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return $addresses;
    }

    private function decode(string $body, string $contentType): string
    {
        if (stripos($contentType, 'text/html') === false && stripos($contentType, 'application/xhtml') === false) {
            return '';
        }

        // Respect the declared charset; fall back to UTF-8.
        if (preg_match('/charset=([\w-]+)/i', $contentType, $m) === 1) {
            $charset = strtoupper($m[1]);

            if ($charset !== 'UTF-8' && in_array($charset, mb_list_encodings(), true)) {
                $body = mb_convert_encoding($body, 'UTF-8', $charset);
            }
        }

        return $body;
    }

    /**
     * @return array{title: string, company: string, location: string, description: string, qualifications: string}
     */
    private function parse(string $html, string $url): array
    {
        $dom = new \DOMDocument;

        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        $title = $this->meta($xpath, 'og:title') ?: $this->firstText($xpath, '//title');
        $company = $this->meta($xpath, 'og:site_name');
        $location = $this->meta($xpath, 'og:locality');

        // Drop chrome that is never part of the posting.
        foreach (['//script', '//style', '//noscript', '//nav', '//header', '//footer', '//aside', '//form', '//iframe'] as $query) {
            foreach (iterator_to_array($xpath->query($query) ?: []) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $text = $this->bestTextBlock($xpath);

        if ($text === '') {
            $text = $this->meta($xpath, 'description') ?: $this->meta($xpath, 'og:description');
        }

        $text = $this->normalise($text);

        if (mb_strlen($text) < 50) {
            throw new \RuntimeException('no_text');
        }

        [$description, $qualifications] = $this->splitQualifications($text);

        return [
            'title' => $this->normalise($title),
            'company' => $this->normalise($company),
            'location' => $this->normalise($location),
            'description' => $description,
            'qualifications' => $qualifications,
        ];
    }

    private function meta(\DOMXPath $xpath, string $property): string
    {
        $queries = [
            sprintf('//meta[@property="%s"]/@content', $property),
            sprintf('//meta[@name="%s"]/@content', $property),
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);

            if ($nodes !== false && $nodes->length > 0) {
                return trim((string) $nodes->item(0)?->nodeValue);
            }
        }

        return '';
    }

    private function firstText(\DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);

        return $nodes !== false && $nodes->length > 0 ? trim((string) $nodes->item(0)?->nodeValue) : '';
    }

    /**
     * Pick the densest text container: job boards wrap the posting in an
     * article/main element, but plenty of sites do not, so fall back to body.
     */
    private function bestTextBlock(\DOMXPath $xpath): string
    {
        $candidates = [];

        foreach (['//article', '//main', '//*[@id="content"]', '//*[contains(@class,"job")]', '//body'] as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $text = $this->normalise($node->textContent);

                if (mb_strlen($text) > 200) {
                    $candidates[] = $text;
                }
            }
        }

        if ($candidates === []) {
            return '';
        }

        // Longest block is usually the posting itself, not a nav fragment.
        usort($candidates, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return $candidates[0];
    }

    private function normalise(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim(mb_substr($text, 0, self::MAX_TEXT_CHARS));
    }

    /**
     * Split a trailing "qualifications/requirements" section out of the body so
     * the form's two fields are both useful. Best effort: when no marker is
     * found, everything stays in the description.
     *
     * @return array{0: string, 1: string}
     */
    private function splitQualifications(string $text): array
    {
        $pattern = '/\n\s*(kualifikasi|persyaratan|requirements?|qualifications?)\s*:?\s*\n/iu';

        if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return [$text, ''];
        }

        $offset = $m[0][1];
        $description = trim(substr($text, 0, $offset));
        $qualifications = trim(substr($text, $offset + strlen($m[0][0])));

        if (mb_strlen($description) < 50) {
            return [$text, ''];
        }

        return [$description, $qualifications];
    }
}
