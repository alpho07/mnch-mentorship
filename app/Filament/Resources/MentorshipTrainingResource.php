<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MentorshipResource\Pages;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\Training;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MentorshipTrainingResource extends Resource {

    protected static ?string $model = Training::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Mentorships';
    protected static ?string $navigationGroup = 'Training Management';
    protected static ?string $slug = 'mentorship';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->hasRole(['super_admin', 'admin', 'division', 'facility_mentor', 'national_mentor']);
    }

    public static function canAccess(): bool {
        return auth()->check() && auth()->user()->hasRole(['super_admin', 'admin', 'division', 'facility_mentor', 'national_mentor']);
    }

    public static function canCreate(): bool {
        return static::canAccess();
    }

    public static function canEdit($record): bool {
        return static::canAccess();
    }

    public static function canDelete($record): bool {
        return static::canAccess();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Query scoping
    // ─────────────────────────────────────────────────────────────────────────

    public static function getEloquentQuery(): Builder {
        $query = parent::getEloquentQuery()
                ->withoutGlobalScopes([
                    \Illuminate\Database\Eloquent\SoftDeletingScope::class,
                ])
                ->where('type', 'facility_mentorship')
                ->with(['facility', 'program', 'county', 'mentor']);

        $user = auth()->user();

        if (!$user->hasRole(['super_admin', 'admin', 'division'])) {
            $query->where('mentor_id', $user->id);
        }

        return $query;
    }

    public static function getNavigationLabel(): string {
        return 'Mentorship';
    }

    public static function getBreadcrumb(): string {
        return 'Mentorship';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FORM
    // ─────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form {
        return $form->schema([
                    // Auto-set hidden fields — mentor is always the logged-in user
                    Forms\Components\Hidden::make('type')->default('facility_mentorship'),
                    Forms\Components\Hidden::make('mentor_id')->default(fn() => auth()->id()),
                    Section::make('Mentorship Preparation')
                            ->description('Use these steps before starting classes or sending attendance links.')
                            ->icon('heroicon-o-light-bulb')
                            ->schema([
                                Forms\Components\Placeholder::make('preparation_note')
                                ->label('')
                                ->content(new \Illuminate\Support\HtmlString('
                                    <div class="space-y-3 text-sm text-gray-700 dark:text-gray-300">
                                        <ol class="list-decimal space-y-1 pl-5">
                                            <li>Identify facility gaps first and decide what this mentorship should address.</li>
                                            <li>Review the mentorship manuals before choosing modules and sessions.</li>
                                            <li>Confirm every mentee has a name, email, phone, cadre, and department.</li>
                                        </ol>
                                        <div class="flex flex-wrap gap-2 pt-1">
                                            <a class="text-primary-600 hover:underline" href="' . url('/resources/infant-child-mentorship-manual') . '" target="_blank" rel="noopener noreferrer">Infant and Child Mentorship Manual</a>
                                            <span class="text-gray-400">|</span>
                                            <a class="text-primary-600 hover:underline" href="https://mnchkenyamentorship.org/resources/newborn-mentorship-mentors-manual" target="_blank" rel="noopener noreferrer">Newborn Mentorship Mentor\'s Manual</a>
                                            <span class="text-gray-400">|</span>
                                            <a class="text-primary-600 hover:underline" href="' . route('resources.search', ['q' => 'mentorship manual']) . '" target="_blank" rel="noopener noreferrer">Search mentorship manuals</a>
                                            <span class="text-gray-400">|</span>
                                            <a class="text-primary-600 hover:underline" href="' . route('resources.search', ['q' => 'MNCH guidelines']) . '" target="_blank" rel="noopener noreferrer">MNCH guidelines</a>
                                        </div>
                                    </div>
                                ')),
                            ]),
                    // ── Section 1: Location ───────────────────────────────────────
                    Section::make('Location')
                            ->description('Where is this mentorship being conducted?')
                            ->icon('heroicon-o-map-pin')
                            ->columns(2)
                            ->schema([
                                Select::make('county_id')
                                ->label('County')
                                ->relationship('county', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn(Set $set) => $set('facility_id', null))
                                ->prefixIcon('heroicon-o-map')
                                ->helperText('Select the county first'),
                                Select::make('facility_id')
                                ->label('Facility')
                                ->options(function (Get $get) {
                                    $countyId = $get('county_id');
                                    if (!$countyId)
                                        return [];

                                    return Facility::whereHas('subcounty', fn($q) =>
                                                    $q->where('county_id', $countyId)
                                            )->get()->mapWithKeys(fn($f) => [
                                                $f->id => "{$f->mfl_code} — {$f->name}",
                                    ]);
                                })
                                ->searchable()
                                ->required()
                                ->disabled(fn(Get $get) => !$get('county_id'))
                                ->prefixIcon('heroicon-o-building-office-2')
                                ->helperText('Facilities load after selecting a county'),
                            ]),
                    // ── Section 2: Program & Schedule ─────────────────────────────
                    Section::make('Program & Schedule')
                            ->description('What program is being mentored and when?')
                            ->icon('heroicon-o-calendar-days')
                            ->schema([
                                Select::make('program_id')
                                ->label('Mentorship Program')
                                ->relationship('program', 'name')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->prefixIcon('heroicon-o-book-open')
                                ->helperText('e.g. Newborn Care or Infant & Child Care')
                                ->columnSpanFull(),
                                Grid::make(3)->schema([
                                    DatePicker::make('start_date')
                                    ->label('Start Date')
                                    ->required()
                                    ->native(false)
                                    ->minDate(today())
                                    ->displayFormat('M j, Y')
                                    ->prefixIcon('heroicon-o-play'),
                                    DatePicker::make('end_date')
                                    ->label('End Date')
                                    ->required()
                                    ->native(false)
                                    ->minDate(fn(Get $get) => $get('start_date') ?? now())
                                    ->after('start_date')
                                    ->displayFormat('M j, Y')
                                    ->prefixIcon('heroicon-o-stop'),
                                    TextInput::make('max_participants')
                                    ->label('Number of Mentees')
                                    ->numeric()
                                    ->default(20)
                                    ->suffix('mentees')
                                    ->prefixIcon('heroicon-o-users')
                                    ->helperText('Recommended minimum: 2. No maximum limit is enforced.'),
                                ]),
                            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TABLE
    // ─────────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            // Mentorship Code
                            Tables\Columns\TextColumn::make('identifier')
                            ->label('Code')
                            ->searchable()
                            ->sortable()
                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                            ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                            ->copyable()
                            ->copyMessage('Code copied')
                            ->default('—')
                            ->color('primary'),
                            // Program (badge)
                            Tables\Columns\TextColumn::make('program.name')
                            ->label('Program')
                            ->searchable()
                            ->sortable()
                            ->badge()
                            ->color('success'),
                            // Facility with MFL code + county as sub-description
                            Tables\Columns\TextColumn::make('facility.name')
                            ->label('Facility')
                            ->searchable()
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn($state, Training $record): string =>
                                    $record->facility?->mfl_code ? "{$record->facility->mfl_code} — {$state}" : ($state ?? '—')
                            )
                            ->description(fn(Training $record): string =>
                                    $record->county?->name ?? ''
                            ),
                            // Mentor
                            Tables\Columns\TextColumn::make('mentor.name')
                            ->label('Lead Mentor')
                            ->searchable()
                            ->description(fn(Training $record): string =>
                                    $record->mentor?->cadre?->name ?? ''
                            )
                            ->toggleable(),
                            // Dates — start with "to {end}" description
                            Tables\Columns\TextColumn::make('start_date')
                            ->label('Period')
                            ->date('M j, Y')
                            ->sortable()
                            ->description(fn(Training $record): string =>
                                    $record->end_date ? 'to ' . \Carbon\Carbon::parse($record->end_date)->format('M j, Y') : ''
                            ),
                            // Active classes count (badge)
                            Tables\Columns\TextColumn::make('active_classes')
                            ->label('Active Classes')
                            ->getStateUsing(fn(Training $r) =>
                                    $r->mentorshipClasses()->count()
                            )
                            ->badge()
                            ->color(fn($state) => $state > 0 ? 'warning' : 'gray')
                            ->alignCenter(),
                            // Mentees (distinct users across all classes)
                            Tables\Columns\TextColumn::make('mentees_count')
                            ->label('Mentees')
                            ->getStateUsing(fn(Training $r) =>
                                    ClassParticipant::whereHas('mentorshipClass',
                                            fn($q) => $q->where('training_id', $r->id)
                                    )->distinct('user_id')->count('user_id')
                            )
                            ->badge()
                            ->color('primary')
                            ->alignCenter(),
                            // Status
                            Tables\Columns\TextColumn::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                        'active' => 'success',
                                        'draft' => 'gray',
                                        'completed' => 'warning',
                                        'cancelled' => 'danger',
                                        default => 'gray',
                                    }),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'active' => 'Active',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ]),
                            Tables\Filters\SelectFilter::make('program_id')
                            ->label('Program')
                            ->relationship('program', 'name'),
                        ])
                        ->actions([
                            Tables\Actions\ActionGroup::make([
                                Tables\Actions\Action::make('manage_classes')
                                ->label('Manage Classes')
                                ->icon('heroicon-o-rectangle-stack')
                                ->color('primary')
                                ->url(fn(Training $r) => static::getUrl('classes', ['record' => $r->id])),
                                Tables\Actions\Action::make('co_mentors')
                                ->label('Co-Mentors')
                                ->icon('heroicon-o-user-group')
                                ->color('info')
                                ->url(fn(Training $r) => static::getUrl('co-mentors', ['record' => $r->id])),
                                Tables\Actions\Action::make('mentees')
                                ->label('Mentees')
                                ->icon('heroicon-o-users')
                                ->color('success')
                                ->url(fn(Training $r) => static::getUrl('mentees', ['record' => $r->id])),
                                Tables\Actions\EditAction::make(),
                                Tables\Actions\ViewAction::make(),
                            ]),
                        ])
                        ->defaultSort('created_at', 'desc')
                        ->emptyStateHeading('No Mentorships Yet')
                        ->emptyStateDescription('Create your first mentorship program to get started.')
                        ->emptyStateIcon('heroicon-o-academic-cap')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make()->label('Create Mentorship'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pages
    // ─────────────────────────────────────────────────────────────────────────

    public static function getPages(): array {
        return [
            'index' => Pages\ListMentorshipTrainings::route('/'),
            'create' => Pages\CreateMentorshipTraining::route('/create'),
            'view' => Pages\ViewMentorshipTraining::route('/{record}'),
            'edit' => Pages\EditMentorshipTraining::route('/{record}/edit'),
            'classes' => Pages\ManageMentorshipClasses::route('/{record}/classes'),
            'mentees' => Pages\ManageMentorshipMentees::route('/{record}/mentees'),
            'co-mentors' => Pages\ManageMentorshipCoMentors::route('/{record}/co-mentors'),
            'class-modules' => Pages\ManageClassModules::route('/{training}/classes/{class}/modules'),
            'class-mentees' => Pages\ManageClassMentees::route('/{training}/classes/{class}/mentees'),
            'module-sessions' => Pages\ManageModuleSessions::route('/{training}/classes/{class}/modules/{module}/sessions'),
            'module-mentees' => Pages\ManageModuleMentees::route('/{training}/classes/{class}/modules/{module}/mentees'),
            'module-summary' => Pages\ModuleSummary::route('/{training}/classes/{class}/modules/{module}/summary'),
            //'mentee-dashboard' => Pages\MenteeDashboard::route('/mentee-dashboard'),
            'mentee-progress' => Pages\MenteeProgress::route('/{record}/participants/{participant}/progress'),
            'attendance-report' => Pages\AttendanceReportPage::route('/attendance-report'),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Navigation badge
    // ─────────────────────────────────────────────────────────────────────────

    public static function getNavigationBadge(): ?string {
        $query = static::getModel()::where('type', 'facility_mentorship');
        //  ->whereHas('mentorshipClasses');

        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'admin', 'division'])) {
            $query->where('mentor_id', $user->id);
        }

        $count = $query->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string {
        return 'success';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private static function mentorOptionLabel(User $u): string {
        return implode(' · ', array_filter([
            $u->name ?? trim("{$u->first_name} {$u->last_name}"),
            $u->cadre?->name,
            $u->facility?->name,
        ]));
    }

    private static function buildDisplayName(Get $get): string {
        return trim(implode(' ', array_filter([
            $get('first_name'),
            $get('middle_name'),
            $get('last_name'),
        ])));
    }
}
