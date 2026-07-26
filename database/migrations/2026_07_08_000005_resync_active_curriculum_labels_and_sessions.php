<?php

use App\Models\Methodology;
use App\Models\ModuleSession;
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
            $this->syncProgram('Newborn Care', $curriculum['newborn'] ?? []);
            $this->syncProgram('Infant and Child Care', $curriculum['infant_child'] ?? []);
        });
    }

    public function down(): void
    {
        // Non-destructive.
    }

    private function syncProgram(string $programName, array $modules): void
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

            if (!$module) {
                $module = ProgramModule::create([
                    'program_id' => $program->id,
                    'name' => $moduleData['module'],
                    'description' => $moduleData['description'] ?? null,
                    'order_sequence' => $index + 1,
                    'total_time_minutes' => collect($moduleData['sessions'] ?? [])->sum('time_minutes'),
                    'is_active' => true,
                ]);
            } else {
                $module->update([
                    'name' => $moduleData['module'],
                    'description' => $moduleData['description'] ?? null,
                    'order_sequence' => $index + 1,
                    'total_time_minutes' => collect($moduleData['sessions'] ?? [])->sum('time_minutes'),
                    'is_active' => true,
                ]);
            }

            $this->syncModuleSessions($module, $moduleData['sessions'] ?? []);
        }
    }

    private function syncModuleSessions(ProgramModule $module, array $sessions): void
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

            $session = $activeSessions[$index] ?? null;
            if (!$session) {
                ModuleSession::create([
                    'program_module_id' => $module->id,
                    'name' => $sessionData['session'],
                    'description' => $sessionData['description'] ?? null,
                    'time_minutes' => $sessionData['time_minutes'],
                    'methodology_id' => $methodologyId,
                    'order_sequence' => $index + 1,
                    'is_active' => true,
                ]);
                continue;
            }

            $session->update([
                'name' => $sessionData['session'],
                'description' => $sessionData['description'] ?? null,
                'time_minutes' => $sessionData['time_minutes'],
                'methodology_id' => $methodologyId,
                'order_sequence' => $index + 1,
                'is_active' => true,
            ]);
        }
    }
};


