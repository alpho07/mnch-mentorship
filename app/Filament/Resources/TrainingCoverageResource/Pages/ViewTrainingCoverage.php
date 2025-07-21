<?php

namespace App\Filament\Resources\TrainingCoverageResource\Pages;

use App\Filament\Resources\TrainingCoverageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTrainingCoverage extends ViewRecord
{
    protected static string $resource = TrainingCoverageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_list')
                ->label('Back to Coverage List')
                ->icon('heroicon-o-arrow-left')
                ->url(fn (): string => static::getResource()::getUrl('index'))
                ->color('gray'),
                
            Actions\Action::make('dashboard')
                ->label('View Dashboard')
                ->icon('heroicon-o-chart-bar')
                ->url(fn (): string => '/admin/training-dashboard') // Direct URL to standalone page
                ->color('primary')
                ->openUrlInNewTab(false),
        ];
    }
}