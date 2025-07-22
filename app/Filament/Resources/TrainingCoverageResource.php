<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainingCoverageResource\Pages;
use App\Models\Training;
use App\Traits\HasTrainingFilters;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrainingCoverageResource extends Resource
{
    use HasTrainingFilters;

    protected static ?string $model = Training::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Training Coverage';
    protected static ?string $navigationGroup = 'Analytics & Reports';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // This resource is primarily for viewing/analytics
            Forms\Components\Placeholder::make('info')
                ->content('This section is for analytics and reporting. Use the Training Management section to create/edit trainings.')
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Training Title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('program.name')
                    ->label('Program')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('facility.name')
                    ->label('Facility')
                    ->sortable()
                    ->formatStateUsing(fn($record) => $record->facility ?
                        "{$record->facility->name} ({$record->facility->mfl_code})" : 'N/A'),

                Tables\Columns\TextColumn::make('facility.subcounty.county.name')
                    ->label('County')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('organizer.name')
                    ->label('Organizer')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('participants_count')
                    ->label('Participants')
                    ->counts('participants')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('sessions_count')
                    ->label('Sessions')
                    ->counts('sessions')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('End Date')
                    ->date('M d, Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('approach')
                    ->label('Approach')
                    ->colors([
                        'primary' => 'onsite',
                        'success' => 'virtual',
                        'warning' => 'hybrid',
                    ])
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                Tables\Columns\IconColumn::make('has_tot')
                    ->label('TOT')
                    ->boolean()
                    ->getStateUsing(fn($record) => $record->participants()->where('is_tot', true)->exists())
                    ->toggleable(),
            ])
            ->filters([
                // Period Filters with Tabs
                Tables\Filters\Filter::make('period_selector')
                    ->form([
                        Forms\Components\Tabs::make('period_tabs')
                            ->tabs([
                                Forms\Components\Tabs\Tab::make('years_tab')
                                    ->label('Years')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('years')
                                            ->label('')
                                            ->options(fn() => static::getAvailableYears())
                                            ->columns(4)
                                            ->gridDirection('row'),
                                    ]),

                                Forms\Components\Tabs\Tab::make('quarters_tab')
                                    ->label('Quarters')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('quarters')
                                            ->label('')
                                            ->options(fn() => static::getAvailableQuarters())
                                            ->columns(3)
                                            ->gridDirection('row'),
                                    ]),

                                Forms\Components\Tabs\Tab::make('months_tab')
                                    ->label('Months')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('months')
                                            ->label('')
                                            ->options(fn() => static::getAvailableMonths())
                                            ->columns(4)
                                            ->gridDirection('row'),
                                    ]),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        static::applyNonGeographicFilters($query, $data);
                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (!empty($data['years'])) {
                            $indicators[] = 'Years: ' . implode(', ', $data['years']);
                        }

                        if (!empty($data['quarters'])) {
                            $quarters = array_map(function ($q) {
                                return str_replace('-', ' ', $q);
                            }, $data['quarters']);
                            $indicators[] = 'Quarters: ' . implode(', ', $quarters);
                        }

                        if (!empty($data['months'])) {
                            $months = array_map(function ($m) {
                                [$year, $month] = explode('-', $m);
                                return \Carbon\Carbon::createFromDate($year, $month, 1)->format('M Y');
                            }, $data['months']);
                            $indicators[] = 'Months: ' . implode(', ', $months);
                        }

                        return $indicators;
                    }),

                // Geographic Cascading Filters
                Tables\Filters\SelectFilter::make('counties')
                    ->label('Counties')
                    ->multiple()
                    ->searchable()
                    ->options(fn() => static::getAvailableCounties())
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['values'])) {
                            static::applyGeographicFilters($query, ['counties' => $data['values']]);
                        }
                        return $query;
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (empty($data['values'])) return null;
                        $counties = \App\Models\County::whereIn('id', $data['values'])->pluck('name')->toArray();
                        return 'Counties: ' . implode(', ', $counties);
                    }),

                Tables\Filters\SelectFilter::make('subcounties')
                    ->label('Subcounties')
                    ->multiple()
                    ->searchable()
                    ->options(function () {
                        // Get current county filter values
                        $counties = request()->input('tableFilters.counties.values', []);
                        return static::getAvailableSubcounties(['counties' => $counties]);
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['values'])) {
                            static::applyGeographicFilters($query, ['subcounties' => $data['values']]);
                        }
                        return $query;
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (empty($data['values'])) return null;
                        $subcounties = \App\Models\Subcounty::whereIn('id', $data['values'])->pluck('name')->toArray();
                        return 'Subcounties: ' . implode(', ', $subcounties);
                    }),

                Tables\Filters\SelectFilter::make('facilities')
                    ->label('Facilities')
                    ->multiple()
                    ->searchable()
                    ->options(function () {
                        // Get current geographic filter values
                        $counties = request()->input('tableFilters.counties.values', []);
                        $subcounties = request()->input('tableFilters.subcounties.values', []);
                        return static::getAvailableFacilities([
                            'counties' => $counties,
                            'subcounties' => $subcounties
                        ]);
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['values'])) {
                            $query->whereIn('facility_id', $data['values']);
                        }
                        return $query;
                    }),

                Tables\Filters\SelectFilter::make('facility_types')
                    ->label('Facility Types')
                    ->multiple()
                    ->options(fn() => static::getAvailableFacilityTypes())
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['values'])) {
                            static::applyGeographicFilters($query, ['facility_types' => $data['values']]);
                        }
                        return $query;
                    }),

                // Program and Training Filters
                Tables\Filters\SelectFilter::make('programs')
                    ->label('Programs')
                    ->multiple()
                    ->options(fn() => static::getAvailablePrograms())
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['values'])) {
                            $query->whereIn('program_id', $data['values']);
                        }
                        return $query;
                    }),

                Tables\Filters\SelectFilter::make('approaches')
                    ->label('Training Approach')
                    ->multiple()
                    ->options([
                        'onsite' => 'Onsite',
                        'virtual' => 'Virtual',
                        'hybrid' => 'Hybrid',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['values'])) {
                            $query->whereIn('approach', $data['values']);
                        }
                        return $query;
                    }),

                // Participant Filters
                Tables\Filters\SelectFilter::make('cadres')
                    ->label('Cadres')
                    ->multiple()
                    ->options(fn() => static::getAvailableCadres())
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['values'])) {
                            static::applyParticipantFilters($query, ['cadres' => $data['values']]);
                        }
                        return $query;
                    }),

                Tables\Filters\SelectFilter::make('departments')
                    ->label('Departments')
                    ->multiple()
                    ->options(fn() => static::getAvailableDepartments())
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['values'])) {
                            static::applyParticipantFilters($query, ['departments' => $data['values']]);
                        }
                        return $query;
                    }),

                Tables\Filters\SelectFilter::make('organizers')
                    ->label('Organizers')
                    ->multiple()
                    ->searchable()
                    ->options(fn() => static::getAvailableOrganizers())
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['values'])) {
                            $query->whereIn('organizer_id', $data['values']);
                        }
                        return $query;
                    }),

                // Special Filters
                Tables\Filters\Filter::make('has_tot')
                    ->label('Has TOT Participants')
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->whereHas('participants', fn($q) => $q->where('is_tot', true))
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('start_from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('start_until')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['start_from'],
                                fn(Builder $q, $date): Builder => $q->whereDate('start_date', '>=', $date)
                            )
                            ->when(
                                $data['start_until'],
                                fn(Builder $q, $date): Builder => $q->whereDate('start_date', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['start_from']) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['start_from'])->toDateString();
                        }
                        if ($data['start_until']) {
                            $indicators[] = 'To: ' . \Carbon\Carbon::parse($data['start_until'])->toDateString();
                        }
                        return $indicators;
                    }),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->persistFiltersInSession()
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View Details'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Export actions can be added here
                ]),
            ])
            ->defaultSort('start_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll('30s'); // Auto-refresh every 30 seconds
    }

    public static function getRelations(): array
    {
        return [
            // Add relation managers if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainingCoverages::route('/'),
            'view' => Pages\ViewTrainingCoverage::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'program:id,name',
                'facility:id,name,mfl_code,subcounty_id,facility_type_id',
                'facility.subcounty:id,name,county_id',
                'facility.subcounty.county:id,name',
                'facility.facilityType:id,name',
                'organizer:id,name',
                'participants:id,training_id,name,cadre_id,is_tot',
                'participants.cadre:id,name',
                'sessions:id,training_id,name',
                'departments:id,name'
            ]);
    }

    // Additional helper methods
    public static function canCreate(): bool
    {
        return false; // This is an analytics resource
    }

    public static function canEdit($record): bool
    {
        return false; // This is an analytics resource
    }

    public static function canDelete($record): bool
    {
        return false; // This is an analytics resource
    }

    public static function canDeleteAny(): bool
    {
        return false; // This is an analytics resource
    }
}
