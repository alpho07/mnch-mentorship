<?php

namespace App\Filament\Resources\TrainingResource\Pages;

use App\Filament\Resources\TrainingResource;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\Objective;
use App\Models\ParticipantObjectiveResult;
use App\Models\Grade;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class AssessParticipants extends Page
{
    protected static string $resource = TrainingResource::class;
    protected static string $view = 'filament.pages.assess-participants';
    protected static ?string $title = 'Assess Participants';

    public ?array $data = [];
    public Training $training;
    public $participants;
    public $objectives;
    public $grades;

    public function mount(Training $record): void
    {
        $this->training = $record;
        $this->participants = $record->participants()->with('objectiveResults')->get();
        $this->objectives = $record->sessions()
            ->with('objectives')
            ->get()
            ->pluck('objectives')
            ->flatten();
        $this->grades = Grade::all();

        // Initialize form data
        $formData = [];
        foreach ($this->participants as $participant) {
            foreach ($this->objectives as $objective) {
                $existingResult = ParticipantObjectiveResult::where('training_participant_id', $participant->id)
                    ->where('objective_id', $objective->id)
                    ->first();

                $key = "results.{$participant->id}.{$objective->id}";
                $formData[$key] = [
                    'result' => $existingResult?->result ?? 'not_skilled',
                    'grade_id' => $existingResult?->grade_id ?? null,
                    'comments' => $existingResult?->comments ?? '',
                ];
            }
        }

        $this->form->fill($formData);
    }

    public function form(Form $form): Form
    {
        $schema = [];

        foreach ($this->participants as $participant) {
            $participantFields = [];

            foreach ($this->objectives as $objective) {
                $participantFields[] = Forms\Components\Section::make()
                    ->heading($objective->objective_text)
                    ->description("Type: {$objective->type}")
                    ->schema([
                        Forms\Components\Radio::make("results.{$participant->id}.{$objective->id}.result")
                            ->label('Skill Assessment')
                            ->options([
                                'skilled' => 'Skilled',
                                'not_skilled' => 'Not Skilled',
                            ])
                            ->inline()
                            ->required(),

                        Forms\Components\Select::make("results.{$participant->id}.{$objective->id}.grade_id")
                            ->label('Grade')
                            ->options($this->grades->pluck('name', 'id'))
                            ->required(),

                        Forms\Components\Textarea::make("results.{$participant->id}.{$objective->id}.comments")
                            ->label('Comments')
                            ->rows(2),
                    ])
                    ->columns(3)
                    ->compact();
            }

            $schema[] = Forms\Components\Section::make($participant->name)
                ->description("Cadre: {$participant->cadre?->name} | Dept: {$participant->department?->name}")
                ->schema($participantFields)
                ->collapsible();
        }

        return $form
            ->schema($schema)
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data['results'] as $participantId => $objectives) {
            foreach ($objectives as $objectiveId => $result) {
                ParticipantObjectiveResult::updateOrCreate(
                    [
                        'training_participant_id' => $participantId,
                        'objective_id' => $objectiveId,
                    ],
                    [
                        'result' => $result['result'],
                        'grade_id' => $result['grade_id'],
                        'comments' => $result['comments'] ?? null,
                    ]
                );
            }
        }

        // Update participant outcomes based on their results
        foreach ($this->participants as $participant) {
            $this->updateParticipantOutcome($participant);
        }

        Notification::make()
            ->title('Assessments saved successfully')
            ->success()
            ->send();
    }

    protected function updateParticipantOutcome(TrainingParticipant $participant): void
    {
        // Get all results for this participant
        $results = ParticipantObjectiveResult::where('training_participant_id', $participant->id)
            ->with('grade')
            ->get();

        if ($results->isEmpty()) {
            return;
        }

        // Simple logic: if all objectives are passed, participant passes
        // You can customize this logic based on your requirements
        $allPassed = $results->every(function ($result) {
            return $result->grade && strtolower($result->grade->name) === 'pass';
        });

        // Assuming you have a grade for overall outcome
        $outcomeGrade = Grade::where('name', $allPassed ? 'Pass' : 'Fail')->first();
        
        if ($outcomeGrade) {
            $participant->update(['outcome_id' => $outcomeGrade->id]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Back to Training')
                ->url($this->getResource()::getUrl('edit', ['record' => $this->training]))
                ->icon('heroicon-o-arrow-left'),
        ];
    }
}