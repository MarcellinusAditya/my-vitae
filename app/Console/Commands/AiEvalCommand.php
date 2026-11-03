<?php

namespace App\Console\Commands;

use App\Models\JobApplication;
use App\Models\User;
use App\Services\Ai\DocumentGenerationProvider;
use App\Services\Ai\JobFitAnalysisProvider;
use App\Services\Ai\JobFitAnalysisValidator;
use App\Services\ProfileSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Manual quality evaluation against the real AI provider.
 *
 * Not part of the test suite: it costs money and needs network access. Fixtures
 * are anonymous and cover three seniority levels.
 */
class AiEvalCommand extends Command
{
    protected $signature = 'app:ai-eval {--case= : Only run one fixture (junior|mid|senior)} {--keep : Keep the evaluation user and data}';

    protected $description = 'Run anonymous AI quality checks against the configured provider';

    public function handle(JobFitAnalysisProvider $analysis, DocumentGenerationProvider $generation, ProfileSnapshotService $snapshots): int
    {
        $cases = $this->cases($snapshots);

        if ($only = $this->option('case')) {
            $cases = array_filter($cases, fn ($c) => $c['key'] === $only);
        }

        if ($cases === []) {
            $this->error('No matching case.');

            return self::FAILURE;
        }

        $failures = 0;

        DB::beginTransaction();

        try {
            foreach ($cases as $case) {
                $this->line('');
                $this->info("=== {$case['key']} — {$case['label']} ===");
                $failures += $this->evaluate($case, $analysis, $generation, $snapshots);
            }
        } finally {
            DB::rollBack();
        }

        $this->line('');
        $this->info($failures === 0 ? 'ALL CHECKS PASSED' : "{$failures} CHECK(S) FAILED");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private function evaluate(array $case, JobFitAnalysisProvider $analysis, DocumentGenerationProvider $generation, ProfileSnapshotService $snapshots): int
    {
        $failures = 0;
        $profile = $case['profile'];
        $job = $case['job'];

        // --- job-fit analysis -------------------------------------------
        try {
            $result = $analysis->analyze($profile, $job);
            $requirements = $result['requirements'] ?? [];
            $byStatus = collect($requirements)->countBy('match_status')->all();

            $matched = $byStatus['matched'] ?? 0;
            $partial = $byStatus['partial'] ?? 0;
            $missing = $byStatus['not_evidenced'] ?? 0;

            $this->line("  analysis: {$matched} matched, {$partial} partial, {$missing} not evidenced (".count($requirements).' requirements)');

            if ($requirements === []) {
                $this->error('  ✗ no requirements extracted');
                $failures++;
            } else {
                $this->line('  ✓ requirements extracted');
            }

            // The validator demotes claims without evidence; nothing should
            // remain "matched" while carrying zero evidence refs.
            $unsupported = collect($requirements)
                ->filter(fn ($r) => ($r['match_status'] ?? null) === 'matched' && ($r['evidence_refs'] ?? []) === []);

            if ($unsupported->isNotEmpty()) {
                $this->error('  ✗ '.$unsupported->count().' matched requirement(s) without evidence');
                $failures++;
            } else {
                $this->line('  ✓ every matched requirement cites evidence');
            }

            // A requirement marked not_evidenced must tell the user what to do.
            $noAdvice = collect($requirements)
                ->filter(fn ($r) => ($r['match_status'] ?? null) === 'not_evidenced' && trim((string) ($r['recommendation'] ?? '')) === '');

            if ($noAdvice->isNotEmpty()) {
                $this->error('  ✗ '.$noAdvice->count().' not_evidenced requirement(s) without a recommendation');
                $failures++;
            } else {
                $this->line('  ✓ gaps come with recommendations');
            }

            // Nothing may cite an id that does not exist in the snapshot.
            $known = JobFitAnalysisValidator::knownReferences($profile);
            $bogus = collect($requirements)
                ->flatMap(fn ($r) => $r['evidence_refs'] ?? [])
                ->reject(fn ($ref) => isset($known[$ref]))
                ->unique();

            if ($bogus->isNotEmpty()) {
                $this->error('  ✗ unknown evidence reference(s): '.$bogus->implode(', '));
                $failures++;
            } else {
                $this->line('  ✓ all evidence references resolve to profile rows');
            }
        } catch (\Throwable $e) {
            $this->error('  ✗ analysis threw '.$e::class.': '.$e->getMessage());
            $failures++;
        }

        // --- resume generation ------------------------------------------
        try {
            $resume = $generation->generateResume($profile, $job, ['language' => 'id', 'style' => 'professional', 'length' => 'concise']);

            $companies = collect($resume['experiences'] ?? [])->pluck('organization')->filter()->all();
            $known = collect($profile['experiences'] ?? [])->pluck('organization')->all();
            $invented = array_diff($companies, $known);

            $this->line('  resume: '.count($companies).' experience entr(ies)');

            if ($invented !== []) {
                $this->error('  ✗ invented employer(s): '.implode(', ', $invented));
                $failures++;
            } else {
                $this->line('  ✓ no invented employers');
            }

            $bullets = collect($resume['experiences'] ?? [])->flatMap(fn ($e) => $e['bullets'] ?? []);
            $empty = $bullets->filter(fn ($b) => trim((string) $b) === '');
            if ($empty->isNotEmpty()) {
                $this->error('  ✗ '.$empty->count().' empty bullet(s)');
                $failures++;
            } else {
                $this->line('  ✓ no empty bullets ('.$bullets->count().' total)');
            }

            $summary = trim((string) ($resume['summary'] ?? ''));
            if (mb_strlen($summary) < 40) {
                $this->error('  ✗ summary too short ('.mb_strlen($summary).' chars)');
                $failures++;
            } else {
                $this->line('  ✓ summary present ('.mb_strlen($summary).' chars)');
            }
        } catch (\Throwable $e) {
            $this->error('  ✗ resume threw '.$e::class.': '.$e->getMessage());
            $failures++;
        }

        // --- cover letter -----------------------------------------------
        try {
            $letter = $generation->generateCoverLetter($profile, $job, ['language' => 'id', 'style' => 'professional']);
            $body = $letter['body_paragraphs'] ?? [];
            $text = is_array($body) ? implode(' ', array_map(fn ($p) => is_array($p) ? ($p['text'] ?? '') : (string) $p, $body)) : (string) $body;

            $this->line('  cover letter: '.count($body).' paragraph(s), '.mb_strlen($text).' chars');

            if (count($body) < 2) {
                $this->error('  ✗ fewer than 2 body paragraphs');
                $failures++;
            } else {
                $this->line('  ✓ at least 2 body paragraphs');
            }

            // No placeholder leakage.
            if (preg_match('/\[(nama|company|perusahaan|posisi|position)\]|lorem ipsum/i', $text) === 1) {
                $this->error('  ✗ placeholder text found in body');
                $failures++;
            } else {
                $this->line('  ✓ no placeholder text');
            }
        } catch (\Throwable $e) {
            $this->error('  ✗ cover letter threw '.$e::class.': '.$e->getMessage());
            $failures++;
        }

        return $failures;
    }

    /**
     * Anonymous fixtures covering three seniority levels.
     *
     * Records are created through the real models and snapshotted with
     * ProfileSnapshotService, so the fixtures always match the canonical
     * snapshot shape the providers receive in production.
     *
     * @return list<array{key: string, label: string, profile: array<string, mixed>, job: array<string, mixed>}>
     */
    private function cases(ProfileSnapshotService $snapshots): array
    {
        // Each entry: [key, label, profile attributes, job attributes].
        $definitions = [
            [
                'junior',
                'Fresh graduate, frontend',
                [
                    'summary' => 'Lulusan Informatika dengan pengalaman magang frontend.',
                    'contact' => ['full_name' => 'Kandidat A', 'email' => 'a@example.com', 'location' => 'Bandung'],
                    'skills' => [['name' => 'JavaScript', 'category' => 'Frontend'], ['name' => 'React', 'category' => 'Frontend'], ['name' => 'Git', 'category' => 'Tools']],
                    'experiences' => [[
                        'organization' => 'PT Magang Digital',
                        'job_title' => 'Frontend Intern',
                        'employment_type' => 'internship',
                        'location' => 'Bandung',
                        'start_date' => '2025-01-01',
                        'end_date' => '2025-06-30',
                        'is_current' => false,
                        'description' => 'Membangun 12 komponen React untuk dashboard internal.',
                    ]],
                    'educations' => [[
                        'institution' => 'Universitas Contoh',
                        'degree' => 'S1 Informatika',
                        'field_of_study' => 'Informatika',
                        'start_date' => '2021-08-01',
                        'end_date' => '2025-07-31',
                    ]],
                ],
                [
                    'company_name' => 'Startup A',
                    'job_title' => 'Junior Frontend Developer',
                    'job_location' => 'Bandung',
                    'job_description' => 'Mencari junior frontend dengan React dan Git. Minimal 1 tahun pengalaman atau magang.',
                    'language' => 'id',
                ],
            ],
            [
                'mid',
                'Mid-level backend',
                [
                    'summary' => 'Backend engineer 3 tahun dengan Laravel dan MySQL.',
                    'contact' => ['full_name' => 'Kandidat B', 'email' => 'b@example.com', 'location' => 'Jakarta'],
                    'skills' => [['name' => 'PHP', 'category' => 'Backend'], ['name' => 'Laravel', 'category' => 'Backend'], ['name' => 'MySQL', 'category' => 'Database'], ['name' => 'Docker', 'category' => 'DevOps']],
                    'experiences' => [[
                        'organization' => 'PT Menengah Teknologi',
                        'job_title' => 'Backend Engineer',
                        'employment_type' => 'full_time',
                        'location' => 'Jakarta',
                        'start_date' => '2022-04-01',
                        'is_current' => true,
                        'description' => 'Membangun REST API untuk 8 layanan internal dengan Laravel. Menurunkan waktu query laporan dari 4s ke 400ms lewat indexing.',
                    ]],
                ],
                [
                    'company_name' => 'Perusahaan B',
                    'job_title' => 'Backend Engineer',
                    'job_location' => 'Jakarta',
                    'job_description' => 'Backend engineer untuk layanan pembayaran. Stack PHP/Laravel, MySQL, Docker. Wajib pengalaman 3 tahun.',
                    'language' => 'id',
                ],
            ],
            [
                'senior',
                'Senior engineer with leadership',
                [
                    'summary' => 'Senior engineer 8 tahun, memimpin tim lintas fungsi.',
                    'contact' => ['full_name' => 'Kandidat C', 'email' => 'c@example.com', 'location' => 'Jakarta'],
                    'skills' => [['name' => 'PHP', 'category' => 'Backend'], ['name' => 'Laravel', 'category' => 'Backend'], ['name' => 'Kubernetes', 'category' => 'DevOps'], ['name' => 'Leadership', 'category' => 'Soft skill']],
                    'experiences' => [[
                        'organization' => 'PT Besar Nusantara',
                        'job_title' => 'Lead Backend Engineer',
                        'employment_type' => 'full_time',
                        'location' => 'Jakarta',
                        'start_date' => '2019-02-01',
                        'is_current' => true,
                        'description' => 'Memimpin tim 6 engineer membangun platform ujian dengan 50k pengguna aktif. Menetapkan standar observability yang menurunkan MTTR dari 45 menit ke 8 menit.',
                    ]],
                ],
                [
                    'company_name' => 'Perusahaan C',
                    'job_title' => 'Engineering Lead',
                    'job_location' => 'Jakarta',
                    'job_description' => 'Memimpin tim backend 5-8 orang. Wajib pengalaman Laravel, Kubernetes, dan mentoring.',
                    'language' => 'id',
                ],
            ],
        ];

        $cases = [];

        foreach ($definitions as [$key, $label, $profileAttributes, $jobAttributes]) {
            $user = User::create([
                'name' => 'AI Eval '.ucfirst($key),
                'email' => "ai-eval-{$key}-".uniqid().'@example.test',
                'password' => bcrypt(str()->random(32)),
            ]);

            $profile = $user->careerProfile()->create([
                'summary' => $profileAttributes['summary'],
                'default_language' => 'id',
            ]);

            $profile->contact()->create($profileAttributes['contact']);

            foreach ($profileAttributes['skills'] ?? [] as $skill) {
                $profile->skills()->create($skill);
            }

            foreach ($profileAttributes['experiences'] ?? [] as $experience) {
                $profile->experiences()->create($experience);
            }

            foreach ($profileAttributes['educations'] ?? [] as $education) {
                $profile->educations()->create($education);
            }

            $application = JobApplication::create([
                'user_id' => $user->id,
                'career_profile_id' => $profile->id,
                ...$jobAttributes,
            ]);

            $cases[] = [
                'key' => $key,
                'label' => $label,
                'profile' => $snapshots->build($profile->fresh()),
                'job' => [
                    'company_name' => $application->company_name,
                    'job_title' => $application->job_title,
                    'job_location' => $application->job_location,
                    'job_description' => $application->job_description,
                    'job_qualifications' => $application->job_qualifications,
                    'language' => $application->language,
                ],
            ];
        }

        return $cases;
    }
}
