<?php

namespace App\Filament\Resources\TrainingResource\Pages;

use App\Filament\Resources\TrainingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTraining extends EditRecord
{
    protected static string $resource = TrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('assess')
                ->label('Assess Participants')
                ->icon('heroicon-o-clipboard-document-check')
                ->url(fn (): string => static::getResource()::getUrl('assess', ['record' => $this->record]))
                ->visible(fn (): bool => $this->record->participants()->exists() && $this->record->sessions()->whereHas('objectives')->exists()),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Data should be automatically loaded by Filament relationships
        // This method is here if you need any custom data manipulation
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove nested data that's handled by relationships
        unset($data['sessions']);
        unset($data['participants']);
        
        return $data;
    }
}