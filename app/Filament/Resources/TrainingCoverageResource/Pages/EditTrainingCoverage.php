<?php

namespace App\Filament\Resources\TrainingCoverageResource\Pages;

use App\Filament\Resources\TrainingCoverageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTrainingCoverage extends EditRecord
{
    protected static string $resource = TrainingCoverageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
