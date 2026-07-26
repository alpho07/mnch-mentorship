<?php

use App\Models\ClassModule;
use App\Models\ClassSession;
use App\Models\Methodology;
use App\Models\ModuleSession;
use App\Models\MentorshipModuleUsage;
use App\Models\Program;
use App\Models\ProgramModule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    public function up(): void
    {
        $path = database_path('seeders/data/mentorship_curriculum_2025_10_13.php');

        if (!File::exists($path)) {
            throw new RuntimeException("Curriculum file not found at {$path}");
        }

        $curriculum = require $path;

        DB::transaction(function () use ($curriculum): void {
            $this->syncTemplates('Newborn Care', $curriculum['newborn'] ?? []);
            $this->syncTemplates('Infant and Child Care', $curriculum['infant_child'] ?? []);
            $this->remapProgramReferences(4);
            $this->remapProgramReferences(5);
            $this->remapResourceReferences(4);
            $this->remapResourceReferences(5);
            $this->remapUsageReferences(4);
            $this->remapUsageReferences(5);
        });
    }

    public function down(): void
    {
        // Non-destructive. The old template IDs remain in the database for
        // historical integrity, but all live references are now mapped forward.
    }

    private function syncTemplates(string $programName, array $modules): void
    {
        $program = Program::where('name', $programName)->first();

        if (!$program || $modules === []) {
            return;
        }

        $activeModules = ProgramModule::where('program_id', $program->id)
            ->where('is_active', true)
            ->orderBy('order_sequence')
            ->get()
            ->values();

        foreach ($modules as $index => $moduleData) {
            $module = $activeModules[$index] ?? null;
            $payload = [
                'program_id' => $program->id,
                'name' => $moduleData['module'],
                'description' => $moduleData['description'] ?? null,
                'order_sequence' => $index + 1,
                'total_time_minutes' => collect($moduleData['sessions'] ?? [])->sum('time_minutes'),
                'is_active' => true,
            ];

            if ($module) {
                $module->update($payload);
            } else {
                $module = ProgramModule::create($payload);
            }

            $this->syncSessions($module, $moduleData['sessions'] ?? []);
        }
    }

    private function syncSessions(ProgramModule $module, array $sessions): void
    {
        $activeSessions = ModuleSession::where('program_module_id', $module->id)
            ->where('is_active', true)
            ->orderBy('order_sequence')
            ->get()
            ->values();

        foreach ($sessions as $index => $sessionData) {
            $methodologyId = null;
            if (!empty($sessionData['methodology'])) {
                $methodologyId = Methodology::firstOrCreate(
                    ['name' => $sessionData['methodology']],
                    [
                        'description' => $sessionData['methodology'],
                        'is_active' => true,
                    ]
                )->id;
            }

            $payload = [
                'program_module_id' => $module->id,
                'name' => $sessionData['session'],
                'description' => $sessionData['description'] ?? null,
                'time_minutes' => $sessionData['time_minutes'],
                'methodology_id' => $methodologyId,
                'order_sequence' => $index + 1,
                'is_active' => true,
            ];

            $session = $activeSessions[$index] ?? null;
            if ($session) {
                $session->update($payload);
            } else {
                ModuleSession::create($payload);
            }
        }
    }

    private function remapProgramReferences(int $programId): void
    {
        $newIdsByOrder = ProgramModule::where('program_id', $programId)
            ->where('is_active', true)
            ->pluck('id', 'order_sequence')
            ->all();

        $classModules = ClassModule::query()
            ->whereHas('programModule', fn ($q) => $q->where('program_id', $programId))
            ->with(['programModule', 'sessions' => fn ($q) => $q->orderBy('session_number')])
            ->get();

        foreach ($classModules as $classModule) {
            $newProgramModuleId = $newIdsByOrder[$classModule->programModule->order_sequence] ?? null;
            if (!$newProgramModuleId) {
                continue;
            }

            $classModule->update([
                'program_module_id' => $newProgramModuleId,
                'order_sequence' => ProgramModule::whereKey($newProgramModuleId)->value('order_sequence') ?? $classModule->order_sequence,
            ]);

            $templateSessions = ModuleSession::where('program_module_id', $newProgramModuleId)
                ->where('is_active', true)
                ->orderBy('order_sequence')
                ->get()
                ->values();

            $existingSessions = $classModule->sessions()->orderBy('session_number')->get()->values();
            $pairedCount = min($existingSessions->count(), $templateSessions->count());

            for ($i = 0; $i < $pairedCount; $i++) {
                $classSession = $existingSessions[$i];
                $templateSession = $templateSessions[$i];
                $classSession->update([
                    'module_session_id' => $templateSession->id,
                    'session_number' => $templateSession->order_sequence,
                    'title' => $templateSession->name,
                    'description' => $templateSession->description,
                    'duration_minutes' => $templateSession->time_minutes,
                ]);
            }

            if ($classModule->status === 'not_started' && $existingSessions->count() < $templateSessions->count()) {
                for ($i = $existingSessions->count(); $i < $templateSessions->count(); $i++) {
                    $templateSession = $templateSessions[$i];
                    ClassSession::create([
                        'class_module_id' => $classModule->id,
                        'module_session_id' => $templateSession->id,
                        'session_number' => $templateSession->order_sequence,
                        'title' => $templateSession->name,
                        'description' => $templateSession->description,
                        'duration_minutes' => $templateSession->time_minutes,
                        'status' => 'scheduled',
                        'attendance_taken' => false,
                    ]);
                }
            }
        }
    }

    private function remapResourceReferences(int $programId): void
    {
        $newIdsByOrder = ProgramModule::where('program_id', $programId)
            ->where('is_active', true)
            ->pluck('id', 'order_sequence')
            ->all();

        $oldModules = ProgramModule::where('program_id', $programId)->get(['id', 'order_sequence']);
        foreach ($oldModules as $oldModule) {
            $newModuleId = $newIdsByOrder[$oldModule->order_sequence] ?? null;
            if (!$newModuleId) {
                continue;
            }

            DB::table('resource_program_modules')
                ->where('program_module_id', $oldModule->id)
                ->update(['program_module_id' => $newModuleId]);
        }
    }

    private function remapUsageReferences(int $programId): void
    {
        $newIdsByOrder = ProgramModule::where('program_id', $programId)
            ->where('is_active', true)
            ->pluck('id', 'order_sequence')
            ->all();

        $oldModules = ProgramModule::where('program_id', $programId)->get(['id', 'order_sequence']);
        foreach ($oldModules as $oldModule) {
            $newModuleId = $newIdsByOrder[$oldModule->order_sequence] ?? null;
            if (!$newModuleId) {
                continue;
            }

            MentorshipModuleUsage::where('module_id', $oldModule->id)
                ->update(['module_id' => $newModuleId]);
        }
    }
};
