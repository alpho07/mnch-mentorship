<?php

namespace App\Filament\Resources\ResourceResource\Pages;

use App\Filament\Resources\ResourceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateResource extends CreateRecord
{
    protected static string $resource = ResourceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Resource created successfully';
    }

    protected function afterCreate(): void
    {
        // Handle any post-creation tasks
        $record = $this->getRecord();
        
        // Log activity
        activity()
            ->performedOn($record)
            ->causedBy(auth()->user())
            ->log('Resource created');
    }
}