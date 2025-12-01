<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAssessments extends ListRecords {

    protected static string $resource = AssessmentResource::class;

    protected function getHeaderActions(): array {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array {
        return [
            'all' => Tab::make('All Assessments')
                    ->badge(fn() => \App\Models\Assessment::count()),
            'my_assessments' => Tab::make('My Assessments')
                    ->modifyQueryUsing(fn(Builder $query) => $query->where('assessor_id', auth()->id()))
                    ->badge(fn() => \App\Models\Assessment::where('assessor_id', auth()->id())->count()),
            'draft' => Tab::make('Draft')
                    ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'draft'))
                    ->badge(fn() => \App\Models\Assessment::where('status', 'draft')->count())
                    ->badgeColor('secondary'),
            'in_progress' => Tab::make('In Progress')
                    ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'in_progress'))
                    ->badge(fn() => \App\Models\Assessment::where('status', 'in_progress')->count())
                    ->badgeColor('warning'),
            'completed' => Tab::make('Completed')
                    ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'completed'))
                    ->badge(fn() => \App\Models\Assessment::where('status', 'completed')->count())
                    ->badgeColor('success'),
        ];
    }
}
