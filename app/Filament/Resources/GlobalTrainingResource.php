<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GlobalTrainingResource\Pages;
use App\Models\Training;
use App\Models\Program;
use App\Models\User;
use App\Models\County;
use App\Models\Partner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Module;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

class GlobalTrainingResource extends Resource
{
    protected static ?string $model = Training::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'MOH';

    protected static ?string $navigationGroup = 'Training Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'moh-trainings';

    protected static ?string $recordTitleAttribute = 'title';

    // Filter to only show global trainings
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', 'global_training')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    // Ensure proper route model binding for global trainings only
    public static function resolveRecordRouteBinding($value): ?Model
    {
        return static::getModel()::where('type', 'global_training')->findOrFail($value);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Hidden field to ensure type is always set
                Forms\Components\Hidden::make('type')
                    ->default('global_training'),

                // Hidden field to store mentor_id (logged in user)
                Forms\Components\Hidden::make('mentor_id')
                    ->default(auth()->id()),

                Forms\Components\Section::make('Training Information')
                    ->description('Basic details about the training program')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Training Title')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Enter the training title')
                                    ->columnSpanFull(),

                                TextInput::make('identifier')
                                    ->label('Training ID')
                                    ->required()
                                    ->readOnly(true)
                                    ->unique(Training::class, ignoreRecord: true)
                                    ->maxLength(50)
                                    ->default(fn($record) => $record?->identifier ?? strtoupper('TRN-' . Str::random(8)))
                                    ->disabled(fn($record) => filled($record?->identifier))
                                    ->dehydrated(true)
                                    ->helperText('Auto-generated unique identifier'),

