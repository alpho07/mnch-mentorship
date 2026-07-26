<?php

namespace App\Console\Commands;

use App\Services\MentorshipCurriculumAuditService;
use Illuminate\Console\Command;

class AuditMentorshipCurriculum extends Command
{
    protected $signature = 'mentorship:curriculum-audit
                            {--json : Output the audit result as JSON}
                            {--strict : Exit with a failure code when mismatches are found}';

    protected $description = 'Audit the active mentorship curriculum against the 13-Oct manual source';

    public function handle(MentorshipCurriculumAuditService $auditService): int
    {
        $audit = $auditService->audit();

        if ($this->option('json')) {
            $this->line(json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderAudit($audit);
        }

        if (($this->option('strict') || $audit['problem_count'] > 0) && $audit['problem_count'] > 0) {
            $this->error("Audit failed with {$audit['problem_count']} problem(s).");
            return Command::FAILURE;
        }

        $this->info('Audit completed successfully.');

        return Command::SUCCESS;
    }

    private function renderAudit(array $audit): void
    {
        $this->info('Curriculum audit summary');
        $this->line('Source: ' . $audit['curriculum_file']);
        $this->line('Problems: ' . $audit['problem_count']);

        foreach ($audit['programs'] as $program) {
            $this->line(sprintf(
                '- %s: %s modules actual / %s expected [%s]',
                $program['program'],
                $program['actual_module_count'],
                $program['expected_module_count'],
                $program['status']
            ));

            foreach ($program['modules'] as $module) {
                $this->line(sprintf(
                    '  - %s -> %s [%s]',
                    $module['expected_name'],
                    $module['actual_name'] ?? 'missing',
                    $module['status']
                ));
            }
        }

        $reference = $audit['reference_state'];
        $this->line(sprintf(
            'References: null class modules=%d, null class sessions=%d, null resource refs=%d, null usage refs=%d',
            $reference['null_class_module_refs'],
            $reference['null_class_session_refs'],
            $reference['null_resource_refs'],
            $reference['null_usage_refs']
        ));
        $this->line(sprintf(
            'Inactive refs: class modules=%d, class sessions=%d, resources=%d, usages=%d',
            $reference['inactive_class_refs'],
            $reference['inactive_session_refs'],
            $reference['inactive_resource_refs'],
            $reference['inactive_usage_refs']
        ));

        if ($audit['problem_count'] > 0) {
            $this->warn('Problems:');
            foreach ($audit['problems'] as $problem) {
                $this->line('- ' . $problem);
            }
        }
    }
}
