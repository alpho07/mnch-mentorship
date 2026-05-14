<?php

namespace App\Filament\Forms\Components;

use App\Models\Program;
use App\Models\ProgramModule;
use Filament\Forms\Components\Field;
use Illuminate\Support\Collection;

class ProgramPicker extends Field
{
    protected string $view = 'filament.forms.components.program-picker';

    public function getPrograms(): Collection
    {
        $programs = Program::orderBy('name')->get();

        // Load active ProgramModules in one query, then group by program
        $modulesByProgram = ProgramModule::whereIn('program_id', $programs->pluck('id'))
            ->where('is_active', true)
            ->orderBy('order_sequence')
            ->get(['id', 'name', 'program_id', 'order_sequence'])
            ->groupBy('program_id');

        return $programs->each(
            fn ($p) => $p->setRelation('programModules', $modulesByProgram->get($p->id, collect()))
        );
    }
}
