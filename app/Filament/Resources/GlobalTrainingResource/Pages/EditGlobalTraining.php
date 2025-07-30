<?php

namespace App\Filament\Resources\GlobalTrainingResource\Pages;

use App\Filament\Resources\GlobalTrainingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditGlobalTraining extends EditRecord
{
    protected static string $resource = GlobalTrainingResource::class;

    protected static ?string $title = 'Edit Global Training';

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->color('info'),
            Actions\Action::make('manage_participants')
                ->label('Manage Participants')
                ->icon('heroicon-o-users')
                ->color('success')
                ->url(fn () => static::getResource()::getUrl('participants', ['record' => $this->record])),
            Actions\DeleteAction::make()
                ->requiresConfirmation(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Global Training Updated')
            ->body('The global training has been updated successfully.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure type is always set for global training
        $data['type'] = 'global_training';

        return $data;
    }

    protected function afterSave(): void
    {
        // Handle any post-save logic here
        // For example, sync relationships that might have changed

        if (isset($this->data['programs'])) {
            $this->record->programs()->sync($this->data['programs']);
        }

        if (isset($this->data['modules'])) {
            $this->record->modules()->sync($this->data['modules']);
        }

        if (isset($this->data['methodologies'])) {
            $this->record->methodologies()->sync($this->data['methodologies']);
        }
    }
}
