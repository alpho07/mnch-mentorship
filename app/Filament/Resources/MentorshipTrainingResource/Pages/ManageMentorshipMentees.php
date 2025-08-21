<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Models\Department;
use App\Models\Cadre;
use App\Models\MenteeStatusLog;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\Reader;

class ManageMentorshipMentees extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-mentees';

    public Training $record;

    public function mount(int|string $record): void
    {
      
        $this->record = Training::where('type', 'facility_mentorship')->findOrFail($this->record->id);
    }

    public function getTitle(): string
    {
        return "Manage Mentees - {$this->record->title}";
    }

    public function getSubheading(): ?string
    {
        return "Facility: {$this->record->facility->name} • {$this->record->participants()->count()} mentees enrolled";
    }

    protected function getHeaderActions(): array
    {
        return [
            // Download CSV Template
            Actions\Action::make('download_template')
                ->label('Download Template')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->action(fn() => $this->downloadMenteeTemplate()),

            // Import Mentees from CSV
            Actions\Action::make('import_mentees')
                ->label('Import Mentees')
                ->icon('heroicon-o-document-arrow-up')
                ->color('success')
                ->form($this->getImportForm())
                ->action(fn(array $data) => $this->importMentees($data['mentees_file'])),

            // Add Individual Mentee
            Actions\Action::make('add_mentee')
                ->label('Add Mentee')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->form($this->getAddMenteeForm())
                ->action(fn(array $data) => $this->addMentee($data)),

            // Back to Mentorship
            Actions\Action::make('back_to_mentorship')
                ->label('Back to Mentorship')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn() => MentorshipTrainingResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(TrainingParticipant::query()->where('training_id', $this->record->id))
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label('Mentee Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Phone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.department.name')
                    ->label('Department')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('user.cadre.name')
                    ->label('Cadre')
                    ->searchable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\BadgeColumn::make('user.current_status')
                    ->label('Status')
                    ->getStateUsing(fn(TrainingParticipant $record): string => 
                        $record->user->current_status ?? 'active'
                    )
                    ->colors($this->getStatusColors()),

                Tables\Columns\TextColumn::make('assessment_progress')
                    ->label('Assessment Progress')
                    ->getStateUsing(fn(TrainingParticipant $record): string => 
                        $this->getAssessmentProgress($record)
                    )
                    ->badge()
                    ->color(fn(TrainingParticipant $record): string => 
                        $this->getProgressColor($record)
                    ),

                Tables\Columns\TextColumn::make('overall_score')
                    ->label('Overall Score')
                    ->getStateUsing(fn(TrainingParticipant $record): string => 
                        $this->getOverallScore($record)
                    )
                    ->badge()
                    ->color(fn(TrainingParticipant $record): string => 
                        $this->getScoreColor($record)
                    ),

                Tables\Columns\TextColumn::make('registration_date')
                    ->label('Enrolled')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->relationship('user.department', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('cadre')
                    ->relationship('user.cadre', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->options(MenteeStatusLog::getStatusOptions())
                    ->query(fn(Builder $query, array $data) => 
                        $this->filterByStatus($query, $data)
                    ),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view_profile')
                        ->label('View Profile')
                        ->icon('heroicon-o-user')
                        ->color('info')
                        ->url(fn(TrainingParticipant $record): string => 
                            route('filament.admin.resources.mentee-profiles.view', ['record' => $record->user_id])
                        ),

                    Tables\Actions\Action::make('update_status')
                        ->label('Update Status')
                        ->icon('heroicon-o-pencil')
                        ->color('warning')
                        ->form($this->getUpdateStatusForm())
                        ->action(fn(TrainingParticipant $record, array $data) => 
                            $this->updateMenteeStatus($record, $data)
                        ),

                    Tables\Actions\DeleteAction::make()
                        ->label('Remove from Program')
                        ->requiresConfirmation()
                        ->modalHeading('Remove Mentee')
                        ->modalDescription('Are you sure you want to remove this mentee from the mentorship program?'),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_attending')
                        ->label('Mark as Attending')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn($records) => $this->markAsAttending($records)),

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Remove Selected'),
                ]),
            ])
            ->defaultSort('registration_date', 'desc')
            ->emptyStateHeading('No Mentees Found')
            ->emptyStateDescription('Add mentees to this mentorship program to begin training.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add First Mentee')
                    ->icon('heroicon-o-plus'),
            ]);
    }

    // Form Definitions
    private function getImportForm(): array
    {
        return [
            Forms\Components\Section::make('Import Mentees')
                ->description('Upload a CSV file with mentee details from your facility')
                ->schema([
                    FileUpload::make('mentees_file')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv', '.csv'])
                        ->required()
                        ->directory('temp-imports')
                        ->visibility('private'),

                    Forms\Components\Placeholder::make('import_guidelines')
                        ->content(new HtmlString($this->getImportGuidelines())),
                ])
        ];
    }

    private function getAddMenteeForm(): array
    {
        return [
            Forms\Components\Section::make('Mentee Information')
                ->description('Search by phone or add new mentee from your facility')
                ->schema([
                    TextInput::make('phone')
                        ->label('Phone Number')
                        ->tel()
                        ->required()
                        ->placeholder('+254700000000')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                            $this->handlePhoneLookup($set, $state);
                        }),

                    Forms\Components\Placeholder::make('user_status')
                        ->content(function (Get $get): HtmlString {
                            return new HtmlString($this->getUserStatusMessage($get));
                        }),

                    Grid::make(2)
                        ->schema([
                            TextInput::make('first_name')
                                ->label('First Name')
                                ->required(),
                            TextInput::make('last_name')
                                ->label('Last Name')
                                ->required(),
                        ]),

                    TextInput::make('email')
                        ->label('Email Address')
                        ->email(),

                    Grid::make(2)
                        ->schema([
                            Select::make('department_id')
                                ->label('Department')
                                ->options(Department::all()->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('cadre_id')
                                ->label('Cadre')
                                ->options(Cadre::all()->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),

                    Forms\Components\Hidden::make('user_id'),
                    Forms\Components\Hidden::make('user_found'),
                ])
        ];
    }

    private function getUpdateStatusForm(): array
    {
        return [
            Select::make('new_status')
                ->label('New Status')
                ->options(MenteeStatusLog::getStatusOptions())
                ->required(),

            Forms\Components\DatePicker::make('effective_date')
                ->label('Effective Date')
                ->default(now())
                ->required(),

            TextInput::make('reason')
                ->label('Reason for Change')
                ->required(),

            Textarea::make('notes')
                ->label('Additional Notes')
                ->rows(3),
        ];
    }

    // Action Methods
    private function addMentee(array $data): void
    {
        // Create or find user
        if (!empty($data['user_id'])) {
            $user = User::find($data['user_id']);
            $user->update(['facility_id' => $this->record->facility_id]);
        } else {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'facility_id' => $this->record->facility_id,
                'department_id' => $data['department_id'],
                'cadre_id' => $data['cadre_id'],
                'password' => bcrypt('password'),
                'status' => 'active',
            ]);
        }

        // Check if already enrolled
        $exists = TrainingParticipant::where('training_id', $this->record->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            Notification::make()
                ->title('Mentee Already Enrolled')
                ->body("{$user->full_name} is already enrolled in this mentorship program.")
                ->warning()
                ->send();
            return;
        }

        // Create training participant
        TrainingParticipant::create([
            'training_id' => $this->record->id,
            'user_id' => $user->id,
            'registration_date' => now(),
            'attendance_status' => 'registered',
        ]);

        Notification::make()
            ->title('Mentee Added')
            ->body("{$user->full_name} has been enrolled in the mentorship program.")
            ->success()
            ->send();
    }

    private function updateMenteeStatus(TrainingParticipant $participant, array $data): void
    {
        $participant->user->updateStatus(
            $data['new_status'],
            $data['reason'],
            $data['notes'] ?? null,
            $data['effective_date']
        );

        Notification::make()
            ->title('Status Updated')
            ->body("Status updated for {$participant->user->full_name}")
            ->success()
            ->send();
    }

    private function markAsAttending($records): void
    {
        $records->each(fn(TrainingParticipant $record) =>
            $record->update(['attendance_status' => 'attending'])
        );

        Notification::make()
            ->title('Status Updated')
            ->body('Selected mentees marked as attending.')
            ->success()
            ->send();
    }

    private function importMentees($filePath): void
    {
        try {
            $fullPath = storage_path('app/' . $filePath);
            $csv = Reader::createFromPath($fullPath, 'r');
            $csv->setHeaderOffset(0);

            $records = $csv->getRecords();
            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($records as $index => $record) {
                $rowNumber = $index + 2;

                try {
                    if (empty($record['first_name']) || empty($record['phone'])) {
                        $errors[] = "Row {$rowNumber}: First name and phone are required";
                        $skipped++;
                        continue;
                    }

                    // Find department and cadre
                    $department = !empty($record['department_name'])
                        ? Department::where('name', $record['department_name'])->first()
                        : null;

                    $cadre = !empty($record['cadre_name'])
                        ? Cadre::where('name', $record['cadre_name'])->first()
                        : null;

                    // Find or create user
                    $user = User::where('phone', $record['phone'])->first();

                    if ($user) {
                        $user->update([
                            'first_name' => $record['first_name'],
                            'last_name' => $record['last_name'] ?? '',
                            'email' => $record['email'] ?? $user->email,
                            'facility_id' => $this->record->facility_id,
                            'department_id' => $department?->id ?? $user->department_id,
                            'cadre_id' => $cadre?->id ?? $user->cadre_id,
                        ]);
                    } else {
                        $user = User::create([
                            'first_name' => $record['first_name'],
                            'last_name' => $record['last_name'] ?? '',
                            'phone' => $record['phone'],
                            'email' => $record['email'] ?? null,
                            'facility_id' => $this->record->facility_id,
                            'department_id' => $department?->id,
                            'cadre_id' => $cadre?->id,
                            'password' => bcrypt('password'),
                            'status' => 'active',
                        ]);
                    }

                    // Check if already enrolled
                    $exists = TrainingParticipant::where('training_id', $this->record->id)
                        ->where('user_id', $user->id)
                        ->exists();

                    if ($exists) {
                        $errors[] = "Row {$rowNumber}: {$user->full_name} already enrolled";
                        $skipped++;
                        continue;
                    }

                    // Create training participant
                    TrainingParticipant::create([
                        'training_id' => $this->record->id,
                        'user_id' => $user->id,
                        'registration_date' => now(),
                        'attendance_status' => 'registered',
                    ]);

                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNumber}: " . $e->getMessage();
                    $skipped++;
                }
            }

            // Clean up file
            unlink($fullPath);

            // Show results
            $message = "Import completed: {$imported} participants added";
            if ($skipped > 0) {
                $message .= ", {$skipped} skipped";
            }

            if (!empty($errors)) {
                $errorMessage = implode("\n", array_slice($errors, 0, 10));
                if (count($errors) > 10) {
                    $errorMessage .= "\n... and " . (count($errors) - 10) . " more errors";
                }

                Notification::make()
                    ->title('Import Completed with Issues')
                    ->body($message . "\n\nErrors:\n" . $errorMessage)
                    ->warning()
                    ->persistent()
                    ->send();
            } else {
                Notification::make()
                    ->title('Import Successful')
                    ->body($message)
                    ->success()
                    ->send();
            }

        } catch (\Exception $e) {
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            Notification::make()
                ->title('Import Failed')
                ->body('Error processing file: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function downloadMenteeTemplate()
    {
        $filename = 'mentee_import_template_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'first_name',
            'last_name', 
            'phone',
            'email',
            'department_name',
            'cadre_name'
        ];
        
        return response()->streamDownload(function () use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            
            // Add sample data
            fputcsv($file, [
                'John',
                'Doe',
                '+254700123456', 
                'john.doe@example.com',
                'Nursing',
                'Registered Nurse'
            ]);
            
            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    // Helper Methods
    private function handlePhoneLookup(Set $set, $state): void
    {
        if ($state) {
            $user = User::where('phone', $state)->first();
            if ($user) {
                $set('first_name', $user->first_name);
                $set('last_name', $user->last_name);
                $set('email', $user->email);
                $set('department_id', $user->department_id);
                $set('cadre_id', $user->cadre_id);
                $set('user_id', $user->id);
                $set('user_found', true);
            } else {
                $set('user_found', false);
                $set('first_name', '');
                $set('last_name', '');
                $set('email', '');
                $set('department_id', null);
                $set('cadre_id', null);
                $set('user_id', null);
            }
        }
    }

    private function getUserStatusMessage(Get $get): string
    {
        if ($get('user_found')) {
            return '<div class="text-green-600 font-medium">✓ User found in system</div>';
        } elseif ($get('phone') && !$get('user_found')) {
            return '<div class="text-amber-600 font-medium">⚠ New user - please fill details below</div>';
        }
        return '<div class="text-gray-500">Enter phone number to search</div>';
    }

    private function getImportGuidelines(): string
    {
        return '
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="font-medium text-blue-900 mb-2">Import Guidelines:</h4>
                <ul class="text-sm text-blue-800 space-y-1">
                    <li>• Only mentees from <strong>' . $this->record->facility->name . '</strong> can be added</li>
                    <li>• Phone numbers should include country code (+254...)</li>
                    <li>• Department and cadre names must match existing records</li>
                    <li>• Existing users will be updated, new users will be created</li>
                </ul>
            </div>
        ';
    }

    private function getAssessmentProgress(TrainingParticipant $record): string
    {
        $totalCategories = $this->record->assessmentCategories()->count();
        $completedCategories = $record->user->assessmentResults()
            ->whereHas('assessmentCategory', function ($query) {
                $query->where('training_id', $this->record->id);
            })
            ->distinct('assessment_category_id')
            ->count();
        
        $percentage = $totalCategories > 0 ? round(($completedCategories / $totalCategories) * 100) : 0;
        return "{$completedCategories}/{$totalCategories} ({$percentage}%)";
    }

    private function getOverallScore(TrainingParticipant $record): string
    {
        $scores = $record->user->assessmentResults()
            ->whereHas('assessmentCategory', function ($query) {
                $query->where('training_id', $this->record->id);
            })
            ->pluck('score');
        
        if ($scores->isEmpty()) return 'Not assessed';
        
        $average = $scores->avg();
        return number_format($average, 1) . '%';
    }

    private function getStatusColors(): array
    {
        return [
            'success' => 'active',
            'info' => 'study_leave',
            'warning' => 'transferred',
            'secondary' => fn($state) => in_array($state, ['resigned', 'retired']),
            'danger' => fn($state) => in_array($state, ['defected', 'deceased', 'suspended']),
        ];
    }

    private function getProgressColor(TrainingParticipant $record): string
    {
        $totalCategories = $this->record->assessmentCategories()->count();
        $completedCategories = $record->user->assessmentResults()
            ->whereHas('assessmentCategory', function ($query) {
                $query->where('training_id', $this->record->id);
            })
            ->distinct('assessment_category_id')
            ->count();
        
        if ($totalCategories == 0) return 'gray';
        $percentage = ($completedCategories / $totalCategories) * 100;
        
        if ($percentage == 100) return 'success';
        if ($percentage > 0) return 'warning';
        return 'danger';
    }

    private function getScoreColor(TrainingParticipant $record): string
    {
        $scores = $record->user->assessmentResults()
            ->whereHas('assessmentCategory', function ($query) {
                $query->where('training_id', $this->record->id);
            })
            ->pluck('score');
        
        if ($scores->isEmpty()) return 'gray';
        
        $average = $scores->avg();
        if ($average >= 80) return 'success';
        if ($average >= 70) return 'warning';
        return 'danger';
    }

    private function filterByStatus(Builder $query, array $data): Builder
    {
        if ($data['value']) {
            return $query->whereHas('user.statusLogs', function ($q) use ($data) {
                $q->where('new_status', $data['value'])
                  ->latest('effective_date')
                  ->limit(1);
            });
        }
        return $query;
    }
}