<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Filament\Resources\AssessmentResource\Traits\HasSectionNavigation;
use App\Models\AssessmentSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSkillsLab extends EditRecord {

    use HasSectionNavigation;

    protected static string $resource = AssessmentResource::class;

    public function mount(int|string $record): void {
        parent::mount($record);
        $this->form->fill($this->loadSavedResponses());
    }

    protected function loadSavedResponses(): array {
        $responses = \App\Models\AssessmentQuestionResponse::where('assessment_id', $this->record->id)->get();

        $data = [];

        foreach ($responses as $resp) {
            $fieldName = "question_response_{$resp->assessment_question_id}";
            $data[$fieldName] = $resp->response_value;

            if ($resp->explanation) {
                $data[$fieldName . "_explanation"] = $resp->explanation;
            }

            if ($resp->metadata) {
                foreach ($resp->metadata as $key => $value) {
                    if ($key === 'positive_count') {
                        $data["{$fieldName}_positive_count"] = $value;
                    } elseif ($key === 'sample_size') {
                        $data["{$fieldName}_sample_size"] = $value;
                    } elseif ($key === 'calculated_proportion') {
                        $data["{$fieldName}_proportion"] = $value;
                    } else {
                        $data["{$fieldName}_{$key}"] = $value;
                    }
                }
            }
        }

        return $data;
    }

    public function form(Form $form): Form {
        $sectionId = AssessmentSection::where('code', 'skills_lab')->value('id');

        return $form->schema([
                            Forms\Components\Section::make('Skills Lab Assessment')
                            ->description('Training facilities and equipment availability')
                            ->schema(
                                    \App\Services\DynamicFormBuilder::buildForSection(
                                            $sectionId,
                                            $this->record->id
                                    )
                            )
                            ->columns(1)
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array {
        $sectionId = AssessmentSection::where('code', 'skills_lab')->value('id');

        \App\Services\DynamicFormBuilder::saveResponses(
                $this->record->id,
                $sectionId,
                $data
        );

        \App\Services\DynamicScoringService::recalculateSectionScore(
                $this->record->id,
                $sectionId
        );

        $progress = $this->record->section_progress ?? [];
        $progress['skills_lab'] = true;
        $this->record->section_progress = $progress;
        $this->record->save();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'question_response_')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    protected function getCurrentSectionKey(): string {
        return 'skills_lab';
    }

    protected function getSavedNotification(): ?Notification {
        $nextSection = $this->getNextSection();

        return Notification::make()
                        ->title('Skills Lab section saved successfully')
                        ->body($nextSection ? "Moving to: {$nextSection}" : "Returning to dashboard")
                        ->success()
                        ->duration(3000);
    }

    protected function getNextSection(): ?string {
        $sections = $this->getAllSections();
        $currentIndex = array_search('skills_lab', array_keys($sections));
        $sectionKeys = array_keys($sections);

        for ($i = $currentIndex + 1; $i < count($sectionKeys); $i++) {
            if (!$sections[$sectionKeys[$i]]['done']) {
                return $sections[$sectionKeys[$i]]['label'];
            }
        }

        return null;
    }

    public function getTitle(): string {
        return "Skills Lab - {$this->record->facility->name}";
    }

    public static function getNavigationLabel(): string {
        return 'Skills Lab';
    }
}
