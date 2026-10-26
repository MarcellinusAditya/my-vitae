<?php

namespace App\Services\Ai;

use App\Services\ProfileSnapshotService;

/**
 * Builds the job-fit prompt and calls the shared chat completion client.
 */
class OpenAiCompatibleJobFitAnalysis implements JobFitAnalysisProvider
{
    private const MAX_INPUT_CHARS = 20000;

    public function __construct(
        private readonly ChatCompletionClient $client,
        private readonly ProfileSnapshotService $snapshots,
    ) {}

    public function name(): string
    {
        return 'openai-compatible';
    }

    public function analyze(array $profileSnapshot, array $jobSnapshot): array
    {
        $profileText = mb_substr($this->snapshots->toPromptText($profileSnapshot), 0, self::MAX_INPUT_CHARS);
        $jobText = mb_substr($this->jobText($jobSnapshot), 0, self::MAX_INPUT_CHARS);

        $content = $this->client->complete([
            ['role' => 'system', 'content' => 'You only output one valid JSON object. Never add commentary or markdown.'],
            ['role' => 'user', 'content' => $this->prompt($profileText, $jobText, (string) ($jobSnapshot['language'] ?? 'id'))],
        ], 0.1);

        return JobFitAnalysisValidator::validate($content, $profileSnapshot);
    }

    /**
     * @param  array<string, mixed>  $jobSnapshot
     */
    private function jobText(array $jobSnapshot): string
    {
        $lines = [
            'Position: '.($jobSnapshot['job_title'] ?? ''),
            'Company: '.($jobSnapshot['company_name'] ?? ''),
        ];

        if (! empty($jobSnapshot['job_location'])) {
            $lines[] = 'Location: '.$jobSnapshot['job_location'];
        }

        $lines[] = "\nJOB DESCRIPTION:\n".($jobSnapshot['job_description'] ?? '');

        if (! empty($jobSnapshot['job_qualifications'])) {
            $lines[] = "\nQUALIFICATIONS:\n".$jobSnapshot['job_qualifications'];
        }

        if (! empty($jobSnapshot['notes'])) {
            $lines[] = "\nNOTES:\n".$jobSnapshot['notes'];
        }

        return implode("\n", $lines);
    }

    private function prompt(string $profileText, string $jobText, string $language): string
    {
        $outputLanguage = $language === 'en' ? 'English' : 'Bahasa Indonesia';

        return <<<PROMPT
You are a job-fit analyst. Compare the candidate profile with the job posting and report, requirement by requirement, whether the profile provides evidence.

HARD RULES — violating these makes the output unusable:
1. NEVER invent experience, employers, skills, dates, certifications, or metrics. Only use the profile text.
2. Every requirement you mark as "matched" or "partial" MUST cite at least one evidence reference in the exact form [experience:ID], [education:ID], [skill:ID], [project:ID] or [certification:ID] copied from the profile text. IDs are the numbers in brackets.
3. If you cannot cite a real ID, the requirement is "not_evidenced" with an empty evidence_refs array.
4. Do not output a single "ATS score", pass probability, or hiring prediction. Only per-requirement status.
5. Write "text", "recommendation" and "keyword" values in {$outputLanguage}.

Return one JSON object with exactly these keys:

{
  "requirements": [
    {
      "text": string,
      "category": "responsibility" | "required_qualification" | "preferred_qualification" | "skill" | "tool" | "seniority",
      "priority": "must_have" | "nice_to_have",
      "match_status": "matched" | "partial" | "not_evidenced",
      "evidence_refs": ["experience:12", "skill:4"],
      "recommendation": string|null
    }
  ],
  "keyword_suggestions": [
    {"keyword": string, "supported_by_profile": bool, "evidence_refs": ["skill:4"]}
  ],
  "seniority": string|null,
  "summary": string
}

Rules for the fields:
- Include at most 20 requirements, ordered must-have first.
- "matched" = the profile clearly proves it. "partial" = related evidence exists but needs the candidate to confirm/expand. "not_evidenced" = nothing in the profile supports it.
- "recommendation" only for partial/not_evidenced: one concrete, honest action (e.g. "Add the migration project you led to your profile"), never advice to fabricate.
- "keyword_suggestions" lists important JD keywords; supported_by_profile must be false when no evidence ref is given.
- "summary" is 2-3 neutral sentences describing coverage. Never promise passing screening.

PROFILE:
{$profileText}

JOB POSTING:
{$jobText}
PROMPT;
    }
}
