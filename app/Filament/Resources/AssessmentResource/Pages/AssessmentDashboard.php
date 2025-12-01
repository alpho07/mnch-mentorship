<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use Filament\Actions;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class AssessmentDashboard extends Page {

    protected static string $resource = AssessmentResource::class;
    // Tell Filament which Blade view to use
    protected static string $view = 'filament.pages.assessment.dashboard';
    // Register a named infolist
    protected static ?string $infolist = 'assessment_summary';
    public Assessment $record;

    public function mount(int|string $record): void {

        $this->record = Assessment::findOrFail($this->record->id);

        // Ensure section_progress array exists
        if (!$this->record->section_progress) {
            $this->record->section_progress = [
                'facility_assessor' => true,
                'infrastructure' => false,
                'skills_lab' => false,
                'human_resources' => false,
                'health_products' => false,
                'information_systems' => false,
                'quality_of_care' => false,
            ];
            $this->record->save();
        }
    }

    /**
     * Named infolist for the page header
     *
     * Filament 3 requires:
     * getInfolist(string $name): ?Infolist
     */
    public function getInfolist(string $name): ?Infolist {
        if ($name !== 'assessment_summary') {
            return null;
        }

        return Infolist::make()
                    ->record($this->record)
                        ->schema([
                            Section::make('Assessment Details')
                            ->schema([
                                TextEntry::make('facility.name')->label('Facility'),
                                TextEntry::make('assessment_type')->label('Type'),
                                TextEntry::make('assessment_date')->label('Date'),
                                TextEntry::make('assessor_name')->label('Assessor'),
                            ]),
        ]);
    }

    /**
     * Submit the entire assessment
     */
    public function submitAssessment() {
        $progress = $this->record->section_progress;

        if (in_array(false, $progress, true)) {
            Notification::make()
                    ->warning()
                    ->title('Cannot submit')
                    ->body('Please complete all sections before submitting the assessment.')
                    ->send();
            return;
        }

        $this->record->status = 'completed';
        $this->record->completed_at = now();
        $this->record->completed_by = auth()->id();
        $this->record->save();

        Notification::make()
                ->success()
                ->title('Assessment submitted')
                ->body('The assessment has been successfully completed.')
                ->send();

        return redirect(AssessmentResource::getUrl());
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('Submit Assessment')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn() => $this->canSubmit())
                    ->action('submitAssessment'),
        ];
    }

    private function canSubmit(): bool {
        $progress = $this->record->section_progress ?? [];
        return $progress && !in_array(false, $progress, true);
    }

    /**
     * Dashboard data for Blade template
     */
    protected function getViewData(): array {
        return [
            'record' => $this->record,
            'sections' => [
                [
                    'key' => 'facility_assessor',
                    'label' => 'Facility & Assessor',
                    'route' => null,
                    'done' => $this->record->section_progress['facility_assessor'] ?? false,
                ],
                [
                    'key' => 'infrastructure',
                    'label' => 'Infrastructure',
                    'route' => AssessmentResource::getUrl('edit-infrastructure', ['record' => $this->record->id]),
                    'done' => $this->record->section_progress['infrastructure'] ?? false,
                ],
                [
                    'key' => 'skills_lab',
                    'label' => 'Skills Lab',
                    'route' => AssessmentResource::getUrl('edit-skills-lab', ['record' => $this->record->id]),
                    'done' => $this->record->section_progress['skills_lab'] ?? false,
                ],
                [
                    'key' => 'human_resources',
                    'label' => 'Human Resources',
                    'route' => AssessmentResource::getUrl('edit-human-resources', ['record' => $this->record->id]),
                    'done' => $this->record->section_progress['human_resources'] ?? false,
                ],
                [
                    'key' => 'health_products',
                    'label' => 'Health Products',
                    'route' => AssessmentResource::getUrl('edit-health-products', ['record' => $this->record->id]),
                    'done' => $this->record->section_progress['health_products'] ?? false,
                ],
                [
                    'key' => 'information_systems',
                    'label' => 'Information Systems',
                    'route' => AssessmentResource::getUrl('edit-information-systems', ['record' => $this->record->id]),
                    'done' => $this->record->section_progress['information_systems'] ?? false,
                ],
                [
                    'key' => 'quality_of_care',
                    'label' => 'Quality of Care',
                    'route' => AssessmentResource::getUrl('edit-quality-of-care', ['record' => $this->record->id]),
                    'done' => $this->record->section_progress['quality_of_care'] ?? false,
                ],
            ],
        ];
    }
}
