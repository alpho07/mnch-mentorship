<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateAssessment1 extends CreateRecord {

    protected static string $resource = AssessmentResource::class;
    // Use simple form - wizard handled in resource but only Step 1 shows
    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array {
        $data['status'] = 'draft';
        $data['section_progress'] = [];

        // Auto-populate assessor from auth user
        $data['assessor_id'] = auth()->id();
        $data['assessor_name'] = auth()->user()->name ?? '';
        $data['assessor_contact'] = auth()->user()->email ?? auth()->user()->phone ?? '';

        unset($data['county_filter']);
        unset($data['facility_info']);

        return $data;
    }

    protected function afterCreate(): void {
        Notification::make()
                ->title('Facility information saved')
                ->body('Continue filling the assessment sections.')
                ->success()
                ->send();
    }

    protected function getRedirectUrl(): string {
        // Redirect to edit page immediately
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string {
        return null;
    }
}
