<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Models\MentorshipClass;
use App\Models\ClassParticipant;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class ManageClassMentees extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-class-mentees';
    protected static bool $shouldRegisterNavigation = false;
    public Training $training;
    public MentorshipClass $class;

    public function mount(Training $training, MentorshipClass $class): void {
        $this->training = $training;
        $this->class = $class->load('training');
    }

    public function getTitle(): string {
        return "Manage Mentees - {$this->class->name}";
    }

    public function getSubheading(): ?string {
        $enrolledCount = $this->class->participants()->count();
        return "{$this->training->facility->name} • {$enrolledCount} mentees enrolled";
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('add_mentees')
                    ->label('Add Mentees')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('mentees')
                        ->label('Select Mentees from Training')
                        ->multiple()
                        ->searchable()
                        ->options(function () {
                            // Get mentees from the training who aren't in this class yet
                            $alreadyEnrolled = $this->class->participants()->pluck('user_id')->toArray();

                            return $this->training->participants()
                                            ->with('user')
                                            ->get()
                                            ->reject(fn($p) => in_array($p->user_id, $alreadyEnrolled))
                                            ->mapWithKeys(fn($p) => [
                                                $p->user_id => $p->user->full_name .
                                                ' (' . ($p->user->department?->name ?? 'No Dept') . ')',
                            ]);
                        })
                        ->required()
                        ->helperText('Select mentees from the training program'),
                    ])
                    ->action(fn(array $data) => $this->addMentees($data)),
                    Actions\Action::make('generate_link')
                    ->label('Generate Enrollment Link')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->action(fn() => $this->generateEnrollmentLink()),
                    Actions\Action::make('back')
                    ->label('Back to Classes')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(fn() => MentorshipTrainingResource::getUrl('classes', ['record' => $this->training])),
        ];
    }

    public function table(Table $table): Table {
        return $table
                        ->query(
                                ClassParticipant::query()
                                ->where('mentorship_class_id', $this->class->id)
                                ->with(['user.department', 'user.cadre'])
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('user.full_name')
                            ->label('Mentee Name')
                            ->searchable(['first_name', 'last_name'])
                            ->weight('bold')
                            ->description(fn($record) =>
                                    ($record->user->department?->name ?? '') . ' • ' .
                                    ($record->user->cadre?->name ?? '')
                            ),
                            Tables\Columns\TextColumn::make('user.phone')
                            ->label('Phone')
                            ->searchable()
                            ->copyable(),
                            Tables\Columns\BadgeColumn::make('status')
                            ->colors([
                                'warning' => 'enrolled',
                                'success' => 'active',
                                'primary' => 'completed',
                                'danger' => 'dropped',
                            ]),
                            Tables\Columns\TextColumn::make('attendance_rate')
                            ->label('Attendance')
                            ->suffix('%')
                            ->badge()
                            ->color(fn($state) => match (true) {
                                        $state >= 80 => 'success',
                                        $state >= 60 => 'warning',
                                        default => 'danger',
                                    }),
                            Tables\Columns\TextColumn::make('enrolled_at')
                            ->label('Enrolled')
                            ->dateTime('M j, Y')
                            ->sortable(),
                        ])
                        ->actions([
                            Tables\Actions\ActionGroup::make([
                                Tables\Actions\Action::make('update_status')
                                ->label('Update Status')
                                ->icon('heroicon-o-pencil')
                                ->form([
                                    Forms\Components\Select::make('status')
                                    ->options([
                                        'enrolled' => 'Enrolled',
                                        'active' => 'Active',
                                        'completed' => 'Completed',
                                        'dropped' => 'Dropped',
                                    ])
                                    ->required(),
                                ])
                                ->action(function ($record, array $data) {
                                    $record->update($data);
                                    Notification::make()->success()->title('Status Updated')->send();
                                }),
                                Tables\Actions\DeleteAction::make()
                                ->label('Remove')
                                ->modalHeading('Remove from Class')
                                ->modalDescription('Remove this mentee from the class?'),
                            ]),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\BulkAction::make('mark_active')
                                ->label('Mark as Active')
                                ->icon('heroicon-o-check')
                                ->color('success')
                                ->action(function ($records) {
                                    $records->each(fn($r) => $r->update(['status' => 'active']));
                                    Notification::make()->success()->title('Updated')->send();
                                }),
                            ]),
                        ])
                        ->defaultSort('enrolled_at', 'desc')
                        ->emptyStateHeading('No Mentees Enrolled')
                        ->emptyStateDescription('Add mentees from the training program to this class.')
                        ->emptyStateIcon('heroicon-o-users');
    }

    private function addMentees(array $data): void {
        $added = 0;

        foreach ($data['mentees'] as $userId) {
            // Check if already enrolled
            if (ClassParticipant::where('mentorship_class_id', $this->class->id)
                            ->where('user_id', $userId)->exists()) {
                continue;
            }

            ClassParticipant::create([
                'mentorship_class_id' => $this->class->id,
                'user_id' => $userId,
                'status' => 'enrolled',
                'enrolled_at' => now(),
            ]);

            $added++;
        }

        Notification::make()
                ->success()
                ->title('Mentees Added')
                ->body("{$added} mentee(s) added to {$this->class->name}")
                ->send();
    }

    private function generateEnrollmentLink(): void {
        // Generate unique token for this class
        if (!$this->class->enrollment_token) {
            $this->class->update([
                'enrollment_token' => Str::random(32),
                'enrollment_link_active' => true,
            ]);
        }

        $link = route('mentee.enroll', ['token' => $this->class->enrollment_token]);

        Notification::make()
                ->success()
                ->title('Enrollment Link Generated')
                ->body('Share this link with mentees to enroll')
                ->actions([
                    \Filament\Notifications\Actions\Action::make('copy')
                    ->button()
                    ->label('Copy Link')
                    ->action(function () use ($link) {
                        // JS will handle copying
                    })
                    ->extraAttributes([
                        'x-on:click' => "
                            navigator.clipboard.writeText('{$link}');
                            \$tooltip('Copied!', { timeout: 2000 });
                        ",
                    ]),
                ])
                ->persistent()
                ->send();
    }
}
