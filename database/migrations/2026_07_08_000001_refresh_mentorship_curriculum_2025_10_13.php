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
            $this->refreshProgram(
                programName: 'Newborn Care',
                programDescription: 'Comprehensive newborn care training program',
                modules: $curriculum['newborn'] ?? []
            );

            $this->refreshProgram(
                programName: 'Infant and Child Care',
                programDescription: 'Comprehensive infant and child care training program',
                modules: $curriculum['infant_child'] ?? []
            );
        });
    }

    public function down(): void
    {
        // Intentionally left non-destructive.
        // This migration refreshes live curriculum data and preserves historical
        // class foreign keys, so there is no safe automatic rollback.
    }

    private function refreshProgram(string $programName, string $programDescription, array $modules): void
    {
        if ($modules === []) {
            return;
        }

        $program = Program::firstOrCreate(
            ['name' => $programName],
            ['description' => $programDescription]
        );

        $expectedNames = array_map(
            static fn (array $module): string => $module['module'],
            $modules
        );

        $activeModules = ProgramModule::where('program_id', $program->id)
            ->where('is_active', true)
            ->orderBy('order_sequence')
            ->pluck('name')
            ->all();

        if ($activeModules === $expectedNames) {
            return;
        }

        $legacyModuleIds = ProgramModule::where('program_id', $program->id)->pluck('id');

        if ($legacyModuleIds->isNotEmpty()) {
            ModuleSession::whereIn('program_module_id', $legacyModuleIds)->update(['is_active' => false]);
        }

        ProgramModule::where('program_id', $program->id)->update(['is_active' => false]);

        foreach ($modules as $index => $moduleData) {
            $module = ProgramModule::create([
                'program_id' => $program->id,
                'name' => $moduleData['module'],
                'description' => $moduleData['description'] ?? null,
                'order_sequence' => $index + 1,
                'total_time_minutes' => collect($moduleData['sessions'] ?? [])->sum('time_minutes'),
                'is_active' => true,
            ]);

            foreach ($moduleData['sessions'] ?? [] as $sessionIndex => $sessionData) {
                $methodology = null;

                if (!empty($sessionData['methodology'])) {
                    $methodology = Methodology::firstOrCreate(
                        ['name' => $sessionData['methodology']],
                        [
                            'description' => $sessionData['methodology'],
                            'is_active' => true,
                        ]
                    );
                }

                ModuleSession::create([
                    'program_module_id' => $module->id,
                    'name' => $sessionData['session'],
                    'description' => $sessionData['description'] ?? null,
                    'time_minutes' => $sessionData['time_minutes'],
                    'methodology_id' => $methodology?->id,
                    'order_sequence' => $sessionIndex + 1,
                    'is_active' => true,
                ]);
            }
        }
    }
};
