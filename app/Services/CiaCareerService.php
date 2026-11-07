<?php

namespace App\Services;

use JsonException;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class CiaCareerService
{
    /**
     * @return array<string, mixed>
     */
    public function scoreResume(string $pdfPath, string $targetRole): array
    {
        $roles = $this->readRolesDataset();
        $role = null;
        foreach ($roles as $candidate) {
            if ($this->normalizeSkill($candidate['role']) === $this->normalizeSkill($targetRole)) {
                $role = $candidate;
                break;
            }
        }
        if (! is_array($role)) {
            throw new CiaServiceException('The requested target role is not available.', 422);
        }

        $result = $this->runScript('resume_ats_scorer.py', [
            'pdf_path' => $pdfPath,
            'target_role' => $role['role'],
            'required_skills' => $role['required_skills'],
        ], 90);
        if (
            ($result['status'] ?? null) !== 'success'
            || ! is_numeric($result['score'] ?? null)
            || ! is_array($result['skill_gaps'] ?? null)
        ) {
            throw new CiaServiceException('Resume scorer returned an invalid response.', 502);
        }

        return $result;
    }

    /**
     * @param  list<string>  $candidateSkills
     * @return array{matched_skills: list<string>, skill_gaps: list<string>}
     */
    public function compareSkills(array $candidateSkills, string $targetRole): array
    {
        $role = null;
        foreach ($this->readRolesDataset() as $candidate) {
            if ($this->normalizeSkill($candidate['role']) === $this->normalizeSkill($targetRole)) {
                $role = $candidate;
                break;
            }
        }
        if (! is_array($role)) {
            throw new CiaServiceException('The requested target role is not available for skill comparison.', 422);
        }

        $candidateKeys = [];
        foreach ($candidateSkills as $skill) {
            if (is_string($skill) && trim($skill) !== '') {
                $candidateKeys[$this->normalizeSkill($skill)] = true;
            }
        }
        $matched = [];
        $gaps = [];
        foreach ($role['required_skills'] as $requiredSkill) {
            if (isset($candidateKeys[$this->normalizeSkill($requiredSkill)])) {
                $matched[] = $requiredSkill;
            } else {
                $gaps[] = $requiredSkill;
            }
        }

        return ['matched_skills' => $matched, 'skill_gaps' => $gaps];
    }

    private function normalizeSkill(string $skill): string
    {
        return implode(' ', preg_split('/\s+/', mb_strtolower(trim($skill)), -1, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * @param  list<string>  $skills
     * @return array<string, mixed>
     */
    public function matchInternships(array $skills): array
    {
        $skills = $skills ?: ['PHP', 'Laravel', 'JavaScript', 'MySQL', 'Git'];
        $result = $this->runScript('internship_matcher.py', $skills, 90, true);
        if (
            ($result['status'] ?? null) !== 'success'
            || ($result['total_matched'] ?? null) !== 5
            || ! is_array($result['matches'] ?? null)
            || count($result['matches']) !== 5
        ) {
            throw new CiaServiceException('Internship matcher returned an invalid response.', 502);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    public function generateCareerReport(array $profile): array
    {
        $result = $this->runScript('career_report_generator.py', $profile, 120);
        if (
            ! is_string($result['title'] ?? null)
            || ! is_string($result['summary'] ?? null)
            || ! is_array($result['archetypes'] ?? null)
            || count($result['archetypes']) !== 6
            || ! is_string($result['pdf_base64'] ?? null)
        ) {
            throw new CiaServiceException('Career report generator returned an invalid response.', 502);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|list<string>  $payload
     * @return array<string, mixed>
     */
    private function runScript(
        string $script,
        array $payload,
        int $timeout,
        bool $jsonArgument = false,
    ): array {
        try {
            $input = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CiaServiceException('Career service input could not be prepared.', 422, $exception);
        }

        $command = [
            (string) env('CIA_PYTHON_BINARY', 'python'),
            base_path('scripts/'.$script),
        ];
        if ($jsonArgument) {
            $command[] = $input;
        }
        $process = new Process($command, base_path());
        if (! $jsonArgument) {
            $process->setInput($input);
        }
        $process->setTimeout($timeout);

        try {
            $process->run();
        } catch (ProcessStartFailedException $exception) {
            throw new CiaServiceException('Career service could not be started.', 502, $exception);
        } catch (ProcessTimedOutException $exception) {
            throw new CiaServiceException('Career service timed out. Please try again.', 504, $exception);
        }

        if (! $process->isSuccessful()) {
            $error = json_decode($process->getErrorOutput(), true);
            $message = is_string($error['message'] ?? null)
                ? $error['message']
                : 'Career service could not complete the request.';
            $lowerMessage = mb_strtolower($message);
            $status = str_contains($lowerMessage, 'pdf')
                || str_contains($lowerMessage, 'extractable text')
                ? 422
                : 502;
            throw new CiaServiceException($message, $status);
        }

        try {
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CiaServiceException('Career service returned invalid JSON.', 502, $exception);
        }
        if (! is_array($result)) {
            throw new CiaServiceException('Career service returned an invalid response.', 502);
        }

        return $result;
    }

    /**
     * @return list<array{id: string, role: string, required_skills: list<string>, match_criteria: string}>
     */
    private function readRolesDataset(): array
    {
        $path = base_path('scripts/data/roles_dataset.json');
        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            throw new CiaServiceException('The career role dataset could not be read.', 502);
        }
        try {
            $roles = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CiaServiceException('The career role dataset is invalid.', 502, $exception);
        }
        if (! is_array($roles)) {
            throw new CiaServiceException('The career role dataset is invalid.', 502);
        }

        return $roles;
    }
}
