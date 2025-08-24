<?php
// app/Filament/Resources/MenteeProfileResource/Pages/ListMenteeProfiles.php

namespace App\Filament\Resources\MenteeProfileResource\Pages;

use App\Filament\Resources\MenteeProfileResource;
use App\Filament\Widgets\MenteeStatsOverview;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMenteeProfiles extends ListRecords
{
    protected static string $resource = MenteeProfileResource::class;

    protected function getHeaderWidgets(): array
    {
        // Filament 3: register a widget class
        return [MenteeStatsOverview::class];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $q) =>
                    $q->whereHas('statusLogs', function($qq){
                        $qq->orderByDesc('effective_date')->orderByDesc('id')->limit(1)->where('new_status','active');
                    })->orWhereDoesntHave('statusLogs')),
            'study_leave' => Tab::make('Study Leave')
                ->modifyQueryUsing(fn (Builder $q) =>
                    $q->whereHas('statusLogs', fn($qq) => $qq->orderByDesc('effective_date')->orderByDesc('id')->limit(1)->where('new_status','study_leave'))),
            'attrition' => Tab::make('Attrition')
                ->modifyQueryUsing(fn (Builder $q) =>
                    $q->whereHas('statusLogs', function ($qq) {
                        $attr = \App\Filament\Resources\MenteeProfileResource::attritionStatuses();
                        $qq->orderByDesc('effective_date')->orderByDesc('id')->limit(1)->whereIn('new_status', $attr);
                    })),
            'inactive_6m' => Tab::make('Inactive ≥ 6 mo')
                ->modifyQueryUsing(fn (Builder $q) =>
                    \App\Filament\Resources\MenteeProfileResource::applyInactivityWindow($q, 180)),
        ];
    }
}
