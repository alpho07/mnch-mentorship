<?php

use App\Models\ClassModule;
use App\Models\ModuleSession;
use App\Models\ProgramModule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->remapProgram(4);
            $this->remapProgram(5);
        });
    }

    public function down(): void
    {
        // Non-destructive. Historical class modules were remapped only when
        // untouched (not_started). Rolling back would require a manual mapping
        // back to the retired curriculum rows.
    }

    private function remapProgram(int $programId): void
    {
        $newModulesByOrder = ProgramModule::query()
            ->where('program_id', $programId)
            ->where('is_active', true)
            ->pluck('id', 'order_sequence')
            ->all();

        $classModules = ClassModule::query()
            ->whereHas('mentorshipClass', fn ($q) => $q->where('status', 'active'))
            ->where('status', 'not_started')
            ->whereHas('programModule', fn ($q) => $q->where('program_id', $programId)->where('is_active', false))
            ->get();

        foreach ($classModules as $classModule) {
            $newProgramModuleId = $newModulesByOrder[$classModule->programModule->order_sequence] ?? null;

            if (!$newProgramModuleId) {
                continue;
            }

            if ($classModule->sessions()->where('attendance_taken', true)->exists()) {
                continue;
            }

            $classModule->sessions()->delete();

            $classModule->update([
                'program_module_id' => $newProgramModuleId,
                'order_sequence' => ProgramModule::whereKey($newProgramModuleId)->value('order_sequence') ?? $classModule->order_sequence,
            ]);

            $module = ClassModule::find($classModule->id);
            if ($module) {
                $module->autoCreateSessions();
            }
        }
    }
};


