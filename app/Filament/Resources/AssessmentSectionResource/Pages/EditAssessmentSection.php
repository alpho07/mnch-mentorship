<?php

namespace App\Filament\Resources\AssessmentSectionResource\Pages;

use App\Filament\Resources\AssessmentSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAssessmentSection extends EditRecord
{
    protected static string $resource = AssessmentSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
