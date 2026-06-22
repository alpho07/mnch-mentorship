<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassModule;
use App\Models\ClassModuleActivityParticipant;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Services\EmoncNotificationService;
use App\Services\QuizAttemptService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ReviewModuleMentee extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.review-module-mentee';

    protected static bool $shouldRegisterNavigation = false;

    public Training $training;

    public MentorshipClass $class;

    public ClassModule $module;

    public ClassParticipant $participant;

    public ?MenteeModuleProgress $progress = null;

    public array $preTestStatus = [];

    public array $postTestStatus = [];

    public array $activities = [];

    public bool $isEmonc = false;

    // Form data property required by InteractsWithForms / statePath('data')
    public ?array $data = [];

    public function mount(Training $training, MentorshipClass $class, ClassModule $module, ClassParticipant $participant): void
    {
        $this->training = $training->load(['facility', 'program', 'mentor']);
        $this->class = $class;
        $this->module = $module->load(['programModule.activities', 'programModule.quizzes.questions.options']);
        $this->participant = $participant->load(['user.facility.subcounty.county', 'user.cadre', 'user.department']);

        $this->progress = MenteeModuleProgress::with(['preTestAttempt.responses.option', 'postTestAttempt.responses.option'])
            ->where('class_participant_id', $this->participant->id)
            ->where('class_module_id', $this->module->id)
            ->first();

        $this->isEmonc = $this->detectEmonc();

        $this->loadQuizStatuses();
        $this->loadActivities();

        $this->form->fill([
            'video_review_status' => $this->progress?->video_review_status ?? 'pending',
            'video_review_notes' => $this->progress?->video_review_notes ?? '',
        ]);
    }

    public function getTitle(): string
    {
        return "Review — {$this->participant->user?->full_name}";
    }

    public function getSubheading(): ?string
    {
        return "{$this->module->programModule?->name} · {$this->class->name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Module Mentees')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('module-mentees', [
                    'training' => $this->training->id,
                    'class' => $this->class->id,
                    'module' => $this->module->id,
                ])),
        ];
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('video_review_status')
                    ->label('Video Review Outcome')
                    ->options([
                        'pending' => 'Pending',
                        'passed' => 'Passed',
                        'failed' => 'Failed',
                    ])
                    ->required()
                    ->native(true),
                Forms\Components\Textarea::make('video_review_notes')
                    ->label('Review Notes')
                    ->rows(4)
                    ->maxLength(2000)
                    ->placeholder('Feedback for the mentee...'),
            ])
            ->statePath('data');
    }

    public function saveReview(): void
    {
        $data = $this->form->getState();

        $progress = MenteeModuleProgress::updateOrCreate(
            [
                'class_participant_id' => $this->participant->id,
                'class_module_id' => $this->module->id,
            ],
            [
                'status' => $this->progress?->status ?? 'in_progress',
                'started_at' => $this->progress?->started_at ?? now(),
            ]
        );

        $progress->recordVideoReview(
            $data['video_review_status'],
            $data['video_review_notes'],
            auth()->id()
        );

        app(EmoncNotificationService::class)->videoReviewed($progress->fresh());

        $this->progress = $progress->fresh(['preTestAttempt.responses.option', 'postTestAttempt.responses.option']);

        Notification::make()
            ->success()
            ->title('Review Saved')
            ->body("Evaluation saved for {$this->participant->user?->full_name}.")
            ->send();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Data loading helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function detectEmonc(): bool
    {
        $name = strtolower($this->training->program?->name ?? '');

        return str_contains($name, 'maternal') && str_contains($name, 'emonc');
    }

    private function loadQuizStatuses(): void
    {
        $service = app(QuizAttemptService::class);
        $mentee = $this->participant->user;

        $preTestQuizzes = $this->module->programModule?->quizzes?->filter(fn ($q) => $q->isPreTest()) ?? collect();
        $postTestQuizzes = $this->module->programModule?->quizzes?->filter(fn ($q) => $q->isPostTest()) ?? collect();

        $this->preTestStatus = $this->buildQuizStatus($mentee, $preTestQuizzes, $service, 'pre_test_attempt_id');
        $this->postTestStatus = $this->buildQuizStatus($mentee, $postTestQuizzes, $service, 'post_test_attempt_id');
    }

    private function buildQuizStatus(?\App\Models\User $mentee, Collection $quizzes, QuizAttemptService $service, string $attemptIdColumn): array
    {
        if ($quizzes->isEmpty() || ! $mentee) {
            return ['exists' => false, 'passed' => true, 'completed' => true, 'attempt' => null, 'quiz' => null];
        }

        $quiz = $quizzes->first();
        $latestCompleted = $service->getLatestAttempts($mentee, $quiz);
        $key = $attemptIdColumn === 'pre_test_attempt_id' ? 'pre_test' : 'post_test';
        $latest = $latestCompleted[$key];

        if ($latest) {
            return [
                'exists' => true,
                'passed' => $latest->isPassed(),
                'completed' => true,
                'attempt' => $latest,
                'quiz' => $quiz,
            ];
        }

        return [
            'exists' => true,
            'passed' => false,
            'completed' => false,
            'attempt' => null,
            'quiz' => $quiz,
        ];
    }

    private function loadActivities(): void
    {
        $activityIds = $this->module->programModule?->activities?->pluck('id')->toArray() ?? [];
        $enrolled = [];
        $completed = [];

        if (! empty($activityIds)) {
            $enrolled = ClassModuleActivityParticipant::where('class_module_id', $this->module->id)
                ->where('class_participant_id', $this->participant->id)
                ->whereIn('activity_id', $activityIds)
                ->pluck('activity_id')
                ->toArray();

            $completed = ClassModuleActivityParticipant::where('class_module_id', $this->module->id)
                ->where('class_participant_id', $this->participant->id)
                ->whereIn('activity_id', $activityIds)
                ->where('status', 'completed')
                ->pluck('activity_id')
                ->toArray();
        }

        $this->activities = $this->module->programModule?->activities?->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'enrolled' => in_array($a->id, $enrolled),
            'completed' => in_array($a->id, $completed),
        ])->toArray() ?? [];
    }
}
