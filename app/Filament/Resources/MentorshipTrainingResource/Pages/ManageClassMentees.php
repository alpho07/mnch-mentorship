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

class ManageClassMentees extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-class-mentees';
    protected static bool $shouldRegisterNavigation = false;
    
    public Training $training;
    public MentorshipClass $class;

    public function mount(Training $training, MentorshipClass $class): void
    {
        $this->training = $training;
        $this->class = $class->load('training');
    }

    public function getTitle(): string
    {
        return "Manage Mentees - {$this->class->name}";
    }

    public function getSubheading(): ?string
    {
        $enrolledCount = $this->class->participants()->count();
        return "{$this->training->facility->name} • {$enrolledCount} mentees enrolled";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add_mentees')
                ->label('Add Mentees')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->slideOver()
                ->modalWidth('2xl')
                ->form([
                    Forms\Components\Section::make('Search & Select Mentees')
                        ->description('Search for existing users or add new mentees to this class')
                        ->schema([
                            Forms\Components\Select::make('user_ids')
                                ->label('Select Mentees')
                                ->multiple()
                                ->searchable()
                                ->getSearchResultsUsing(function (string $search) {
                                    return User::where('status', 'active')
                                        ->where(function ($query) use ($search) {
                                            $query->where('first_name', 'like', "%{$search}%")
                                                ->orWhere('last_name', 'like', "%{$search}%")
                                                ->orWhere('phone', 'like', "%{$search}%")
                                                ->orWhere('email', 'like', "%{$search}%");
                                        })
                                        ->whereNotIn('id', function ($query) {
                                            $query->select('user_id')
                                                ->from('class_participants')
                                                ->where('mentorship_class_id', $this->class->id);
                                        })
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn($user) => [
                                            $user->id => $user->full_name . 
                                                ' (' . $user->phone . ')' .
                                                ($user->facility ? ' - ' . $user->facility->name : ''),
                                        ]);
                                })
                                ->getOptionLabelsUsing(function (array $values) {
                                    return User::whereIn('id', $values)
                                        ->get()
                                        ->mapWithKeys(fn($user) => [
                                            $user->id => $user->full_name . 
                                                ' (' . $user->phone . ')' .
                                                ($user->facility ? ' - ' . $user->facility->name : ''),
                                        ]);
                                })
                                ->helperText('Type to search by name, phone, or email')
                                ->preload()
                                ->columnSpanFull(),
                        ]),
                    
                    Forms\Components\Section::make('Or Add New Mentee')
                        ->description('Create a new user and add them to this class')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('new_first_name')
                                    ->label('First Name')
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('new_last_name')
                                    ->label('Last Name')
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('new_phone')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->required(fn(Forms\Get $get) => 
                                        !empty($get('new_first_name')) || !empty($get('new_last_name'))
                                    )
                                    ->helperText('Required if adding new mentee'),
                                
                                Forms\Components\TextInput::make('new_email')
                                    ->label('Email')
                                    ->email(),
                                
                                Forms\Components\Select::make('new_facility_id')
                                    ->label('Facility')
                                    ->options(\App\Models\Facility::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required(fn(Forms\Get $get) => 
                                        !empty($get('new_first_name')) || !empty($get('new_last_name'))
                                    )
                                    ->columnSpanFull(),
                            ]),
                        ]),
                    
                    Forms\Components\Section::make('Module Exemptions')
                        ->description('System automatically skips modules mentees completed in previous classes')
                        ->schema([
                            Forms\Components\Placeholder::make('exemption_info')
                                ->content(new \Illuminate\Support\HtmlString('
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        <p class="mb-2">✓ Mentees who previously completed modules will be automatically exempted</p>
                                        <p>✓ They will only see modules they haven\'t completed yet</p>
                                    </div>
                                '))
                                ->columnSpanFull(),
                        ]),
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

    public function table(Table $table): Table
    {
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
                    ->color(fn($state) => match(true) {
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

    private function addMentees(array $data): void
    {
        $userIds = $data['user_ids'] ?? [];
        $added = 0;
        $exempted = 0;
        
        // Create new user if provided
        if (!empty($data['new_first_name']) && !empty($data['new_phone'])) {
            // Check if user with phone already exists
            $existingUser = User::where('phone', $data['new_phone'])->first();
            
            if ($existingUser) {
                $userIds[] = $existingUser->id;
                
                Notification::make()
                    ->warning()
                    ->title('User Already Exists')
                    ->body("User with phone {$data['new_phone']} already exists. Adding them to class.")
                    ->send();
            } else {
                // Create new user
                $newUser = User::create([
                    'first_name' => $data['new_first_name'],
                    'last_name' => $data['new_last_name'],
                    'phone' => $data['new_phone'],
                    'email' => $data['new_email'] ?? null,
                    'facility_id' => $data['new_facility_id'],
                    'password' => bcrypt('temporary123'), // Default password
                    'status' => 'active',
                ]);
                
                $userIds[] = $newUser->id;
                
                Notification::make()
                    ->success()
                    ->title('New User Created')
                    ->body("User {$newUser->full_name} created and will be added to class")
                    ->send();
            }
        }
        
        // Add each user to the class
        foreach ($userIds as $userId) {
            // Skip if already enrolled
            $exists = ClassParticipant::where('mentorship_class_id', $this->class->id)
                ->where('user_id', $userId)
                ->exists();
            
            if ($exists) {
                continue;
            }
            
            // Create participant
            $participant = ClassParticipant::create([
                'mentorship_class_id' => $this->class->id,
                'user_id' => $userId,
                'status' => 'enrolled',
                'enrolled_at' => now(),
            ]);
            
            // Get modules this user completed in previous classes
            $completedModuleIds = $this->getUserCompletedModules($userId);
            
            // Create module progress records for all class modules
            foreach ($this->class->classModules as $classModule) {
                $isExempted = in_array($classModule->program_module_id, $completedModuleIds);
                
                \App\Models\MenteeModuleProgress::create([
                    'class_participant_id' => $participant->id,
                    'class_module_id' => $classModule->id,
                    'status' => $isExempted ? 'exempted' : 'not_started',
                    'completed_in_previous_class' => $isExempted,
                    'exempted_at' => $isExempted ? now() : null,
                ]);
                
                if ($isExempted) {
                    $exempted++;
                }
            }
            
            $added++;
        }
        
        $message = "{$added} mentee(s) added to {$this->class->name}";
        if ($exempted > 0) {
            $message .= " • {$exempted} module(s) auto-exempted";
        }
        
        Notification::make()
            ->success()
            ->title('Mentees Added')
            ->body($message)
            ->send();
    }
    
    private function getUserCompletedModules(int $userId): array
    {
        // Get all modules this user has completed in ANY previous class
        return \Illuminate\Support\Facades\DB::table('class_participants')
            ->join('mentee_module_progress', 'class_participants.id', '=', 'mentee_module_progress.class_participant_id')
            ->join('class_modules', 'mentee_module_progress.class_module_id', '=', 'class_modules.id')
            ->where('class_participants.user_id', $userId)
            ->where('mentee_module_progress.status', 'completed')
            ->pluck('class_modules.program_module_id')
            ->unique()
            ->toArray();
    }

    private function generateEnrollmentLink(): void
    {
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