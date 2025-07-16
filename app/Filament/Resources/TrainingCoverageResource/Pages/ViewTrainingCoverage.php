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
            Actions\EditAction::make(),
        ];
    }
}
