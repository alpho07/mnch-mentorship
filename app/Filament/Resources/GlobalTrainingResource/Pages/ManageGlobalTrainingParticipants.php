<?php

namespace App\Filament\Resources\GlobalTrainingResource\Pages;

use App\Filament\Resources\GlobalTrainingResource;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Models\Facility;
use App\Models\Department;
use App\Models\Cadre;
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
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\Writer;
use Illuminate\Http\Response;

class ManageGlobalTrainingParticipants extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = GlobalTrainingResource::class;

    protected static string $view = 'filament.pages.participants-global';

    public function getViewData(): array
    {
        return [
            'record' => $this->record,
        ];
    }

    public Training $record;

    public function mount(int|string $record): void
    {
        $this->record = Training::where('type', 'global_training')->findOrFail($this->record->id);
    }

    public function getTitle(): string
    {
        return "Manage Participants - {$this->record->title}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_template')
                ->label('Download Template')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->url('/training/1/participants/template')
                ->openUrlInNewTab(),

            Actions\Action::make('import_participants')
                ->label('Import Participants')
                ->icon('heroicon-o-document-arrow-up')
                ->color('success')
                ->form([
                    Forms\Components\Section::make('Import Participants')
                        ->description('Upload a CSV file with participant details. Download the template first for the correct format.')
                        ->schema([
                            FileUpload::make('participants_file')
                                ->label('CSV File')
                                ->acceptedFileTypes(['text/csv', '.csv'])
                                ->required()
                                ->directory('temp-imports')
                                ->visibility('private')
                                ->helperText('Upload a CSV file following the template format. Maximum file size: 2MB'),

                            Forms\Components\Placeholder::make('import_info')
                                ->content(new HtmlString('
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                        <h4 class="font-medium text-blue-900 mb-2">Import Guidelines:</h4>
                                        <ul class="text-sm text-blue-800 space-y-1">
                                            <li>• Use the template format (download above)</li>
                                            <li>• Phone numbers should include country code (+254...)</li>
                                            <li>• Email addresses are optional but recommended</li>
                                            <li>• Facilities: Use MFL code for accuracy, or facility name as fallback</li>
                                            <li>• Department and cadre names must match existing records</li>
                                            <li>• Existing users will be updated, new users will be created</li>
                                        </ul>
                                    </div>
                                ')),
                        ])
                ])
                ->action(function (array $data) {
                    return $this->importParticipants($data['participants_file']);
                }),

            Actions\Action::make('add_participant')
                ->label('Add Participant')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->form([
                    Forms\Components\Section::make('Participant Information')
                        ->description('Search by phone or add new participant details')
                        ->schema([
                            TextInput::make('phone')
                                ->label('Phone Number')
                                ->tel()
                                ->required()
                                ->placeholder('+254700000000')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                    if ($state) {
                                        $user = User::where('phone', $state)->first();
                                        if ($user) {
                                            $set('first_name', $user->first_name ?? $user->name);
                                            $set('last_name', $user->last_name);
                                            $set('email', $user->email);
                                            $set('facility_id', $user->facility_id);
                                            $set('department_id', $user->department_id);
                                            $set('cadre_id', $user->cadre_id);
                                            $set('user_id', $user->id);
                                            $set('user_found', true);
                                        } else {
                                            $set('user_found', false);
                                            // Clear fields for new user
                                            $set('first_name', '');
                                            $set('last_name', '');
                                            $set('email', '');
                                            $set('facility_id', null);
                                            $set('department_id', null);
                                            $set('cadre_id', null);
                                            $set('user_id', null);
                                        }
                                    }
                                }),

                            Forms\Components\Placeholder::make('user_status')
                                ->content(function (Get $get): HtmlString {
                                    if ($get('user_found')) {
                                        return new HtmlString('<div class="text-green-600 font-medium">✓ User found in system</div>');
                                    } elseif ($get('phone') && !$get('user_found')) {
                                        return new HtmlString('<div class="text-amber-600 font-medium">⚠ New user - please fill details below</div>');
                                    }
                                    return new HtmlString('<div class="text-gray-500">Enter phone number to search</div>');
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

                            Grid::make(3)
                                ->schema([
                                    Select::make('facility_id')
                                        ->label('Facility')
                                        ->options(Facility::all()->pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->createOptionForm([
                                            TextInput::make('name')
                                                ->required(),
                                            TextInput::make('mfl_code')
                                                ->label('MFL Code'),
                                        ]),

                                    Select::make('department_id')
                                        ->label('Department')
                                        ->options(Department::all()->pluck('name', 'id'))
                                        ->searchable()
                                        ->preload(),

                                    Select::make('cadre_id')
                                        ->label('Cadre')
                                        ->options(Cadre::all()->pluck('name', 'id'))
                                        ->searchable()
                                        ->preload(),
                                ]),

                            Forms\Components\Hidden::make('user_id'),
                            Forms\Components\Hidden::make('user_found'),
                        ])
                ])
                ->action(function (array $data) {
                    // Create or find user
                    if (!empty($data['user_id'])) {
                        $user = User::find($data['user_id']);
                    } else {
                        $user = User::create([
                            'first_name' => $data['first_name'],
                            'last_name' => $data['last_name'],
                            'email' => $data['email'] ?? null,
                            'phone' => $data['phone'],
                            'facility_id' => $data['facility_id'],
                            'department_id' => $data['department_id'] ?? null,
                            'cadre_id' => $data['cadre_id'] ?? null,
                            'password' => bcrypt('password'), // Default password
                            'status' => 'active',
                        ]);
                    }

                    // Check if already enrolled
                    $exists = TrainingParticipant::where('training_id', $this->record->id)
                        ->where('user_id', $user->id)
                        ->exists();

                    if ($exists) {
                        Notification::make()
                            ->title('Participant Already Enrolled')
                            ->body("{$user->full_name} is already enrolled in this training.")
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
                        ->title('Participant Added')
                        ->body("{$user->full_name} has been enrolled in the training.")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('back_to_training')
                ->label('Back to Training')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => GlobalTrainingResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    private function downloadTemplate()
    {
        $csv = Writer::createFromString('');

        // Add headers
        $csv->insertOne([
            'first_name',
            'last_name',
            'phone',
            'email',
            'facility_name',
            'facility_mfl_code',
            'department_name',
            'cadre_name'
        ]);

        // Add sample data
        $csv->insertOne([
            'John',
            'Doe',
            '+254700123456',
            'john.doe@example.com',
            'Kenyatta National Hospital',
            'KNH001',
            'Nursing',
            'Registered Nurse'
        ]);

        $csv->insertOne([
            'Jane',
            'Smith',
            '+254711234567',
            'jane.smith@example.com',
            'Moi Teaching Hospital',
            'MTH002',
            'Laboratory',
            'Lab Technician'
        ]);

        $filename = 'participants_import_template_' . date('Y-m-d') . '.csv';
        $content = $csv->toString();

        // Store temporarily and redirect to download
        $tempPath = 'temp/' . $filename;
        Storage::disk('public')->put($tempPath, $content);

        // Trigger download via JavaScript
        $this->js("
            const link = document.createElement('a');
            link.href = '" . Storage::disk('public')->url($tempPath) . "';
            link.download = '{$filename}';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Clean up after 5 seconds
            setTimeout(() => {
                fetch('/cleanup-temp-file', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content
                    },
                    body: JSON.stringify({file: '{$tempPath}'})
                });
            }, 5000);
        ");

        Notification::make()
            ->title('Template Downloaded')
            ->body('The participant import template has been downloaded.')
            ->success()
            ->send();
    }

    private function importParticipants($filePath)
    {
        try {
            $fullPath = Storage::disk('local')->path($filePath);
            $csv = Reader::createFromPath($fullPath, 'r');
            $csv->setHeaderOffset(0);

            $records = $csv->getRecords();
            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($records as $index => $record) {
                $rowNumber = $index + 2; // Account for header row

                try {
                    // Validate required fields
                    if (empty($record['first_name']) || empty($record['phone'])) {
                        $errors[] = "Row {$rowNumber}: First name and phone are required";
                        $skipped++;
                        continue;
                    }

                    // Find or create facility (prefer MFL code over name)
                    $facility = null;
                    if (!empty($record['facility_mfl_code'])) {
                        $facility = Facility::where('mfl_code', $record['facility_mfl_code'])->first();
                        if (!$facility) {
                            $errors[] = "Row {$rowNumber}: Facility with MFL code '{$record['facility_mfl_code']}' not found";
                            $skipped++;
                            continue;
                        }
                    } elseif (!empty($record['facility_name'])) {
                        $facility = Facility::where('name', $record['facility_name'])->first();
                        if (!$facility) {
                            $errors[] = "Row {$rowNumber}: Facility '{$record['facility_name']}' not found";
                            $skipped++;
                            continue;
                        }
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
                        // Update existing user
                        $user->update([
                            'first_name' => $record['first_name'],
                            'last_name' => $record['last_name'] ?? '',
                            'email' => $record['email'] ?? $user->email,
                            'facility_id' => $facility?->id ?? $user->facility_id,
                            'department_id' => $department?->id ?? $user->department_id,
                            'cadre_id' => $cadre?->id ?? $user->cadre_id,
                        ]);
                    } else {
                        // Create new user
                        $user = User::create([
                            'first_name' => $record['first_name'],
                            'last_name' => $record['last_name'] ?? '',
                            'phone' => $record['phone'],
                            'email' => $record['email'] ?? null,
                            'facility_id' => $facility?->id,
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

            // Clean up uploaded file
            Storage::disk('local')->delete($filePath);

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
            Storage::disk('local')->delete($filePath);

            Notification::make()
                ->title('Import Failed')
                ->body('Error processing file: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(TrainingParticipant::query()->where('training_id', $this->record->id))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Participant Name')
                    ->searchable(['name', 'first_name', 'last_name', 'middle_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Phone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.facility.name')
                    ->label('Facility')
                    ->searchable()
                    ->sortable(),

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
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('facility')
                    ->relationship('user.facility', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('cadre')
                    ->relationship('user.cadre', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view_details')
                        ->label('View Details')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->modalHeading(fn (TrainingParticipant $record): string => "Participant: {$record->user->full_name}")
                        ->modalContent(fn (TrainingParticipant $record): \Illuminate\View\View => view('filament.components.participant-details', ['participant' => $record]))
                        ->modalWidth('lg'),

                    Tables\Actions\Action::make('update_status')
                        ->label('Update Status')
                        ->icon('heroicon-o-pencil')
                        ->color('warning')
                        ->form([
                            Select::make('attendance_status')
                                ->options([
                                    'registered' => 'Registered',
                                    'attending' => 'Attending',
                                    'completed' => 'Completed',
                                    'dropped' => 'Dropped',
                                ])
                                ->required(),

                            Forms\Components\DatePicker::make('completion_date')
                                ->label('Completion Date')
                                ->visible(fn (Get $get) => $get('attendance_status') === 'completed'),
                        ])
                        ->fillForm(fn (TrainingParticipant $record): array => [
                            'attendance_status' => $record->attendance_status,
                            'completion_date' => $record->completion_date,
                        ])
                        ->action(function (TrainingParticipant $record, array $data): void {
                            $record->update($data);

                            Notification::make()
                                ->title('Status Updated')
                                ->body('Participant status has been updated successfully.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->label('Remove')
                        ->requiresConfirmation()
                        ->modalHeading('Remove Participant')
                        ->modalDescription('Are you sure you want to remove this participant from the training?'),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_attending')
                        ->label('Mark as Attending')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(fn (TrainingParticipant $record) =>
                                $record->update(['attendance_status' => 'attending'])
                            );
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Remove Selected'),
                ]),
            ])
            ->defaultSort('registration_date', 'desc');
    }
}
