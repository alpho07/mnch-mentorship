<?php
namespace App\Filament\Resources\SerialNumberResource\Pages;

use App\Filament\Resources\SerialNumberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSerialNumber extends CreateRecord
{
    protected static string $resource = SerialNumberResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function afterCreate(): void
    {
        // Create initial tracking record
        $this->getRecord()->trackingHistory()->create([
            'action' => 'created',
            'to_location_id' => $this->getRecord()->current_location_id,
            'to_location_type' => $this->getRecord()->current_location_type,
            'tracked_by' => auth()->id(),
            'notes' => 'Serial number registered in system',
        ]);
    }
}