<?php

// app/Filament/Resources/ResourceResource/Pages/EditResource.php
namespace App\Filament\Resources\ResourceResource\Pages;

use App\Filament\Resources\ResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditResource extends EditRecord
{
    protected static string $resource = ResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('View on Frontend')
                ->url(fn () => route('resources.show', $this->getRecord()->slug))
                ->openUrlInNewTab(),

            Actions\Action::make('download')
                ->label('Download File')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn () => route('admin.resources.download', $this->getRecord()))
                ->openUrlInNewTab()
                ->visible(fn () => !empty($this->getRecord()->file_path)),

            Actions\ReplicateAction::make()
                ->excludeAttributes(['slug', 'view_count', 'download_count', 'like_count'])
                ->beforeReplicaSaved(function ($replica, array $data): void {
                    $replica->title = $data['title'] . ' (Copy)';
                    $replica->slug = \Str::slug($replica->title);
                    $replica->status = 'draft';
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Resource updated successfully';
    }

    protected function afterSave(): void
    {
        // Log activity
        activity()
            ->performedOn($this->getRecord())
            ->causedBy(auth()->user())
            ->log('Resource updated');
    }
}