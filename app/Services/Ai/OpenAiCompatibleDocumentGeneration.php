<?php

namespace App\Services\Ai;

use App\Services\ProfileSnapshotService;

/**
 * Builds the generation prompts (versioned) and calls the shared client.
 */
class OpenAiCompatibleDocumentGeneration implements DocumentGenerationProvider
{
    /** Bump when a prompt changes so stored documents stay traceable. */
    public const PROMPT_VERSION = 'v2';

    private const MAX_INPUT_CHARS = 20000;

    public function __construct(
        private readonly ChatCompletionClient $client,
        private readonly ProfileSnapshotService $snapshots,
    ) {}

    public function name(): string
    {
        return 'openai-compatible';
    }

    public function generateResume(array $profileSnapshot, array $jobSnapshot, array $preferences): array
    {
        $raw = $this->client->complete([
            ['role' => 'system', 'content' => 'You only output one valid JSON object. Never add commentary or markdown.'],
            ['role' => 'user', 'content' => $this->resumePrompt($profileSnapshot, $jobSnapshot, $preferences)],
        ], 0.3);

        return DocumentFactValidator::validateResume($raw, $profileSnapshot);
    }

    public function generateCoverLetter(array $profileSnapshot, array $jobSnapshot, array $preferences): array
    {
        $raw = $this->client->complete([
            ['role' => 'system', 'content' => 'You only output one valid JSON object. Never add commentary or markdown.'],
            ['role' => 'user', 'content' => $this->coverLetterPrompt($profileSnapshot, $jobSnapshot, $preferences)],
        ], 0.4);

        return DocumentFactValidator::validateCoverLetter($raw, $profileSnapshot, $jobSnapshot);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $job
     * @param  array<string, mixed>  $prefs
     */
    private function resumePrompt(array $profile, array $job, array $prefs): string
    {
        $profileText = mb_substr($this->snapshots->toPromptText($profile), 0, self::MAX_INPUT_CHARS);
        $jobText = $this->jobText($job);
        $language = ($prefs['language'] ?? 'id') === 'en' ? 'English' : 'Bahasa Indonesia';
        $length = ($prefs['resume_length'] ?? 'concise') === 'detailed' ? 'detailed (up to 4 bullets per role)' : 'concise (2-3 bullets per role)';
        $analysis = $this->analysisText($prefs);

        return <<<PROMPT
You write ATS-friendly resumes. Rewrite ONLY what the candidate profile proves, tailored to the job.

NON-NEGOTIABLE RULES:
1. NEVER invent employers, job titles, dates, degrees, certifications, technologies, metrics, or achievements.
2. Every fact (organization, role, start/end dates) MUST be copied EXACTLY from the profile. Do not "improve" dates or titles.
3. Numbers and percentages are ONLY allowed when they appear in the profile text. If you cannot point to the source, omit the number.
4. Every experience, project and skill entry needs "evidence_refs" using the exact ids from the profile text: [experience:ID], [project:ID], [skill:ID], [education:ID], [certification:ID].
5. Write all user-facing text in {$language}. Keep bullets action-first, no fluff, no first-person pronouns in the resume.
6. Length: {$length}. Use standard section headings, one column, no tables/icons/graphics.

Return exactly one JSON object:

{
  "summary": string,
  "skills": [{"name": string, "category": string|null, "evidence_refs": ["skill:4"]}],
  "experiences": [{"organization": string, "role": string, "start_date": string|null, "end_date": string|null, "is_current": bool, "bullets": [string], "evidence_refs": ["experience:12"]}],
  "projects": [{"name": string, "role": string|null, "bullets": [string], "technologies": [string], "evidence_refs": ["project:2"]}],
  "education": [{"institution": string, "degree": string|null, "field_of_study": string|null, "start_date": string|null, "end_date": string|null, "evidence_refs": ["education:7"]}],
  "certifications": [{"name": string, "issuer": string|null, "issue_date": string|null, "evidence_refs": ["certification:9"]}],
  "languages": [string],
  "unverified_claims": [string]
}

"unverified_claims" lists any statement you were tempted to add but could not support — leave it empty if none.
Only include sections that have profile data. Order experience/projects by relevance to the job.

PROFILE (ids in brackets are evidence refs):
{$profileText}

TARGET JOB:
{$jobText}

{$analysis}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $job
     * @param  array<string, mixed>  $prefs
     */
    private function coverLetterPrompt(array $profile, array $job, array $prefs): string
    {
        $profileText = mb_substr($this->snapshots->toPromptText($profile), 0, self::MAX_INPUT_CHARS);
        $jobText = $this->jobText($job);
        $language = ($prefs['language'] ?? 'id') === 'en' ? 'English' : 'Bahasa Indonesia';
        $tone = match ($prefs['tone'] ?? 'professional') {
            'formal' => 'formal and respectful',
            'warm' => 'warm yet professional',
            default => 'professional and direct',
        };
        $recipient = trim((string) ($prefs['recipient_name'] ?? ''));
        $recipientRule = $recipient !== ''
            ? "The user supplied this recipient name: \"{$recipient}\". Use it in the greeting."
            : 'No recipient name was supplied. Use a neutral greeting (e.g. "Dear Hiring Manager" / "Kepada Tim Rekrutmen"). NEVER invent a name.';
        $analysis = $this->analysisText($prefs);

        return <<<PROMPT
You write tailored cover letters grounded strictly in the candidate profile.

NON-NEGOTIABLE RULES:
1. NEVER invent employers, achievements, metrics, referrals, or facts about the company that were not given.
2. Mention only experience that appears in the profile; cite it with "evidence_refs" using exact ids ([experience:3], [project:2], [skill:5]).
3. {$recipientRule}
4. Do not claim the candidate will "guarantee" results or that they will pass screening.
5. Write in {$language}, tone: {$tone}. Specific to this job — never generic filler.
6. 3-4 body paragraphs, each tied to a real requirement of the posting.

Return exactly one JSON object:

{
  "recipient_line": string,
  "opening": string,
  "body_paragraphs": [{"text": string, "evidence_refs": ["experience:3"]}],
  "closing": string,
  "signature": string,
  "evidence_refs": ["experience:3"],
  "unverified_claims": [string]
}

PROFILE (ids in brackets are evidence refs):
{$profileText}

TARGET JOB:
{$jobText}

{$analysis}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private function jobText(array $job): string
    {
        $lines = [
            'Position: '.($job['job_title'] ?? ''),
            'Company: '.($job['company_name'] ?? ''),
        ];

        if (! empty($job['job_location'])) {
            $lines[] = 'Location: '.$job['job_location'];
        }

        $lines[] = "\nJOB DESCRIPTION:\n".($job['job_description'] ?? '');

        if (! empty($job['job_qualifications'])) {
            $lines[] = "\nQUALIFICATIONS:\n".$job['job_qualifications'];
        }

        return implode("\n", $lines);
    }

    /**
     * Requirements the analysis already mapped, so generation targets real gaps.
     *
     * @param  array<string, mixed>  $prefs
     */
    private function analysisText(array $prefs): string
    {
        $requirements = $prefs['analysis_requirements'] ?? [];

        if (! is_array($requirements) || $requirements === []) {
            return '';
        }

        $lines = ['JOB-FIT ANALYSIS (already computed, use to prioritise):'];

        foreach (array_slice($requirements, 0, 12) as $requirement) {
            if (! is_array($requirement)) {
                continue;
            }

            $lines[] = sprintf(
                '- [%s] %s',
                $requirement['match_status'] ?? 'unknown',
                $requirement['text'] ?? '',
            );
        }

        return implode("\n", $lines);
    }
}
