<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JobPostingFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobPostingImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Example hosts must not need real DNS in tests.
        $this->app->bind(JobPostingFetcher::class, fn () => new class extends JobPostingFetcher
        {
            protected function resolve(string $host): array
            {
                // Literal IPs keep their own value so the SSRF checks stay real.
                if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                    return [$host];
                }

                return ['93.184.216.34'];
            }
        });
    }

    private function postingHtml(): string
    {
        return <<<'HTML'
        <!DOCTYPE html>
        <html><head>
            <title>Backend Engineer - Acme</title>
            <meta property="og:site_name" content="Acme Careers">
            <meta property="og:title" content="Backend Engineer">
            <meta property="og:locality" content="Jakarta">
            <meta name="description" content="Join our platform team.">
        </head>
        <body>
            <nav>Home About Contact</nav>
            <header>Acme Careers</header>
            <main>
                <h1>Backend Engineer</h1>
                <p>We are looking for a backend engineer to build reliable APIs for our
                payment platform. You will own services end to end and work closely with
                product and data teams on a daily basis.</p>
                <h2>Kualifikasi</h2>
                <ul>
                    <li>3+ years with PHP and Laravel</li>
                    <li>Strong SQL and Redis experience</li>
                </ul>
            </main>
            <footer>Copyright Acme</footer>
        </body></html>
        HTML;
    }

    public function test_posting_is_imported_and_split_into_description_and_qualifications(): void
    {
        Http::fake([
            'https://acme.example/jobs/1' => Http::response($this->postingHtml(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        $posting = app(JobPostingFetcher::class)->fetch('https://acme.example/jobs/1');

        $this->assertSame('Backend Engineer', $posting['title']);
        $this->assertSame('Acme Careers', $posting['company']);
        $this->assertSame('Jakarta', $posting['location']);
        $this->assertStringContainsString('reliable APIs', $posting['description']);
        $this->assertStringContainsString('3+ years with PHP', $posting['qualifications']);
        // Page chrome is never part of the posting.
        $this->assertStringNotContainsString('Copyright Acme', $posting['description']);
    }

    public function test_import_endpoint_returns_the_posting_as_json(): void
    {
        Http::fake([
            'https://acme.example/jobs/1' => Http::response($this->postingHtml(), 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('applications.import-url'), ['url' => 'https://acme.example/jobs/1'])
            ->assertOk()
            ->assertJsonPath('posting.title', 'Backend Engineer')
            ->assertJsonPath('posting.company', 'Acme Careers');
    }

    public function test_private_and_loopback_hosts_are_refused(): void
    {
        // Real resolver here: the stub above deliberately treats names as public.
        $fetcher = new JobPostingFetcher;

        foreach (['http://127.0.0.1/jobs', 'http://localhost/jobs', 'http://169.254.169.254/latest/meta-data'] as $url) {
            $this->assertFalse($fetcher->hostIsPublic((string) parse_url($url, PHP_URL_HOST)), $url);
        }
    }

    public function test_non_http_schemes_are_rejected(): void
    {
        $fetcher = app(JobPostingFetcher::class);

        $this->assertFalse($fetcher->isAllowedUrl('file:///etc/passwd'));
        $this->assertFalse($fetcher->isAllowedUrl('ftp://example.com/cv'));
        $this->assertFalse($fetcher->isAllowedUrl('not a url'));
        $this->assertTrue($fetcher->isAllowedUrl('https://example.com/jobs/1'));
    }

    public function test_blocked_host_returns_a_clear_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('applications.import-url'), ['url' => 'http://127.0.0.1/secret'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Alamat tersebut tidak dapat diakses dari server kami.');
    }

    public function test_page_without_text_reports_no_text(): void
    {
        Http::fake([
            'https://empty.example/job' => Http::response(
                '<html><body><nav>Menu</nav></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('applications.import-url'), ['url' => 'https://empty.example/job'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Halaman berhasil dibuka, tetapi teks lowongan tidak ditemukan. Tempel isinya secara manual.');
    }

    public function test_failed_fetch_is_reported_as_such(): void
    {
        Http::fake([
            'https://acme.example/jobs/1' => Http::response('upstream error', 500),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('applications.import-url'), ['url' => 'https://acme.example/jobs/1'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Gagal membuka halaman lowongan. Coba lagi atau tempel isinya secara manual.');
    }

    public function test_import_requires_authentication(): void
    {
        $this->postJson(route('applications.import-url'), ['url' => 'https://acme.example/jobs/1'])
            ->assertUnauthorized();
    }
}
