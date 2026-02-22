<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Services\AttendanceService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ManageModuleMentees extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-module-mentees';
    protected static bool $shouldRegisterNavigation = false;
    public Training $training;
    public MentorshipClass $class;
    public ClassModule $module;

    public function mount(Training $training, MentorshipClass $class, ClassModule $module): void {
        $this->training = $training;
        $this->class = $class;
        $this->module = $module->load('programModule');
    }

    public function getTitle(): string {
        return "Attendance — {$this->module->programModule?->name}";
    }

    public function getSubheading(): ?string {
        $service = app(AttendanceService::class);
        $summary = $service->getModuleAttendanceSummary($this->module);

        $confirmed = $summary['confirmed'];
        $total = $summary['total_enrolled'];
        $rate = $summary['rate'];
        $status = $this->module->status_label;

        return "Status: {$status} · {$confirmed}/{$total} confirmed ({$rate}%) · Class: {$this->class->name}";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // View Data — passes variables to the blade view
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Attendance link is derived from class_modules.attendance_token
     * (the module-level QR/link token, not class_sessions).
     * Returns null when the link is inactive or token not yet generated.
     */
    public function getViewData(): array {
        $attendanceLink = ($this->module->attendance_token && $this->module->attendance_link_active) ? route('module.attendance', ['token' => $this->module->attendance_token]) : null;

        return [
            'attendanceLink' => $attendanceLink,
            'attendanceLinkActive' => (bool) $this->module->attendance_link_active,
            'attendanceToken' => $this->module->attendance_token,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Attendance link helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function toggleAttendanceLink(): void {
        if (!$this->module->attendance_token) {
            $this->module->update([
                'attendance_token' => Str::random(32),
                'attendance_link_active' => true,
            ]);
            Notification::make()->success()->title('Attendance link activated')->send();
        } else {
            $this->module->update([
                'attendance_link_active' => !$this->module->attendance_link_active,
            ]);
            $state = $this->module->fresh()->attendance_link_active ? 'activated' : 'deactivated';
            Notification::make()->success()->title("Attendance link {$state}")->send();
        }

        $this->module = $this->module->fresh('programModule');
    }

    public function regenerateAttendanceLink(): void {
        $this->module->update([
            'attendance_token' => Str::random(32),
            'attendance_link_active' => true,
        ]);
        $this->module = $this->module->fresh('programModule');
        Notification::make()->success()->title('Attendance link regenerated')->send();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Header Actions
    // ─────────────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('toggle_attendance_link')
                    ->label(fn() => $this->module->attendance_link_active ? 'Deactivate Attendance Link' : 'Activate Attendance Link')
                    ->icon(fn() => $this->module->attendance_link_active ? 'heroicon-o-link-slash' : 'heroicon-o-link')
                    ->color(fn() => $this->module->attendance_link_active ? 'warning' : 'success')
                    ->action('toggleAttendanceLink'),
                    Actions\Action::make('regenerate_link')
                    ->label('Regenerate Link')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('This will invalidate the current link. All existing QR codes will stop working.')
                    ->action('regenerateAttendanceLink')
                    ->visible(fn() => (bool) $this->module->attendance_token),
                    Actions\Action::make('back')
                    ->label('Back to Modules')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(fn() => MentorshipTrainingResource::getUrl('class-modules', [
                                'training' => $this->training->id,
                                'class' => $this->class->id,
                            ])),
                    Actions\Action::make('mark_all_present')
                    ->label('Mark All Present')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn() => $this->module->status === 'in_progress')
                    ->requiresConfirmation()
                    ->modalHeading('Mark All Mentees as Present')
                    ->modalDescription('This will mark all enrolled mentees as present for this module. Mentees already confirmed are skipped.')
                    ->action(function () {
                        $participants = ClassParticipant::where('mentorship_class_id', $this->class->id)
                                ->whereIn('status', ['enrolled', 'active'])
                                ->get();

                        $marked = 0;
                        foreach ($participants as $participant) {
                            // Skip if already confirmed
                            if ($this->hasAttendance($participant->user_id)) {
                                continue;
                            }

                            ClassAttendance::create([
                                'class_id' => $this->class->id,
                                'class_module_id' => $this->module->id,
                                'session_id' => null,
                                'user_id' => $participant->user_id,
                                'marked_by' => auth()->id(),
                                'marked_at' => now(),
                                'source' => 'manual',
                            ]);

                            // Update mentee progress row to in_progress if not_started
                            MenteeModuleProgress::where('class_participant_id', $participant->id)
                                    ->where('class_module_id', $this->module->id)
                                    ->where('status', 'not_started')
                                    ->update(['status' => 'in_progress', 'started_at' => now()]);

                            $marked++;
                        }

                        Notification::make()->success()->title("{$marked} mentees marked as present")->send();
                    }),
                    Actions\Action::make('export_attendance')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function () {
                        Notification::make()->info()->title('Export coming soon')->send();
                    }),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper: session IDs for this module (for ClassAttendance queries)
    // class_attendances has no class_module_id — we resolve via class_sessions
    // ─────────────────────────────────────────────────────────────────────────

    private function hasAttendance(int $userId): bool {
        return ClassAttendance::where('class_id', $this->class->id)
                        ->where('class_module_id', $this->module->id)
                        ->where('user_id', $userId)
                        ->exists();
    }

    private function attendanceRecord(int $userId): ?ClassAttendance {
        return ClassAttendance::where('class_id', $this->class->id)
                        ->where('class_module_id', $this->module->id)
                        ->where('user_id', $userId)
                        ->latest('marked_at')
                        ->first();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Table
    // ─────────────────────────────────────────────────────────────────────────

    public function table(Table $table): Table {
        return $table
        ->query(ClassParticipant::query()
        ->with([
        'user.cadre',
        'user.facility',
        'moduleProgress' => fn ($q) => $q->where('class_module_id', $this->module->id),
        ])
        ->where('mentorship_class_id', $this->class->id)
        ->whereIn('status', ['enrolled', 'active', 'completed'])
        )
        ->columns([
                    Tables\Columns\TextColumn::make('user_name')
                    ->label('Mentee')
                    ->getStateUsing(fn(ClassParticipant $record) => trim(
                                    collect([$record->user?->first_name, $record->user?->last_name])
                                    ->filter()
                                    ->implode(' ')
                            ) ?: ($record->user?->name ?? '—'))
                    ->searchable(query: function ($query, $search) {
                        $query->whereHas('user', fn($q) => $q
                                        ->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%")
                        );
                    })
                    ->description(fn(ClassParticipant $record) => $record->user?->email)
                    ->weight('medium'),
                    Tables\Columns\TextColumn::make('user.cadre.name')
                    ->label('Cadre')
                    ->placeholder('—')
                    ->toggleable(),
                    Tables\Columns\TextColumn::make('facility_name')
                    ->label('Facility')
                    ->getStateUsing(fn(ClassParticipant $record) => $record->user?->facility?->name ?? '—')
                    ->placeholder('—')
                    ->toggleable(),
                    Tables\Columns\IconColumn::make('attendance_confirmed')
                    ->label('Confirmed')
                    ->getStateUsing(fn(ClassParticipant $record) => $this->hasAttendance($record->user_id))
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                    Tables\Columns\TextColumn::make('confirmed_at')
                    ->label('Confirmed At')
                    ->getStateUsing(fn(ClassParticipant $record) => $this->attendanceRecord($record->user_id)?->marked_at)
                    ->dateTime('d M Y H:i')
                    ->placeholder('Not confirmed')
                    ->color(fn(ClassParticipant $record) => $this->hasAttendance($record->user_id) ? 'success' : 'gray'),
                    Tables\Columns\TextColumn::make('attendance_source')
                    ->label('Source')
                    ->getStateUsing(fn(ClassParticipant $record) => $this->attendanceRecord($record->user_id)?->source)
                    ->badge()
                    ->color(fn(?string $state) => match ($state) {
                                'auto' => 'success',
                                'manual' => 'warning',
                                default => 'gray',
                            })
                    ->placeholder('—'),
                    Tables\Columns\BadgeColumn::make('module_progress_status')
                    ->label('Progress')
                    ->getStateUsing(fn(ClassParticipant $record) => $record->moduleProgress->first()?->status ?? 'not_started')
                    ->colors([
                        'gray' => 'not_started',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'info' => 'exempted',
                    ])
                    ->formatStateUsing(fn(string $state) => match ($state) {
                                'not_started' => 'Not Started',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'exempted' => 'Exempted',
                                default => ucfirst($state),
                            }),
                    Tables\Columns\IconColumn::make('has_recommendation')
                    ->label('Recommendation')
                    ->getStateUsing(fn(ClassParticipant $record) => !empty($record->moduleProgress->first()?->mentor_recommendation))
                    ->boolean()
                    ->trueColor('primary')
                    ->falseColor('gray')
                    ->trueIcon('heroicon-o-document-text')
                    ->falseIcon('heroicon-o-minus'),
                ])
                ->actions([
                    Tables\Actions\ActionGroup::make([
                        // ── Manual Attendance ────────────────────────────────────

                        Tables\Actions\Action::make('mark_present')
                        ->label('Mark Present')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn(ClassParticipant $record) =>
                                $this->module->status === 'in_progress' &&
                                !$this->hasAttendance($record->user_id)
                        )
                        ->action(function (ClassParticipant $record) {
                            ClassAttendance::create([
                                'class_id' => $this->class->id,
                                'class_module_id' => $this->module->id,
                                'session_id' => null,
                                'user_id' => $record->user_id,
                                'marked_by' => auth()->id(),
                                'marked_at' => now(),
                                'source' => 'manual',
                            ]);

                            // Update mentee progress row if still not_started
                            MenteeModuleProgress::where('class_participant_id', $record->id)
                                    ->where('class_module_id', $this->module->id)
                                    ->where('status', 'not_started')
                                    ->update(['status' => 'in_progress', 'started_at' => now()]);

                            Notification::make()->success()->title('Marked as present')->send();
                        }),
                        Tables\Actions\Action::make('remove_attendance')
                        ->label('Remove Attendance')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn(ClassParticipant $record) =>
                                $this->module->status === 'in_progress' &&
                                $this->hasAttendance($record->user_id)
                        )
                        ->requiresConfirmation()
                        ->action(function (ClassParticipant $record) {
                            $sessionIds = $this->moduleSessionIds();
                            ClassAttendance::where('class_id', $this->class->id)
                                    ->where('user_id', $record->user_id)
                                    ->where(function ($q) use ($sessionIds) {
                                        $q->whereIn('session_id', $sessionIds);
                                        if ($sessionIds->isEmpty()) {
                                            $q->orWhereNull('session_id');
                                        }
                                    })
                                    ->delete();

                            Notification::make()->success()->title('Attendance removed')->send();
                        }),
                        // ── Recommendation ───────────────────────────────────────
                        Tables\Actions\Action::make('write_recommendation')
                        ->label('Write Recommendation')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->slideOver()
                        ->modalWidth('2xl')
                        ->modalHeading(fn(ClassParticipant $record) => "Recommendation — {$record->user?->display_name}")
                        ->fillForm(fn(ClassParticipant $record) => [
                            'mentor_recommendation' => $record->moduleProgress->first()?->mentor_recommendation ?? '',
                                ])
                        ->form([
                            Forms\Components\Placeholder::make('module_info')
                            ->label('Module')
                            ->content(fn() => $this->module->programModule?->name),
                            Forms\Components\Textarea::make('mentor_recommendation')
                            ->label('Recommendation / Feedback')
                            ->rows(6)
                            ->placeholder(
                                    "Write your recommendation for this mentee based on their performance in this module.\n\n" .
                                    "Consider:\n• Clinical competencies demonstrated\n• Areas for improvement\n• Suggested next steps\n• Overall readiness"
                            )
                            ->helperText('Visible to the mentee in their progress dashboard after the module is completed.')
                            ->required(),
                            Forms\Components\Select::make('recommendation_type')
                            ->label('Assessment Outcome')
                            ->options([
                                'satisfactory' => '✅ Satisfactory — Ready to progress',
                                'needs_support' => '⚠️ Needs Support — Additional mentoring recommended',
                                'not_competent' => '❌ Not Yet Competent — Repeat module required',
                                'exemplary' => '⭐ Exemplary — Recommend for peer mentoring role',
                            ])
                            ->placeholder('Select outcome...')
                            ->searchable(),
                            Forms\Components\Textarea::make('private_notes')
                            ->label('Private Mentor Notes (not visible to mentee)')
                            ->rows(3)
                            ->placeholder('Internal notes for program coordinators only...'),
                        ])
                        ->action(function (ClassParticipant $record, array $data) {
                            $progress = MenteeModuleProgress::firstOrCreate(
                                    [
                                        'class_participant_id' => $record->id,
                                        'class_module_id' => $this->module->id,
                                    ],
                                    ['status' => 'not_started']
                            );

                            $recommendation = $data['mentor_recommendation'];

                            if (!empty($data['recommendation_type'])) {
                                $typeLabels = [
                                    'satisfactory' => 'OUTCOME: Satisfactory — Ready to progress',
                                    'needs_support' => 'OUTCOME: Needs Support — Additional mentoring recommended',
                                    'not_competent' => 'OUTCOME: Not Yet Competent — Repeat module required',
                                    'exemplary' => 'OUTCOME: Exemplary — Recommend for peer mentoring role',
                                ];
                                $recommendation = ($typeLabels[$data['recommendation_type']] ?? '') . "\n\n" . $recommendation;
                            }

                            $progress->update([
                                'mentor_recommendation' => $recommendation,
                                'notes' => $data['private_notes'] ?? $progress->notes,
                                'recommendation_by' => auth()->id(),
                                'recommendation_written_at' => now(),
                            ]);

                            Notification::make()
                                    ->success()
                                    ->title('Recommendation Saved')
                                    ->body("Recommendation written for {$record->user?->display_name}.")
                                    ->send();
                        }),
                        Tables\Actions\Action::make('view_recommendation')
                        ->label('View Recommendation')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->visible(fn(ClassParticipant $record) =>
                                !empty($record->moduleProgress->first()?->mentor_recommendation)
                        )
                        ->modalHeading(fn(ClassParticipant $record) => "Recommendation — {$record->user?->display_name}")
                        ->modalContent(fn(ClassParticipant $record) => view(
                                        'filament.components.recommendation-view',
                                        [
                                            'progress' => $record->moduleProgress->first(),
                                            'mentee' => $record->user,
                                            'module' => $this->module,
                                        ]
                                ))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),
                    ]),
                ])
                ->filters([
                    Tables\Filters\SelectFilter::make('attendance_status')
                    ->label('Attendance Status')
                    ->options([
                        'confirmed' => 'Confirmed Present',
                        'absent' => 'Not Confirmed',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) {
                            return;
                        }

                        $sessionIds = $this->moduleSessionIds();
                        $confirmedIds = ClassAttendance::where('class_id', $this->class->id)
                                ->where(function ($q) use ($sessionIds) {
                                    $q->whereIn('session_id', $sessionIds);
                                    if ($sessionIds->isEmpty()) {
                                        $q->orWhereNull('session_id');
                                    }
                                })
                                ->pluck('user_id');

                        if ($data['value'] === 'confirmed') {
                            $query->whereIn('user_id', $confirmedIds);
                        } else {
                            $query->whereNotIn('user_id', $confirmedIds);
                        }
                    }),
                ])
                ->emptyStateHeading('No Mentees Enrolled')
                ->emptyStateDescription('Enroll mentees in the class first.')
                ->emptyStateIcon('heroicon-o-users')
                ->striped()
                ->paginated(false);
    }
}
