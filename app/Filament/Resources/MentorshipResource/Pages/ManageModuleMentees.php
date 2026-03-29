<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Models\MentorshipClass;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;

class ManageModuleMentees extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-module-mentees';
    protected static bool $shouldRegisterNavigation = false;
    
    public Training $training;
    public MentorshipClass $class;
    public ClassModule $module;
    public ?string $attendanceLink = null;

    public function mount(Training $training, MentorshipClass $class, ClassModule $module): void
    {
        $this->training = $training;
        $this->class = $class;
        $this->module = $module->load(['programModule', 'mentorshipClass']);
        
        // Load existing attendance link if available
        if ($this->module->attendance_token && $this->module->attendance_link_active) {
            $this->attendanceLink = route('module.attend', ['token' => $this->module->attendance_token]);
        }
    }

    public function getTitle(): string
    {
        return "Manage Module Mentees - {$this->module->programModule->name}";
    }

    public function getSubheading(): ?string
    {
        $enrolledCount = MenteeModuleProgress::where('class_module_id', $this->module->id)
            ->whereIn('status', ['not_started', 'in_progress', 'completed'])
            ->count();
        return "{$this->class->name} • {$enrolledCount} mentees enrolled";
    }

    protected function getHeaderActions(): array
    {
        $hasEnrolledMentees = MenteeModuleProgress::where('class_module_id', $this->module->id)
            ->whereIn('status', ['not_started', 'in_progress', 'completed'])
            ->count() > 0;
        
        return [
            Actions\Action::make('add_mentees')
                ->label('Add Mentees to Module')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->slideOver()
                ->modalWidth('3xl')
                ->form([
                    Forms\Components\Section::make('Select Mentees')
                        ->description('Select mentees from this class. Already enrolled mentees are pre-selected.')
                        ->schema([
                            Forms\Components\TextInput::make('search')
                                ->label('Search')
                                ->placeholder('Search by name, phone, or email...')
                                ->live(debounce: 300)
                                ->prefixIcon('heroicon-o-magnifying-glass')
                                ->afterStateUpdated(fn() => null),
                            
                            Forms\Components\CheckboxList::make('selected_participants')
                                ->label('Class Participants')
                                ->options(function (Forms\Get $get) {
                                    $search = $get('search');
                                    
                                    $query = ClassParticipant::where('mentorship_class_id', $this->class->id)
                                        ->with(['user.facility', 'user.department']);
                                    
                                    if ($search) {
                                        $query->whereHas('user', function ($q) use ($search) {
                                            $q->where(function ($subQ) use ($search) {
                                                $subQ->where('first_name', 'like', "%{$search}%")
                                                    ->orWhere('last_name', 'like', "%{$search}%")
                                                    ->orWhere('phone', 'like', "%{$search}%")
                                                    ->orWhere('email', 'like', "%{$search}%");
                                            });
                                        });
                                    }
                                    
                                    return $query
                                        ->get()
                                        ->mapWithKeys(fn($participant) => [
                                            $participant->id => $participant->user->full_name . 
                                                ' - ' . $participant->user->phone .
                                                ($participant->user->facility ? ' (' . $participant->user->facility->name . ')' : ''),
                                        ]);
                                })
                                ->default(function () {
                                    return MenteeModuleProgress::where('class_module_id', $this->module->id)
                                        ->whereIn('status', ['not_started', 'in_progress', 'completed'])
                                        ->pluck('class_participant_id')
                                        ->toArray();
                                })
                                ->bulkToggleable()
                                ->columns(1)
                                ->gridDirection('row')
                                ->helperText('Already enrolled mentees are checked. Uncheck to remove, check to add new mentees.')
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(function (array $data) {
                    $selectedParticipants = $data['selected_participants'] ?? [];
                    $currentEnrolledIds = MenteeModuleProgress::where('class_module_id', $this->module->id)
                        ->whereIn('status', ['not_started', 'in_progress', 'completed'])
                        ->pluck('class_participant_id')
                        ->toArray();
                    
                    $participantsToAdd = array_diff($selectedParticipants, $currentEnrolledIds);
                    $participantsToRemove = array_diff($currentEnrolledIds, $selectedParticipants);
                    
                    DB::transaction(function () use ($participantsToAdd, $participantsToRemove) {
                        // Remove participants
                        if (!empty($participantsToRemove)) {
                            MenteeModuleProgress::where('class_module_id', $this->module->id)
                                ->whereIn('class_participant_id', $participantsToRemove)
                                ->delete();
                        }
                        
                        // Add new participants
                        $exemptedCount = 0;
                        foreach ($participantsToAdd as $participantId) {
                            $participant = ClassParticipant::find($participantId);
                            $isExempted = $this->hasUserCompletedModule($participant->user_id, $this->module->program_module_id);
                            
                            if ($isExempted) $exemptedCount++;
                            
                            MenteeModuleProgress::create([
                                'class_participant_id' => $participantId,
                                'class_module_id' => $this->module->id,
                                'status' => $isExempted ? 'exempted' : 'not_started',
                                'completed_in_previous_class' => $isExempted,
                                'exempted_at' => $isExempted ? now() : null,
                            ]);
                        }
                    });
                    
                    $messages = [];
                    if (count($participantsToAdd) > 0) $messages[] = count($participantsToAdd) . ' mentee(s) added';
                    if (count($participantsToRemove) > 0) $messages[] = count($participantsToRemove) . ' mentee(s) removed';
                    
                    Notification::make()
                        ->success()
                        ->title('Module Mentees Updated')
                        ->body(implode(' • ', $messages))
                        ->send();
                }),
                
            Actions\Action::make('generate_attendance_link')
                ->label('Generate Attendance Link')
                ->icon('heroicon-o-link')
                ->color('primary')
                ->visible($hasEnrolledMentees && !$this->module->attendance_link_active)
                ->action(function () {
                    $this->generateAttendanceLink();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                MenteeModuleProgress::query()
                    ->where('class_module_id', $this->module->id)
                    ->with(['classParticipant.user.department', 'classParticipant.user.cadre'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('classParticipant.user.full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('classParticipant.user.phone')
                    ->label('Phone')
                    ->searchable(),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'not_started',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'info' => 'exempted',
                    ]),
                
                Tables\Columns\IconColumn::make('completed_in_previous_class')
                    ->label('Previously Completed')
                    ->boolean()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'not_started' => 'Not Started',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'exempted' => 'Exempted',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_completed')
                    ->label('Mark Completed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MenteeModuleProgress $record) => $record->status !== 'completed')
                    ->action(function (MenteeModuleProgress $record) {
                        $record->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                        ]);
                        
                        Notification::make()
                            ->success()
                            ->title('Marked as Completed')
                            ->send();
                    }),
                    
                Tables\Actions\DeleteAction::make()
                    ->label('Remove'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_all_completed')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'status' => 'completed',
                                    'completed_at' => now(),
                                ]);
                            }
                            
                            Notification::make()
                                ->success()
                                ->title('Marked as Completed')
                                ->body(count($records) . ' mentees marked as completed')
                                ->send();
                        }),
                    
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Remove Selected'),
                ]),
            ]);
    }

    private function generateAttendanceLink(): void
    {
        if (!$this->module->attendance_token) {
            $this->module->update([
                'attendance_token' => Str::random(32),
            ]);
        }
        
        $this->module->update([
            'attendance_link_active' => true,
        ]);
        
        $this->attendanceLink = route('module.attend', ['token' => $this->module->attendance_token]);
        
        Notification::make()
            ->success()
            ->title('Attendance Link Generated')
            ->body('The attendance link is now displayed on the page.')
            ->send();
    }
    
    public function deactivateAttendanceLink(): void
    {
        $this->module->update([
            'attendance_link_active' => false,
        ]);
        
        $this->attendanceLink = null;
        
        Notification::make()
            ->warning()
            ->title('Attendance Link Deactivated')
            ->body('Mentees can no longer use this link to mark attendance.')
            ->send();
    }

    private function hasUserCompletedModule(int $userId, int $programModuleId): bool
    {
        return DB::table('class_participants')
            ->join('mentee_module_progress', 'class_participants.id', '=', 'mentee_module_progress.class_participant_id')
            ->join('class_modules', 'mentee_module_progress.class_module_id', '=', 'class_modules.id')
            ->where('class_participants.user_id', $userId)
            ->where('class_modules.program_module_id', $programModuleId)
            ->where('mentee_module_progress.status', 'completed')
            ->exists();
    }
}