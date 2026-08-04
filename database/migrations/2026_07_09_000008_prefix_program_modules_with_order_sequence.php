<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->prefixProgramModules('Newborn Care');
            $this->prefixProgramModules('Infant and Child Care');
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $this->removeProgramModulePrefixes('Newborn Care');
            $this->removeProgramModulePrefixes('Infant and Child Care');
        });
    }

    private function prefixProgramModules(string $programName): void
    {
        $program = DB::table('programs')->where('name', $programName)->first(['id']);

        if (!$program) {
            throw new \RuntimeException("Program not found: {$programName}");
        }

        $modules = DB::table('program_modules')
            ->where('program_id', $program->id)
            ->orderBy('order_sequence')
            ->get(['id', 'name', 'order_sequence']);

        foreach ($modules as $module) {
            $baseName = preg_replace('/^Module\\s+\\d+\\s*:\\s*/i', '', (string) $module->name) ?? (string) $module->name;
            $expectedName = 'Module ' . (int) $module->order_sequence . ': ' . trim($baseName);

            if ($module->name === $expectedName) {
                continue;
            }

            DB::table('program_modules')
                ->where('id', $module->id)
                ->update(['name' => $expectedName]);
        }
    }

    private function removeProgramModulePrefixes(string $programName): void
    {
        $program = DB::table('programs')->where('name', $programName)->first(['id']);

        if (!$program) {
            return;
        }

        $modules = DB::table('program_modules')
            ->where('program_id', $program->id)
            ->orderBy('order_sequence')
            ->get(['id', 'name']);

        foreach ($modules as $module) {
            $baseName = preg_replace('/^Module\\s+\\d+\\s*:\\s*/i', '', (string) $module->name) ?? (string) $module->name;

            if ($baseName === $module->name) {
                continue;
            }

            DB::table('program_modules')
                ->where('id', $module->id)
                ->update(['name' => trim($baseName)]);
        }
    }
};


