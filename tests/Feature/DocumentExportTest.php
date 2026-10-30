<?php

namespace Tests\Feature;

use App\Models\DocumentExport;
use App\Models\GeneratedDocument;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\DocumentExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class DocumentExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function document(User $user, array $overrides = []): GeneratedDocument
    {
        $profile = $user->careerProfile()->create(['summary' => 'Backend engineer with Laravel focus.']);
        $profile->contact()->create([
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+62 812 0000',
            'location' => 'Jakarta',
        ]);

        $application = JobApplication::create([
            'user_id' => $user->id,
            'career_profile_id' => $profile->id,
            'company_name' => 'Global Fintech',
            'job_title' => 'Senior Backend Engineer',
            'job_description' => str_repeat('Job description text. ', 10),
            'language' => 'id',
        ]);

        return $application->generatedDocuments()->create([
            'type' => GeneratedDocument::TYPE_RESUME,
            'title' => 'CV Senior Backend Engineer',
            'version' => 2,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
            'content_json' => [
                'summary' => 'Backend engineer with Laravel focus and API experience.',
                'skills' => [['name' => 'PHP'], ['name' => 'Laravel'], ['name' => 'MySQL']],
                'experiences' => [[
                    'organization' => 'Acme Corp', 'role' => 'Senior Engineer',
                    'start_date' => '2022-01-01', 'end_date' => null, 'is_current' => true,
                    'bullets' => ['Led a team of 4 engineers.', 'Shipped the exam platform.'],
                ]],
                'education' => [[
                    'institution' => 'ITB', 'degree' => 'S1', 'field_of_study' => 'Informatika',
                    'start_date' => '2015-01-01', 'end_date' => '2019-01-01',
                ]],
                'certifications' => [['name' => 'AWS SAA', 'issuer' => 'Amazon', 'issue_date' => '2023-01-01']],
                'languages' => ['Indonesia', 'English'],
            ],
            ...$overrides,
        ]);
    }

    public function test_pdf_export_creates_file_with_selectable_text(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user);

        $this->actingAs($user)
            ->post(route('documents.exports.store', $document), ['format' => 'pdf'])
            ->assertRedirect();

        $export = DocumentExport::firstOrFail();

        $this->assertSame('pdf', $export->format);
        $this->assertSame(DocumentExport::STATUS_COMPLETED, $export->status);
        $this->assertGreaterThan(1000, $export->file_size);
        $this->assertStringEndsWith('.pdf', $export->filename);
        Storage::disk('local')->assertExists($export->storage_path);

        // The PDF must contain an extractable text layer with the real content.
        $bytes = Storage::disk('local')->get($export->storage_path);
        $this->assertStringStartsWith('%PDF', $bytes);

        $tmp = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
        file_put_contents($tmp, $bytes);

        $parser = new Parser;
        $text = $parser->parseFile($tmp)->getText();
        @unlink($tmp);

        $this->assertStringContainsString('Jane Doe', $text);
        $this->assertStringContainsString('Acme Corp', $text);
        $this->assertStringContainsString('Led a team of 4 engineers', $text);

        // Reading order: summary before experience section.
        $this->assertLessThan(
            mb_strpos($text, 'PENGALAMAN'),
            mb_strpos($text, 'RINGKASAN'),
            'PDF text order must follow the document section order.',
        );
    }

    public function test_docx_export_is_readable_and_keeps_order(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user);

        $this->actingAs($user)
            ->post(route('documents.exports.store', $document), ['format' => 'docx'])
            ->assertRedirect();

        $export = DocumentExport::firstOrFail();
        $this->assertSame('docx', $export->format);
        $this->assertStringEndsWith('.docx', $export->filename);

        $bytes = Storage::disk('local')->get($export->storage_path);
        $this->assertStringStartsWith('PK', $bytes, 'DOCX is a ZIP container.');

        $tmp = tempnam(sys_get_temp_dir(), 'docx').'.docx';
        file_put_contents($tmp, $bytes);

        $reader = IOFactory::load($tmp, 'Word2007');
        $text = '';

        foreach ($reader->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText()."\n";
                }

                if (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $child) {
                        if (method_exists($child, 'getText')) {
                            $text .= $child->getText()."\n";
                        }
                    }
                }
            }
        }

        @unlink($tmp);

        $this->assertStringContainsString('Jane Doe', $text);
        $this->assertStringContainsString('Acme Corp', $text);
        $this->assertStringContainsString('RINGKASAN', $text);
        $this->assertStringContainsString('PENGALAMAN KERJA', $text);
        $this->assertLessThan(
            mb_strpos($text, 'PENGALAMAN KERJA'),
            mb_strpos($text, 'RINGKASAN'),
        );
    }

    public function test_owner_can_download_but_other_user_cannot(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $other->careerProfile()->create();

        $document = $this->document($owner);
        $export = app(DocumentExportService::class)->create($document, 'pdf');

        $this->actingAs($owner)
            ->get(route('exports.download', $export))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($other)->get(route('exports.download', $export))->assertForbidden();
        $this->actingAs($other)->delete(route('exports.destroy', $export))->assertForbidden();
        $this->actingAs($other)->post(route('documents.exports.store', $document), ['format' => 'pdf'])->assertForbidden();
    }

    public function test_guest_cannot_download(): void
    {
        $owner = User::factory()->create();
        $document = $this->document($owner);
        $export = app(DocumentExportService::class)->create($document, 'pdf');

        $this->get(route('exports.download', $export))->assertRedirect('/login');
    }

    public function test_deleting_export_removes_the_file(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user);
        $export = app(DocumentExportService::class)->create($document, 'docx');

        $path = $export->storage_path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($user)
            ->delete(route('exports.destroy', $export))
            ->assertRedirect(route('documents.show', $document->id));

        $this->assertDatabaseCount('document_exports', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_deleting_document_removes_all_exports_and_files(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user);

        $pdf = app(DocumentExportService::class)->create($document, 'pdf');
        $docx = app(DocumentExportService::class)->create($document, 'docx');

        $paths = [$pdf->storage_path, $docx->storage_path];

        $this->actingAs($user)
            ->delete(route('documents.destroy', $document))
            ->assertRedirect();

        $this->assertDatabaseCount('document_exports', 0);

        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_retention_keeps_only_five_exports_per_format(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user);

        $service = app(DocumentExportService::class);
        $paths = [];

        for ($i = 0; $i < 7; $i++) {
            $paths[] = $service->create($document, 'pdf')->storage_path;
        }

        $this->assertSame(DocumentExportService::KEEP_PER_FORMAT, $document->exports()->where('format', 'pdf')->count());

        // The two oldest files are gone from disk.
        Storage::disk('local')->assertMissing($paths[0]);
        Storage::disk('local')->assertMissing($paths[1]);
        Storage::disk('local')->assertExists($paths[6]);
    }

    public function test_export_rejected_when_document_not_ready(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user, [
            'generation_status' => GeneratedDocument::STATUS_PENDING,
            'content_json' => null,
        ]);

        $this->actingAs($user)
            ->post(route('documents.exports.store', $document), ['format' => 'pdf'])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('document_exports', 0);
    }

    public function test_invalid_format_is_rejected(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user);

        $this->actingAs($user)
            ->post(route('documents.exports.store', $document), ['format' => 'exe'])
            ->assertSessionHasErrors('format');

        $this->assertDatabaseCount('document_exports', 0);
    }

    public function test_filename_is_safe_and_descriptive(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user);

        $filename = app(DocumentExportService::class)->filename($document, 'pdf');

        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+\.pdf$/', $filename);
        $this->assertStringContainsString('cv', $filename);
        $this->assertStringContainsString('senior-backend-engineer', $filename);
        $this->assertStringContainsString('global-fintech', $filename);
        $this->assertStringContainsString('v2', $filename);
    }

    public function test_show_page_lists_exports(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user);

        app(DocumentExportService::class)->create($document, 'pdf');

        $this->actingAs($user)
            ->get(route('documents.show', $document))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('exports', 1)
                ->where('exports.0.format', 'pdf')
                ->where('exports.0.format_label', 'PDF'));
    }
}
