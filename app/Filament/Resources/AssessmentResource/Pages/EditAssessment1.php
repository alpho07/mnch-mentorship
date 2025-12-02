<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Services\DynamicScoringService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditAssessment1 extends EditRecord
{
    protected static string $resource = AssessmentResource::class;

    // Enable autosave every 30 seconds
    protected static bool $canAutosave = true;
    protected static int $autosaveInterval = 30000; // milliseconds

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_summary')
                ->label('View Summary')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn () => static::getResource()::getUrl('view', ['record' => $this->record])),

            Actions\Action::make('complete_assessment')
                ->label('Complete Assessment')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status !== 'completed')
                ->requiresConfirmation()
                ->modalHeading('Complete Assessment')
                ->modalDescription('Are you sure you want to mark this assessment as completed?')
                ->action(function () {
                    $this->record->complete();
                    
                    Notification::make()
                        ->title('Assessment completed successfully')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('recalculate_scores')
                ->label('Recalculate Scores')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $service = app(DynamicScoringService::class);
                    $sections = \App\Models\AssessmentSection::where('is_scored', true)->get();
                    
                    foreach ($sections as $section) {
                        $service->recalculateSectionScore($this->record->id, $section->id);
                    }
                    
                    Notification::make()
                        ->title('Scores recalculated successfully')
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['county_filter']);
        unset($data['facility_info']);
        
        $this->saveQuestionResponses($data);
        $this->saveHumanResourceResponses($data);
        $this->saveHealthProductsResponses($data);
        
        return $data;
    }

    protected function saveHumanResourceResponses(array $data): void
    {
        $cadres = \App\Models\MainCadre::where('is_active', true)->get();
        
        foreach ($cadres as $cadre) {
            $prefix = "hr_{$cadre->id}_";
            
            // Check if any HR field for this cadre exists in data
            if (!isset($data["{$prefix}total_in_facility"])) {
                continue;
            }
            
            \App\Models\HumanResourceResponse::updateOrCreate(
                [
                    'assessment_id' => $this->record->id,
                    'cadre_id' => $cadre->id,
                ],
                [
                    'total_in_facility' => (int)($data["{$prefix}total_in_facility"] ?? 0),
                    'etat_plus' => (int)($data["{$prefix}etat_plus"] ?? 0),
                    'comprehensive_newborn_care' => (int)($data["{$prefix}comprehensive_newborn_care"] ?? 0),
                    'imnci' => (int)($data["{$prefix}imnci"] ?? 0),
                    'type_1_diabetes' => (int)($data["{$prefix}type_1_diabetes"] ?? 0),
                    'essential_newborn_care' => (int)($data["{$prefix}essential_newborn_care"] ?? 0),
                ]
            );
        }
    }

    protected function saveQuestionResponses(array $data): void
    {
        $infrastructureId = \App\Models\AssessmentSection::where('code', 'infrastructure')->value('id');
        if ($infrastructureId) {
            \App\Services\DynamicFormBuilder::saveResponses($this->record->id, $infrastructureId, $data);
        }

        $skillsLabId = \App\Models\AssessmentSection::where('code', 'skills_lab')->value('id');
        if ($skillsLabId) {
            \App\Services\DynamicFormBuilder::saveResponses($this->record->id, $skillsLabId, $data);
        }

        $infoSystemsId = \App\Models\AssessmentSection::where('code', 'information_systems')->value('id');
        if ($infoSystemsId) {
            \App\Services\DynamicFormBuilder::saveResponses($this->record->id, $infoSystemsId, $data);
        }

        $qualityId = \App\Models\AssessmentSection::where('code', 'quality_of_care')->value('id');
        if ($qualityId) {
            \App\Services\DynamicFormBuilder::saveResponses($this->record->id, $qualityId, $data);
        }
    }

    protected function saveHealthProductsResponses(array $data): void
    {
        $departments = \App\Models\AssessmentDepartment::where('is_active', true)->get();
        
        foreach ($departments as $department) {
            $commodities = \App\Models\Commodity::where('is_active', true)
                ->whereHas('applicableDepartments', function ($query) use ($department) {
                    $query->where('assessment_department_id', $department->id);
                })
                ->get();
            
            foreach ($commodities as $commodity) {
                $fieldName = "commodity_{$department->id}_{$commodity->id}";
                
                if (!isset($data[$fieldName])) {
                    continue;
                }
                
                $available = (bool) $data[$fieldName];
                
                \App\Models\AssessmentCommodityResponse::updateOrCreate(
                    [
                        'assessment_id' => $this->record->id,
                        'commodity_id' => $commodity->id,
                        'assessment_department_id' => $department->id,
                    ],
                    [
                        'available' => $available,
                        'score' => $available ? 1 : 0,
                    ]
                );
            }
            
            app(\App\Services\CommodityScoringService::class)
                ->recalculateDepartmentScore($this->record->id, $department->id);
        }
    }

    protected function afterSave(): void
    {
        if (!request()->header('X-Livewire-Autosave')) {
            Notification::make()
                ->title('Progress saved')
                ->success()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Save Progress')
                ->icon('heroicon-o-document-check'),
            
            Actions\Action::make('complete')
                ->label('Submit & Complete Assessment')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Complete Assessment')
                ->modalDescription('Are you sure you want to submit and mark this assessment as completed?')
                ->action(function () {
                    $this->save();
                    $this->record->complete();
                    
                    Notification::make()
                        ->title('Assessment completed successfully!')
                        ->body('You can now view the summary or download the report.')
                        ->success()
                        ->send();
                    
                    return redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),
            
            $this->getCancelFormAction(),
        ];
    }
}