                                Forms\Components\Select::make('status')
                                    ->label('Training Status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'ongoing' => 'Ongoing',
                                        'completed' => 'Completed',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->required()
                                    ->default('draft'),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Training Description')
                            ->rows(3)
                            ->placeholder('Provide a detailed description of the training program')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Training Leadership')
                    ->description('Select who will lead this training')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('lead_type')
                                    ->label('Training Lead Type')
                                    ->options([
                                        'national' => 'National',
                                        'county' => 'County',
                                        'partner' => 'Partner Led',
                                    ])
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        // Clear dependent fields when lead type changes
                                        if ($state === 'national') {
                                            $set('lead_county_id', null);
                                            $set('lead_partner_id', null);
                                        } elseif ($state === 'county') {
                                            $set('lead_division_id', null);
                                            $set('lead_partner_id', null);
                                        } elseif ($state === 'partner') {
                                            $set('lead_division_id', null);
                                            $set('lead_county_id', null);
                                        }
                                    }),

                                Forms\Components\Select::make('lead_division_id')
                                    ->label('Division')
                                    ->relationship('division', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Get $get): bool => $get('lead_type') === 'national')
                                    ->required(fn (Get $get): bool => $get('lead_type') === 'national')
                                    ->placeholder('Select the division leading this training'),
                            ]),

                        Forms\Components\Grid::make(1)
                            ->schema([
                                Forms\Components\Select::make('lead_county_id')
                                    ->label('County')
                                    ->relationship('county', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Get $get): bool => $get('lead_type') === 'county')
                                    ->required(fn (Get $get): bool => $get('lead_type') === 'county')
                                    ->placeholder('Select the county leading this training'),

                                Forms\Components\Select::make('lead_partner_id')
                                    ->label('Partner Organization')
                                    ->relationship('partner', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Get $get): bool => $get('lead_type') === 'partner')
                                    ->required(fn (Get $get): bool => $get('lead_type') === 'partner')
                                    ->placeholder('Select the partner organization leading this training'),
                            ]),
                    ]),

                Forms\Components\Section::make('Schedule & Logistics')
                    ->description('Training dates, location, and capacity')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Start Date')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M j, Y'),

                                Forms\Components\DatePicker::make('end_date')
                                    ->label('End Date')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M j, Y')
                                    ->after('start_date'),

                                Forms\Components\TextInput::make('max_participants')
                                    ->label('Maximum Participants')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(30)
                                    ->suffix('people'),
                            ]),

                        Forms\Components\Select::make('locations')
                            ->label('Training Locations')
                            ->multiple()
                            ->relationship('locations', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Location Name'),
                                Forms\Components\Select::make('type')
                                    ->options([
                                        'training_center' => 'Training Center',
                                        'hospital' => 'Hospital',
                                        'conference_hall' => 'Conference Hall',
                                        'hotel' => 'Hotel',
                                        'university' => 'University',
                                        'other' => 'Other',
                                    ])
                                    ->default('training_center'),
                                Forms\Components\Textarea::make('address')
                                    ->rows(2)
                                    ->label('Address'),
                            ])
                            ->createOptionUsing(function (array $data) {
                                return \App\Models\Location::create($data)->id;
                            })
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $locations = \App\Models\Location::whereIn('id', $state)->get();
                                    $locationNames = $locations->pluck('name')->implode(', ');
                                    $set('location', $locationNames);
                                }
                            })
                            ->placeholder('Select or create training locations')
                            ->helperText('Select multiple existing locations or create new ones. Hold Ctrl/Cmd to select multiple.')
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('location_tags')
                            ->label('Or Add Locations as Tags')
                            ->placeholder('Type location names and press Enter')
                            ->helperText('Alternative way to add locations. These will be created automatically if they don\'t exist.')
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                if ($state) {
                                    // Create locations that don't exist and get their IDs
                                    $locationIds = [];
                                    $existingLocationIds = $get('locations') ?? [];
                                    
                                    foreach ($state as $locationName) {
                                        $location = \App\Models\Location::firstOrCreate(
                                            ['name' => $locationName],
                                            ['type' => 'other']
                                        );
                                        $locationIds[] = $location->id;
                                    }
                                    
                                    // Merge with existing selected locations
                                    $allLocationIds = array_unique(array_merge($existingLocationIds, $locationIds));
                                    $set('locations', $allLocationIds);
                                    
                                    // Update the location text field
                                    $allLocations = \App\Models\Location::whereIn('id', $allLocationIds)->get();
                                    $set('location', $allLocations->pluck('name')->implode(', '));
                                }
                            })
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('location')
                            ->label('Combined Locations (Auto-filled)')
                            ->disabled()
                            ->dehydrated(true)
                            ->placeholder('Will be auto-filled when you select locations above')
                            ->helperText('This field combines all selected locations for display purposes')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Content & Programs')
                    ->description('Select the programs, modules, and methodologies for this training')
                    ->schema([
                        Forms\Components\Select::make('programs')
                            ->label('Training Programs')
                            ->multiple()
                            ->relationship('programs', 'name')
                            ->preload()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                // Clear modules when programs change
                                $set('modules', []);
                            })
                            ->helperText('Select the main programs this training will cover')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('modules')
                            ->label('Training Modules')
                            ->multiple()
                            ->options(function (Get $get) {
                                $programIds = $get('programs');
                                if (!$programIds) {
                                    return [];
                                }

                                return Module::whereIn('program_id', $programIds)
                                    ->with('program')
                                    ->get()
                                    ->mapWithKeys(function ($module) {
                                        return [
                                            $module->id => "{$module->program->name} - {$module->name}"
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->searchable()
                            ->helperText('Specific modules within the selected programs')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('methodologies')
                            ->label('Training Methodologies')
                            ->multiple()
                            ->relationship('methodologies', 'name')
                            ->preload()
                            ->searchable()
                            ->helperText('Select the training methods that will be used')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Additional Information')
                    ->description('Any additional notes or information about the training')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->placeholder('Add any additional information, special instructions, or notes about this training')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    protected static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'global_training';
        $data['mentor_id'] = auth()->id();
        
        // Handle location tags
        if (!empty($data['location_tags'])) {
            $locationIds = [];
            foreach ($data['location_tags'] as $locationName) {
                $location = \App\Models\Location::firstOrCreate(
                    ['name' => $locationName],
                    ['type' => 'other']
                );
                $locationIds[] = $location->id;
            }
            $data['locations'] = array_unique(array_merge($data['locations'] ?? [], $locationIds));
        }
        
        return $data;
    }

    protected static function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = 'global_training';
        // Only set mentor_id if it's not already set (preserve existing mentor on edit)
        if (empty($data['mentor_id'])) {
            $data['mentor_id'] = auth()->id();
        }
        
        // Handle location tags
        if (!empty($data['location_tags'])) {
            $locationIds = [];
            foreach ($data['location_tags'] as $locationName) {
                $location = \App\Models\Location::firstOrCreate(
                    ['name' => $locationName],
                    ['type' => 'other']
                );
                $locationIds[] = $location->id;
            }
            $data['locations'] = array_unique(array_merge($data['locations'] ?? [], $locationIds));
        }
        
        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(static::getEloquentQuery()->with(['locations', 'programs', 'county', 'division', 'partner', 'mentor']))
            ->columns([
                TextColumn::make('identifier')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(
                        fn(Training $record): string =>
                        $record->description ? Str::limit($record->description, 60) : ''
                    ),

                BadgeColumn::make('lead_type')
                    ->label('Lead Type')
                    ->colors([
                        'primary' => 'national',
                        'success' => 'county',
                        'warning' => 'partner',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'national' => 'National',
                        'county' => 'County',
                        'partner' => 'Partner Led',
                        default => ucfirst($state),
                    }),

                TextColumn::make('lead_organization')
                    ->label('Lead Organization')
                    ->getStateUsing(function (Training $record): string {
                        return match ($record->lead_type) {
                            'national' => $record->division?->name ?? 'Ministry of Health',
                            'county' => $record->county?->name ?? 'Not specified',
                            'partner' => $record->partner?->name ?? 'Not specified',
                            default => 'Not specified',
                        };
                    })
                    ->searchable(['division.name', 'county.name', 'partner.name']),

                TextColumn::make('programs.name')
                    ->label('Programs')
                    ->badge()
                    ->separator(', ')
                    ->limit(30)
                    ->color('info'),

                TextColumn::make('mentor.full_name')
                    ->label('Created By')
                    ->searchable(['first_name', 'last_name'])
                    ->description(
                        fn(Training $record): string =>
                        $record->mentor?->facility?->name ?? ''
                    ),

                TextColumn::make('locations.name')
                    ->label('Locations')
                    ->badge()
                    ->separator(', ')
                    ->limit(50)
                    ->tooltip(function (Training $record): ?string {
                        if ($record->locations && $record->locations->isNotEmpty()) {
                            return $record->locations->pluck('name')->implode(', ');
                        }
                        return $record->location ?: 'No location specified';
                    })
                    ->placeholder(fn (Training $record): string => $record->location ?: 'No location specified')
                    ->formatStateUsing(function (Training $record): string {
                        if ($record->locations && $record->locations->isNotEmpty()) {
                            return $record->locations->pluck('name')->implode(', ');
                        }
                        return $record->location ?: 'No location specified';
                    }),

                TextColumn::make('start_date')
                    ->date('M j, Y')
                    ->sortable()
                    ->description(
                        fn(Training $record): string =>
                        $record->end_date ? 'to ' . $record->end_date->format('M j, Y') : ''
                    ),

                BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'registration_open',
                        'success' => 'ongoing',
                        'primary' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->icons([
                        'heroicon-o-pencil' => 'draft',
                        'heroicon-o-clock' => 'registration_open',
                        'heroicon-o-play' => 'ongoing',
                        'heroicon-o-check-circle' => 'completed',
                        'heroicon-o-x-circle' => 'cancelled',
                    ]),

                TextColumn::make('participants_count')
                    ->label('Participants')
                    ->counts('participants')
                    ->sortable()
                    ->badge()
                    ->color('success'),
            ])
            ->filters([
                SelectFilter::make('lead_type')
                    ->label('Lead Type')
                    ->options([
                        'national' => 'National',
                        'county' => 'County',
                        'partner' => 'Partner Led',
                    ])
                    ->multiple(),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'registration_open' => 'Registration Open',
                        'ongoing' => 'Ongoing',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple(),

                SelectFilter::make('programs')
                    ->relationship('programs', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('division')
                    ->relationship('division', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('county')
                    ->relationship('county', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('partner')
                    ->relationship('partner', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('mentor')
                    ->relationship('mentor', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn(User $record): string => $record->full_name)
                    ->multiple()
                    ->preload(),

                Filter::make('date_range')
                    ->form([
                        DatePicker::make('start_date')
                            ->label('From Date'),
                        DatePicker::make('end_date')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['start_date'],
                                fn(Builder $query, $date): Builder => $query->whereDate('start_date', '>=', $date),
                            )
                            ->when(
                                $data['end_date'],
                                fn(Builder $query, $date): Builder => $query->whereDate('end_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['start_date'] ?? null) {
                            $indicators['start_date'] = 'From ' . Carbon::parse($data['start_date'])->toFormattedDateString();
                        }
                        if ($data['end_date'] ?? null) {
                            $indicators['end_date'] = 'To ' . Carbon::parse($data['end_date'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->color('info'),
                    Tables\Actions\EditAction::make()
                        ->color('warning'),
                    Action::make('manage_participants')
                        ->label('Participants')
                        ->icon('heroicon-o-users')
                        ->color('success')
                        ->url(
                            fn(Training $record): string =>
                            static::getUrl('participants', ['record' => $record])
                        ),
                    Action::make('manage_assessments')
                        ->label('Assessments')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('primary')
                        ->url(
                            fn(Training $record): string =>
                            static::getUrl('assessments', ['record' => $record])
                        ),
                    Action::make('duplicate')
                        ->label('Duplicate')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->action(function (Training $record) {
                            $newTraining = $record->replicate();
                            $newTraining->title = $record->title . ' (Copy)';
                            $newTraining->identifier = 'GT-' . strtoupper(Str::random(6));
                            $newTraining->status = 'draft';
                            $newTraining->type = 'global_training';
                            $newTraining->mentor_id = auth()->id(); // Set current user as mentor
                            $newTraining->save();

                            // Copy relationships
                            $newTraining->programs()->attach($record->programs->pluck('id'));

                            return redirect()->to(static::getUrl('edit', ['record' => $newTraining]));
                        }),
                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->size('sm')
                    ->color('gray')
                    ->button()
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(fn(Training $record) => $record->update(['status' => 'completed']));
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('start_date', 'desc')
            ->poll('30s')
            ->striped()
            ->emptyStateHeading('No Global Trainings Found')
            ->emptyStateDescription('Create your first global training to get started.')
            ->emptyStateIcon('heroicon-o-academic-cap')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create Global Training')
                    ->icon('heroicon-o-plus'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Add relation managers here if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGlobalTrainings::route('/'),
            'create' => Pages\CreateGlobalTraining::route('/create'),
            'view' => Pages\ViewGlobalTraining::route('/{record}'),
            'edit' => Pages\EditGlobalTraining::route('/{record}/edit'),
            'participants' => Pages\ManageGlobalTrainingParticipants::route('/{record}/participants'),
            'assessments' => Pages\ManageTrainingAssessments::route('/{record}/assessments'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('type', 'global_training')
            ->where('status', 'completed')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'identifier', 'description', 'location'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->title;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'ID' => $record->identifier,
            'Status' => ucfirst($record->status),
            'Location' => $record->location,
            'Start Date' => $record->start_date?->format('M j, Y'),
        ];
    }
}