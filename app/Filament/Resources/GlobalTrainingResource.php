<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GlobalTrainingResource\Pages;
use App\Models\Training;
use App\Models\Program;
use App\Models\User;
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

class GlobalTrainingResource extends Resource
{
    protected static ?string $model = Training::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Trainings';

    protected static ?string $navigationGroup = 'Training Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'global-trainings';

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

                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                TextInput::make('identifier')
                                    ->label('Training ID')
                                    ->required()
                                    ->readOnly(true)
                                    ->unique(Training::class, ignoreRecord: true)
                                    ->maxLength(50)
                                    ->default(fn($record) => $record?->identifier ?? strtoupper('TRN-' . Str::random(8)))
                                    ->disabled(fn($record) => filled($record?->identifier))
                                    ->dehydrated(true),

                                Forms\Components\Select::make('status')
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
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('location')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\DatePicker::make('start_date')
                                    ->required()
                                    ->native(false),

                                Forms\Components\DatePicker::make('end_date')
                                    ->required()
                                    ->native(false),
                            ]),
                    ]),

                Forms\Components\Section::make('Organization')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('organizer_id')
                                    ->label('Organizer')
                                    ->relationship('organizer', 'first_name')
                                    ->getOptionLabelFromRecordUsing(fn(User $record): string => "{$record->full_name} - {$record->facility?->name}")
                                    ->searchable(['first_name', 'last_name', 'email'])
                                    ->required()
                                    ->preload(),

                                Forms\Components\TextInput::make('max_participants')
                                    ->numeric()
                                    //->min(1)
                                    ->default(30),
                            ]),

                        /*Forms\Components\DatePicker::make('registration_deadline')
                            ->native(false),

                        Forms\Components\TextInput::make('target_audience')
                            ->maxLength(255),*/
                    ]),

                Forms\Components\Section::make('Content Structure')
                    ->schema([
                        Forms\Components\Select::make('programs')
                            ->label('Programs')
                            ->multiple()
                            ->relationship('programs', 'name')
                            ->preload()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                // Clear modules when programs change
                                $set('modules', []);
                            }),

                        Forms\Components\Select::make('modules')
                            ->label('Modules')
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
                            ->helperText('Modules are grouped by their parent program'),

                        Forms\Components\Select::make('methodologies')
                            ->label('Training Methodologies')
                            ->multiple()
                            ->relationship('methodologies', 'name')
                            ->preload()
                            ->searchable()
                            ->helperText('Select the training methods that will be used'),

                        Forms\Components\Textarea::make('learning_outcomes')
                            ->label('Learning Outcomes')
                            ->rows(3),

                        Forms\Components\Textarea::make('prerequisites')
                            ->rows(2),

                        Forms\Components\TagsInput::make('training_approaches')
                            ->suggestions([
                                'Lecture',
                                'Practical Sessions',
                                'Group Work',
                                'Case Studies',
                                'Simulation',
                                'Hands-on Practice',
                            ]),
                    ]),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->rows(3),
                    ])
                    ->collapsible(),
            ]);
    }

    // Add this method to ensure type is always set when creating/updating
    protected static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'global_training';
        return $data;
    }

    protected static function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = 'global_training';
        return $data;
    }


    public static function table(Table $table): Table
    {
        return $table
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

                TextColumn::make('programs.name')
                    ->label('Programs')
                    ->badge()
                    ->separator(', ')
                    ->limit(30)
                    ->color('info'),

                TextColumn::make('organizer.full_name')
                    ->label('Organizer')
                    ->searchable(['first_name', 'last_name'])
                    ->description(
                        fn(Training $record): string =>
                        $record->organizer?->facility?->name ?? ''
                    ),

                TextColumn::make('location')
                    ->searchable()
                    ->limit(25)
                    ->tooltip(function (TextColumn $column): ?string {
                        return $column->getState();
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


                /*TextColumn::make('completion_rate')
                    ->label('Progress')
                    ->getStateUsing(fn(Training $record): string => number_format($record->completion_rate, 1) . '%')
                    ->sortable()
                    ->badge()
                    ->color(fn(string $state): string => match (true) {
                        (float) $state >= 80 => 'success',
                        (float) $state >= 60 => 'warning',
                        default => 'danger',
                    }),*/
            ])
            ->filters([
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

                SelectFilter::make('organizer')
                    ->relationship('organizer', 'first_name')
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
                            $newTraining->type = 'global_training'; // Ensure type is set
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
            ->where('status', 'ongoing')
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
