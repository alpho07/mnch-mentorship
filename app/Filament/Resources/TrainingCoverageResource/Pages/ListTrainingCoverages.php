<?php

namespace App\Filament\Resources\TrainingCoverageResource\Pages;

use App\Filament\Resources\TrainingCoverageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTrainingCoverages extends ListRecords
{
    protected static string $resource = TrainingCoverageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('dashboard')
                ->label('View Dashboard')
                ->icon('heroicon-o-chart-bar')
                ->url(fn (): string => '/admin/training-dashboard') // Direct URL to standalone page
                ->color('primary')
                ->openUrlInNewTab(false),
        ];
    }
}