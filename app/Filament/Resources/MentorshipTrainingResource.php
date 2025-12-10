<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MentorshipTrainingResource\Pages;
use App\Models\Training;
use App\Models\Facility;
use App\Models\County;
use App\Models\Program;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;

class MentorshipTrainingResource extends Resource {

    protected static ?string $model = Training::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Mentorships';
    protected static ?string $navigationGroup = 'Training Management';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'mentorships';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $breadcrumb = 'Mentorships';

    public static function getEloquentQuery(): Builder {
        return parent::getEloquentQuery()
                        ->where('type', 'facility_mentorship')
                        ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Form $form): Form {
        return $form->schema([
                    Forms\Components\Hidden::make('type')->default('facility_mentorship'),
                    Forms\Components\Hidden::make('mentor_id')->default(auth()->id()),
                            Section::make('Mentorship Information')
                            ->description('Select county, facility, and training program')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('county_id')
                                    ->label('County')
                                    ->relationship('county', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('facility_id', null);
                                    })
                                    ->helperText('Select the county first'),
                                    Select::make('facility_id')
                                    ->label('Facility')
                                    ->options(function (Get $get) {
                                        $countyId = $get('county_id');
                                        if (!$countyId) {
                                            return [];
                                        }

                                        return Facility::whereHas('subcounty', function ($query) use ($countyId) {
                                                    $query->where('county_id', $countyId);
                                                })->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->required()
                                    ->disabled(fn(Get $get) => !$get('county_id'))
                                    ->helperText('Facilities will appear after selecting county'),
                                ]),
                                Select::make('program_id')
                                ->label('Training Program')
                                ->relationship('program', 'name')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->helperText('Select Newborn Care or Infant and Child Care program')
                                ->columnSpanFull(),
                                Grid::make(3)->schema([
                                    DatePicker::make('start_date')
                                    ->label('Start Date')
                                    ->required()
                                    ->native(false)
                                    ->minDate(now())
                                    ->displayFormat('M j, Y'),
                                    DatePicker::make('end_date')
                                    ->label('End Date')
                                    ->required()
                                    ->native(false)
                                    ->after('start_date')
                                    ->displayFormat('M j, Y'),
                                    TextInput::make('max_participants')
                                    ->label('Maximum Mentees')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->default(20)
                                    ->suffix('mentees')
                                    ->helperText('Recommended: 15-30 mentees'),
                                ]),
                            ]),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->query(static::getEloquentQuery()->with(['facility', 'program', 'county', 'mentor']))
                        ->columns([
                            TextColumn::make('identifier')
                            ->label('ID')
                            ->searchable()
                            ->sortable()
                            ->weight('bold')
                            ->default('N/A'),
                            TextColumn::make('program.name')
                            ->label('Program')
                            ->searchable()
                            ->sortable()
                            ->badge()
                            ->color('success'),
                            TextColumn::make('facility.name')
                            ->label('Facility')
                            ->searchable()
                            ->badge()
                            ->color('info')
                            ->description(fn(Training $record): string =>
                                    $record->county?->name ?? ''
                            ),
                            TextColumn::make('county.name')
                            ->label('County')
                            ->searchable()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                            TextColumn::make('start_date')
                            ->date('M j, Y')
                            ->sortable()
                            ->description(fn(Training $record): string =>
                                    $record->end_date ? 'to ' . $record->end_date->format('M j, Y') : ''
                            ),
                            TextColumn::make('mentor.full_name')
                            ->label('Lead Mentor')
                            ->searchable(['first_name', 'last_name'])
                            ->toggleable(),
                            TextColumn::make('classes_count')
                            ->label('Classes')
                            ->counts('mentorshipClasses')
                            ->badge()
                            ->color('warning'),
                            TextColumn::make('participants_count')
                            ->label('Mentees')
                            ->counts('participants')
                            ->badge()
                            ->color('success'),
                            TextColumn::make('created_at')
                            ->label('Created')
                            ->dateTime('M j, Y')
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            SelectFilter::make('program')
                            ->relationship('program', 'name')
                            ->multiple()
                            ->preload(),
                            SelectFilter::make('county')
                            ->relationship('county', 'name')
                            ->multiple()
                            ->preload(),
                            SelectFilter::make('facility')
                            ->relationship('facility', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                        ])
                        ->actions([
                            ActionGroup::make([
                                Tables\Actions\ViewAction::make()
                                ->color('info'),
                                Tables\Actions\EditAction::make()
                                ->color('warning'),
                                Action::make('manage_classes')
                                ->label('Manage Classes')
                                ->icon('heroicon-o-academic-cap')
                                ->color('success')
                                ->url(fn(Training $record): string =>
                                        static::getUrl('classes', ['record' => $record])
                                ),
                                Action::make('manage_mentees')
                                ->label('Manage Mentees')
                                ->icon('heroicon-o-users')
                                ->color('primary')
                                ->url(fn(Training $record): string =>
                                        static::getUrl('mentees', ['record' => $record])
                                ),
                                Action::make('co_mentors')
                                ->label('Co-Mentors')
                                ->icon('heroicon-o-user-group')
                                ->color('info')
                                ->url(fn(Training $record): string =>
                                        static::getUrl('co-mentors', ['record' => $record])
                                ),
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
                            ]),
                        ])
                        ->defaultSort('created_at', 'desc')
                        ->poll('30s')
                        ->striped()
                        ->emptyStateHeading('No Mentorship Programs Found')
                        ->emptyStateDescription('Create your first facility-based mentorship program.')
                        ->emptyStateIcon('heroicon-o-academic-cap')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make()
                            ->label('Create Mentorship Program')
                            ->icon('heroicon-o-plus'),
        ]);
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListMentorshipTrainings::route('/'),
            'create' => Pages\CreateMentorshipTraining::route('/create'),
            'view' => Pages\ViewMentorshipTraining::route('/{record}'),
            'edit' => Pages\EditMentorshipTraining::route('/{record}/edit'),
            'classes' => Pages\ManageMentorshipClasses::route('/{record}/classes'),
            'mentees' => Pages\ManageMentorshipMentees::route('/{record}/mentees'),
            'co-mentors' => Pages\ManageMentorshipCoMentors::route('/{record}/co-mentors'),
            'class-mentees' => Pages\ManageClassMentees::route('/{training}/classes/{class}/mentees'),
            'module-sessions' => Pages\ManageModuleSessions::route('/{training}/classes/{class}/modules/{module}/sessions'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        $count = static::getModel()::where('type', 'facility_mentorship')
                ->whereHas('mentorshipClasses', function ($query) {
                    $query->where('status', 'active');
                })
                ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null {
        return 'success';
    }
}
