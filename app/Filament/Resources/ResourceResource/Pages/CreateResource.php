<?php

namespace App\Filament\Resources\ResourceResource\Pages;

use App\Filament\Resources\ResourceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

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

    protected function handleRecordCreation(array $data): Model
    {
        $record = static::getModel()::create($data);

        // Log the creation with custom properties
        activity()
            ->performedOn($record)
            ->causedBy(auth()->user())
            ->withProperties([
                'category' => $record->category?->name,
                'type' => $record->resourceType?->name,
                'visibility' => $record->visibility,
            ])
            ->log("Created resource: {$record->title}");

        return $record;
    }
}