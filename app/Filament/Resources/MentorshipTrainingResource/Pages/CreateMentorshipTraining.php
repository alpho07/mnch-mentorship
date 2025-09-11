<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\FacilityAssessment;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateMentorshipTraining extends CreateRecord
{
    protected static string $resource = MentorshipTrainingResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'facility_mentorship';
        
        // Check facility assessment
        if (isset($data['facility_id'])) {
            $assessment = FacilityAssessment::where('facility_id', $data['facility_id'])
                ->where('status', 'approved')
                ->where('next_assessment_due', '>', now())
                ->latest()
                ->first();
                
            if (!$assessment) {
                throw new \Exception('Facility assessment must be completed and approved before creating mentorship.');
            }
        }
        
        // Validate assessment weights
        if (isset($data['assessment_category_settings']) && !empty($data['assessment_category_settings'])) {
            $totalWeight = collect($data['assessment_category_settings'])->sum('weight_percentage');
            if (abs($totalWeight - 100.0) >= 0.1) {
                throw new \Exception("Assessment category weights must total 100%. Current total: {$totalWeight}%");
            }
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Only handle assessment categories - let Filament handle the other relationships automatically
        if (isset($this->data['assessment_category_settings'])) {
            $this->attachAssessmentCategories();
        }
    }

    private function attachAssessmentCategories(): void
    {
        $settings = $this->data['assessment_category_settings'] ?? [];
        $attachData = [];
        
        foreach ($settings as $setting) {
            if (!($setting['is_active'] ?? true)) continue;
            
            $attachData[$setting['assessment_category_id']] = [
                'weight_percentage' => $setting['weight_percentage'],
                'pass_threshold' => $setting['pass_threshold'] ?? 70.00,
                'is_required' => $setting['is_required'] ?? true,
                'order_sequence' => $setting['order_sequence'] ?? 1,
                'is_active' => true,
            ];
        }
        
        if (!empty($attachData)) {
            $this->record->assessmentCategories()->sync($attachData);
        }
    }

    protected function getCreatedNotification(): ?Notification
    {
        $categoriesCount = $this->record->assessmentCategories()->count();
        $programsCount = $this->record->programs()->count();
        
        return Notification::make()
            ->success()
            ->title('Mentorship Created')
            ->body("Mentorship created with {$categoriesCount} assessment categories and {$programsCount} programs. You can now add mentees and begin assessments.")
            ->actions([
                \Filament\Notifications\Actions\Action::make('add_mentees')
                    ->button()
                    ->url($this->getResource()::getUrl('mentees', ['record' => $this->record])),
                \Filament\Notifications\Actions\Action::make('view_assessments')
                    ->button()
                    ->url($this->getResource()::getUrl('assessments', ['record' => $this->record])),
            ]);
    }
    
      public function getTitle(): string
    {
        return 'New Mentorship';
    }

}