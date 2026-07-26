<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class MentorshipCurriculumAuditService
{
    private const TARGET_PROGRAM_NAMES = ['Newborn Care', 'Infant and Child Care'];

    public function audit(): array
    {
        $curriculum = $this->loadCurriculum();
        $this->assertSchema();

        $programAudits = $this->buildProgramAudits($curriculum);
        $referenceAudit = $this->buildReferenceAudit();

        $programProblems = [];
        foreach ($programAudits as $programAudit) {
            $programProblems = array_merge($programProblems, $programAudit['problems']);
        }

        $problems = array_merge($programProblems, $referenceAudit['problems']);

        return [
            'curriculum_file' => database_path('seeders/data/mentorship_curriculum_2025_10_13.php'),
            'programs' => $programAudits,
            'reference_state' => $referenceAudit,
            'problem_count' => count($problems),
            'problems' => $problems,
        ];
    }

    public function assertClean(): void
    {
        $audit = $this->audit();

        if ($audit['problem_count'] > 0) {
            throw new RuntimeException(
                'Curriculum release state verification failed: ' . implode(', ', $audit['problems'])
            );
        }
    }

    public function repairKnownReleaseIssues(): array
    {
        $this->assertSchema();

        $fixedSessions = [];

        $brokenSessions = DB::table('class_sessions as cs')
            ->join('class_modules as cm', 'cs.class_module_id', '=', 'cm.id')
            ->join('program_modules as pm', 'cm.program_module_id', '=', 'pm.id')
            ->leftJoin('module_sessions as ms', 'cs.module_session_id', '=', 'ms.id')
            ->whereIn('pm.program_id', $this->targetProgramIds())
            ->where(function ($query): void {
                $query->whereNull('cs.module_session_id')
                    ->orWhere('ms.is_active', false);
            })
            ->orderBy('cs.id')
            ->get([
                'cs.id',
                'cs.class_module_id',
                'cs.session_number',
                'cs.title',
                'cm.program_module_id',
                'cs.module_session_id as current_module_session_id',
            ]);

        foreach ($brokenSessions as $row) {
            $template = $this->resolveTemplateSession(
                (int) $row->program_module_id,
                (int) $row->session_number,
                (string) $row->title
            );

            if (!$template) {
                    throw new RuntimeException(
                        "Unable to resolve template session for class_session {$row->id} ({$row->title})"
                    );
                }

                DB::table('class_sessions')
                    ->where('id', $row->id)
                    ->update([
                        'module_session_id' => $template->id,
                    'session_number' => $template->order_sequence,
                    'title' => $template->name,
                    'duration_minutes' => $template->time_minutes,
                ]);

            $fixedSessions[] = [
                'class_session_id' => (int) $row->id,
                'module_session_id' => (int) $template->id,
                'title' => $template->name,
            ];
        }

        return $fixedSessions;
    }

    public function loadCurriculum(): array
    {
        $path = database_path('seeders/data/mentorship_curriculum_2025_10_13.php');

        if (!File::exists($path)) {
            throw new RuntimeException("Curriculum source file missing: {$path}");
        }

        $curriculum = require $path;

        if (!is_array($curriculum)) {
            throw new RuntimeException('Curriculum source file did not return an array.');
        }

        return $curriculum;
    }

    private function targetProgramIds(): array
    {
        return DB::table('programs')
            ->whereIn('name', self::TARGET_PROGRAM_NAMES)
            ->pluck('id')
            ->all();
    }

    private function resolveTemplateSession(int $programModuleId, int $sessionNumber, string $title): ?object
    {
        $templates = DB::table('module_sessions')
            ->where('program_module_id', $programModuleId)
            ->where('is_active', true)
            ->orderBy('order_sequence')
            ->get(['id', 'name', 'order_sequence', 'time_minutes']);

        if ($templates->isEmpty()) {
            return null;
        }

        $normalizedTitle = $this->normalizeLabel($title);

        // Legacy Supportive Topics rows in the production copy used a retired
        // "Referral Form Completion and Communication" session. The new
        // curriculum collapses this into the referral/follow-up session.
        if ($programModuleId === 37 && str_contains($normalizedTitle, 'referral')) {
            return $templates->firstWhere('order_sequence', 2) ?? $templates->first();
        }

        if ($templates->count() === 1) {
            return $templates->first();
        }

        if ($sessionNumber >= 1 && $sessionNumber <= $templates->count()) {
            $indexed = $templates[$sessionNumber - 1] ?? null;
            if ($indexed) {
                return $indexed;
            }
        }

        foreach ($templates as $template) {
            $normalizedTemplate = $this->normalizeLabel($template->name);
            if ($normalizedTitle === $normalizedTemplate) {
                return $template;
            }
        }

        foreach ($templates as $template) {
            $normalizedTemplate = $this->normalizeLabel($template->name);
            if ($normalizedTitle !== '' && (
                str_contains($normalizedTitle, $normalizedTemplate) ||
                str_contains($normalizedTemplate, $normalizedTitle)
            )) {
                return $template;
            }
        }

        if (preg_match('/session\s*(\d+)/i', $title, $matches)) {
            $index = (int) $matches[1] - 1;
            if (isset($templates[$index])) {
                return $templates[$index];
            }
        }

        return null;
    }

    private function normalizeLabel(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s*\(copy\)\s*/i', ' ', $value);
        $value = preg_replace('/^session\s*\d+\s*:\s*/i', '', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function assertSchema(): void
    {
        $tables = [
            'programs',
            'program_modules',
            'module_sessions',
            'class_modules',
            'class_sessions',
            'resource_program_modules',
            'mentorship_module_usages',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException("Required table missing: {$table}");
            }
        }

        $requiredColumns = [
            'program_modules' => ['program_id', 'name', 'order_sequence', 'is_active'],
            'module_sessions' => ['program_module_id', 'name', 'time_minutes', 'order_sequence', 'is_active'],
            'class_modules' => ['program_module_id', 'status'],
            'class_sessions' => ['class_module_id', 'module_session_id', 'session_number', 'title'],
            'resource_program_modules' => ['program_module_id'],
            'mentorship_module_usages' => ['module_id'],
        ];

        foreach ($requiredColumns as $table => $columns) {
            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    throw new RuntimeException("Required column missing: {$table}.{$column}");
                }
            }
        }
    }

    private function buildProgramAudits(array $curriculum): array
    {
        $expected = [
            'Newborn Care' => $curriculum['newborn'] ?? [],
            'Infant and Child Care' => $curriculum['infant_child'] ?? [],
        ];

        $audits = [];

        foreach ($expected as $programName => $modules) {
            $program = DB::table('programs')
                ->where('name', $programName)
                ->first(['id', 'name']);

            if (!$program) {
                $audits[] = [
                    'program' => $programName,
                    'expected_module_count' => count($modules),
                    'actual_module_count' => 0,
                    'modules' => [],
                    'problems' => ["Program not found: {$programName}"],
                ];
                continue;
            }

            $activeModules = DB::table('program_modules')
                ->where('program_id', $program->id)
                ->where('is_active', true)
                ->orderBy('order_sequence')
                ->get(['id', 'name', 'order_sequence', 'total_time_minutes']);

            $programProblems = [];
            $moduleAudits = [];

            if ($activeModules->count() !== count($modules)) {
                $programProblems[] = "active module count mismatch: expected " . count($modules) . ", found " . $activeModules->count();
            }

            foreach ($modules as $index => $module) {
                $row = $activeModules[$index] ?? null;
                $expectedModuleName = $module['module'];
                $expectedOrder = $index + 1;
                $expectedMinutes = (int) collect($module['sessions'] ?? [])->sum('time_minutes');

                if (!$row) {
                    $programProblems[] = "missing active module at position {$expectedOrder}";
                    $moduleAudits[] = [
                        'expected_order' => $expectedOrder,
                        'expected_name' => $expectedModuleName,
                        'actual_name' => null,
                        'status' => 'missing',
                        'problems' => ["Missing active module at position {$expectedOrder}"],
                        'sessions' => [],
                    ];
                    continue;
                }

                $moduleProblems = [];
                if ($row->name !== $expectedModuleName) {
                    $moduleProblems[] = "name expected {$expectedModuleName}, found {$row->name}";
                }
                if ((int) $row->order_sequence !== $expectedOrder) {
                    $moduleProblems[] = "order expected {$expectedOrder}, found {$row->order_sequence}";
                }
                if ((int) $row->total_time_minutes !== $expectedMinutes) {
                    $moduleProblems[] = "duration expected {$expectedMinutes}, found {$row->total_time_minutes}";
                }

                $sessions = DB::table('module_sessions as ms')
                    ->leftJoin('methodologies as m', 'ms.methodology_id', '=', 'm.id')
                    ->where('ms.program_module_id', $row->id)
                    ->where('ms.is_active', true)
                    ->orderBy('ms.order_sequence')
                    ->get([
                        'ms.id',
                        'ms.name',
                        'ms.time_minutes',
                        'ms.order_sequence',
                        'ms.methodology_id',
                        'm.name as methodology_name',
                    ]);

                $sessionAudits = [];
                $expectedSessionCount = count($module['sessions'] ?? []);
                if ($sessions->count() !== $expectedSessionCount) {
                    $moduleProblems[] = "session count expected {$expectedSessionCount}, found {$sessions->count()}";
                }

                foreach ($module['sessions'] ?? [] as $sessionIndex => $sessionData) {
                    $sessionRow = $sessions[$sessionIndex] ?? null;
                    $expectedSessionOrder = $sessionIndex + 1;
                    $expectedSessionName = $sessionData['session'];
                    $expectedSessionMinutes = (int) $sessionData['time_minutes'];
                    $expectedMethodology = $sessionData['methodology'] ?? null;

                    if (!$sessionRow) {
                        $sessionAudits[] = [
                            'expected_order' => $expectedSessionOrder,
                            'expected_name' => $expectedSessionName,
                            'actual_name' => null,
                            'status' => 'missing',
                            'problems' => ["Missing active session at position {$expectedSessionOrder}"],
                        ];
                        continue;
                    }

                    $sessionProblems = [];
                    if ($sessionRow->name !== $expectedSessionName) {
                        $sessionProblems[] = "name expected {$expectedSessionName}, found {$sessionRow->name}";
                    }
                    if ((int) $sessionRow->order_sequence !== $expectedSessionOrder) {
                        $sessionProblems[] = "order expected {$expectedSessionOrder}, found {$sessionRow->order_sequence}";
                    }
                    if ((int) $sessionRow->time_minutes !== $expectedSessionMinutes) {
                        $sessionProblems[] = "duration expected {$expectedSessionMinutes}, found {$sessionRow->time_minutes}";
                    }
                    if ($expectedMethodology === null) {
                        if ($sessionRow->methodology_id !== null) {
                            $sessionProblems[] = "expected no methodology, found {$sessionRow->methodology_name}";
                        }
                    } elseif ($sessionRow->methodology_name !== $expectedMethodology) {
                        $sessionProblems[] = "methodology expected {$expectedMethodology}, found {$sessionRow->methodology_name}";
                    }

                    $sessionAudits[] = [
                        'expected_order' => $expectedSessionOrder,
                        'expected_name' => $expectedSessionName,
                        'actual_name' => $sessionRow->name,
                        'expected_minutes' => $expectedSessionMinutes,
                        'actual_minutes' => (int) $sessionRow->time_minutes,
                        'expected_methodology' => $expectedMethodology,
                        'actual_methodology' => $sessionRow->methodology_name,
                        'status' => $sessionProblems === [] ? 'ok' : 'problem',
                        'problems' => $sessionProblems,
                    ];
                }

                if ($moduleProblems !== []) {
                    $programProblems[] = "{$row->name}: " . implode('; ', $moduleProblems);
                }

                $moduleAudits[] = [
                    'expected_order' => $expectedOrder,
                    'expected_name' => $expectedModuleName,
                    'actual_name' => $row->name,
                    'expected_minutes' => $expectedMinutes,
                    'actual_minutes' => (int) $row->total_time_minutes,
                    'session_count' => $sessions->count(),
                    'expected_session_count' => $expectedSessionCount,
                    'status' => $moduleProblems === [] ? 'ok' : 'problem',
                    'problems' => $moduleProblems,
                    'sessions' => $sessionAudits,
                ];
            }

            $audits[] = [
                'program' => $programName,
                'program_id' => (int) $program->id,
                'expected_module_count' => count($modules),
                'actual_module_count' => $activeModules->count(),
                'status' => $programProblems === [] ? 'ok' : 'problem',
                'problems' => $programProblems,
                'modules' => $moduleAudits,
            ];
        }

        return $audits;
    }

    private function buildReferenceAudit(): array
    {
        $programIds = DB::table('programs')
            ->whereIn('name', ['Newborn Care', 'Infant and Child Care'])
            ->pluck('id')
            ->all();

        if ($programIds === []) {
            throw new RuntimeException('Target programs not found while checking reference state.');
        }

        $nullClassModuleRefs = DB::table('class_modules')
            ->whereNull('program_module_id')
            ->count();

        $nullClassSessionRefs = DB::table('class_sessions')
            ->whereNull('module_session_id')
            ->count();

        $nullResourceRefs = DB::table('resource_program_modules')
            ->whereNull('program_module_id')
            ->count();

        $nullUsageRefs = DB::table('mentorship_module_usages')
            ->whereNull('module_id')
            ->count();

        $inactiveClassRefs = DB::table('class_modules as cm')
            ->join('program_modules as pm', 'cm.program_module_id', '=', 'pm.id')
            ->whereIn('pm.program_id', $programIds)
            ->where('pm.is_active', false)
            ->count();

        $inactiveSessionRefs = DB::table('class_sessions as cs')
            ->join('class_modules as cm', 'cs.class_module_id', '=', 'cm.id')
            ->join('module_sessions as ms', 'cs.module_session_id', '=', 'ms.id')
            ->join('program_modules as pm', 'cm.program_module_id', '=', 'pm.id')
            ->whereIn('pm.program_id', $programIds)
            ->where('ms.is_active', false)
            ->count();

        $inactiveResourceRefs = DB::table('resource_program_modules as rpm')
            ->join('program_modules as pm', 'rpm.program_module_id', '=', 'pm.id')
            ->whereIn('pm.program_id', $programIds)
            ->where('pm.is_active', false)
            ->count();

        $inactiveUsageRefs = DB::table('mentorship_module_usages as mmu')
            ->join('program_modules as pm', 'mmu.module_id', '=', 'pm.id')
            ->whereIn('pm.program_id', $programIds)
            ->where('pm.is_active', false)
            ->count();

        $problems = [];

        if ($nullClassModuleRefs > 0) {
            $problems[] = "class_modules.program_module_id null={$nullClassModuleRefs}";
        }

        if ($nullClassSessionRefs > 0) {
            $problems[] = "class_sessions.module_session_id null={$nullClassSessionRefs}";
        }

        if ($nullResourceRefs > 0) {
            $problems[] = "resource_program_modules.program_module_id null={$nullResourceRefs}";
        }

        if ($nullUsageRefs > 0) {
            $problems[] = "mentorship_module_usages.module_id null={$nullUsageRefs}";
        }

        if ($inactiveClassRefs > 0) {
            $problems[] = "inactive class module refs={$inactiveClassRefs}";
        }

        if ($inactiveSessionRefs > 0) {
            $problems[] = "inactive class session refs={$inactiveSessionRefs}";
        }

        if ($inactiveResourceRefs > 0) {
            $problems[] = "inactive resource refs={$inactiveResourceRefs}";
        }

        if ($inactiveUsageRefs > 0) {
            $problems[] = "inactive usage refs={$inactiveUsageRefs}";
        }

        return [
            'null_class_module_refs' => $nullClassModuleRefs,
            'null_class_session_refs' => $nullClassSessionRefs,
            'null_resource_refs' => $nullResourceRefs,
            'null_usage_refs' => $nullUsageRefs,
            'inactive_class_refs' => $inactiveClassRefs,
            'inactive_session_refs' => $inactiveSessionRefs,
            'inactive_resource_refs' => $inactiveResourceRefs,
            'inactive_usage_refs' => $inactiveUsageRefs,
            'status' => $problems === [] ? 'ok' : 'problem',
            'problems' => $problems,
        ];
    }
}
