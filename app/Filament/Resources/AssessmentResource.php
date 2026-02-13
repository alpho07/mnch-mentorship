<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssessmentResource\Pages;
use App\Models\Assessment;
use App\Models\Facility;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\FiltersLayout;

class AssessmentResource extends Resource {

    protected static ?string $model = Assessment::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Assessments';
    protected static ?string $navigationGroup = 'Facility Assessment';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool {
        return auth()->user()->hasRole(['super_admin', 'admin', 'Assessor']);
    }

    public static function canAccess(): bool {
        return auth()->user()->hasRole(['super_admin', 'admin', 'Assessor']);
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

    public static function form(Form $form): Form {
        return $form; // Empty — pages define their own forms
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('facility.name')
                            ->label('Facility')
                            ->searchable()
                            ->sortable()
                            ->weight('bold')
                            ->icon('heroicon-m-building-office-2')
                            ->iconColor('primary'),
                            Tables\Columns\TextColumn::make('facility.mfl_code')
                            ->label('MFL Code')
                            ->searchable()
                            ->sortable()
                            ->badge()
                            ->color('gray'),
                            Tables\Columns\BadgeColumn::make('assessment_type')
                            ->label('Type')
                            ->sortable()
                            ->colors([
                                'primary' => 'baseline',
                                'success' => 'midline',
                                'warning' => 'endline',
                            ])
                            ->icons([
                                'heroicon-m-flag' => 'baseline',
                                'heroicon-m-arrow-trending-up' => 'midline',
                                'heroicon-m-check-circle' => 'endline',
                            ]),
                            Tables\Columns\TextColumn::make('assessment_date')
                            ->label('Date')
                            ->date('M d, Y')
                            ->sortable()
                            ->icon('heroicon-m-calendar')
                            ->iconColor('info'),
                            Tables\Columns\TextColumn::make('assessor_name')
                            ->label('Assessor')
                            ->searchable()
                            ->sortable()
                            ->icon('heroicon-m-user')
                            ->limit(20),
                            Tables\Columns\BadgeColumn::make('status')
                            ->label('Status')
                            ->sortable()
                            ->colors([
                                'gray' => 'draft',
                                'warning' => 'in_progress',
                                'success' => 'completed',
                                'danger' => 'rejected',
                            ])
                            ->icons([
                                'heroicon-m-pencil' => 'draft',
                                'heroicon-m-clock' => 'in_progress',
                                'heroicon-m-check-badge' => 'completed',
                                'heroicon-m-x-circle' => 'rejected',
                            ]),
                            Tables\Columns\TextColumn::make('progress')
                            ->label('Progress')
                            ->getStateUsing(function ($record) {
                                $progress = $record->section_progress ?? [];
                                $completed = count(array_filter($progress));
                                $total = count($progress);
                                return $total > 0 ? round(($completed / $total) * 100) . '%' : '0%';
                            })
                            ->badge()
                            ->color(fn($state) => match (true) {
                                        $state === '100%' => 'success',
                                        (int) str_replace('%', '', $state) >= 50 => 'warning',
                                        default => 'danger',
                                    }),
                            Tables\Columns\TextColumn::make('created_at')
                            ->label('Created')
                            ->dateTime('M d, Y')
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            SelectFilter::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'rejected' => 'Rejected',
                            ])
                            ->multiple()
                            ->placeholder('All Statuses'),
                            SelectFilter::make('assessment_type')
                            ->label('Assessment Type')
                            ->options([
                                'baseline' => 'Baseline',
                                'midline' => 'Midline',
                                'endline' => 'Endline',
                            ])
                            ->multiple()
                            ->placeholder('All Types'),
                            SelectFilter::make('facility_id')
                            ->label('Facility')
                            ->relationship('facility', 'name')
                            ->searchable()
                            ->preload()
                            ->multiple()
                            ->placeholder('All Facilities'),
                            Filter::make('assessment_date')
                            ->form([
                                \Filament\Forms\Components\DatePicker::make('from')
                                ->label('From Date'),
                                \Filament\Forms\Components\DatePicker::make('until')
                                ->label('Until Date'),
                            ])
                            ->query(function (Builder $query, array $data): Builder {
                                return $query
                                                ->when(
                                                        $data['from'],
                                                        fn(Builder $query, $date): Builder => $query->whereDate('assessment_date', '>=', $date),
                                                )
                                                ->when(
                                                        $data['until'],
                                                        fn(Builder $query, $date): Builder => $query->whereDate('assessment_date', '<=', $date),
                                                );
                            })
                            ->indicateUsing(function (array $data): array {
                                $indicators = [];
                                if ($data['from'] ?? null) {
                                    $indicators[] = Tables\Filters\Indicator::make('From ' . \Carbon\Carbon::parse($data['from'])->toFormattedDateString())
                                            ->removeField('from');
                                }
                                if ($data['until'] ?? null) {
                                    $indicators[] = Tables\Filters\Indicator::make('Until ' . \Carbon\Carbon::parse($data['until'])->toFormattedDateString())
                                            ->removeField('until');
                                }
                                return $indicators;
                            }),
                            Filter::make('completed')
                            ->label('Completed Assessments')
                            ->query(fn(Builder $query): Builder => $query->where('status', 'completed'))
                            ->toggle(),
                            Filter::make('in_progress')
                            ->label('In Progress')
                            ->query(fn(Builder $query): Builder => $query->where('status', 'in_progress'))
                            ->toggle(),
                            Filter::make('recent')
                            ->label('Last 30 Days')
                            ->query(fn(Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(30)))
                            ->toggle(),
                                ], layout: FiltersLayout::AboveContent)
                        ->filtersFormColumns(3)
                        ->actions([
                            Tables\Actions\ActionGroup::make([
                                Tables\Actions\Action::make('dashboard')
                                ->label('Continue Assessment')
                                ->icon('heroicon-o-arrow-right-circle')
                                ->url(fn($record) => static::getUrl('dashboard', ['record' => $record]))
                                ->color('primary'),
                                Tables\Actions\Action::make('view_summary')
                                ->label('View Summary')
                                ->icon('heroicon-o-eye')
                                ->color('info')
                                ->url(fn($record) => AssessmentResource::getUrl('summary', ['record' => $record])),
                                Tables\Actions\Action::make('download')
                                ->label('Download Report')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('success')
                                ->action(function ($record) {
                                    // Add download logic here
                                })
                                ->visible(fn($record) => $record->status === 'completed'),
//                                Tables\Actions\DeleteAction::make()
//                                ->requiresConfirmation(),
                            ])
                            ->label('Actions')
                            ->icon('heroicon-m-ellipsis-vertical')
                            ->size('sm')
                            ->color('gray')
                            ->button(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\BulkAction::make('mark_in_progress')
                                ->label('Mark as In Progress')
                                ->icon('heroicon-o-clock')
                                ->color('warning')
                                ->action(fn($records) => $records->each->update(['status' => 'in_progress']))
                                ->deselectRecordsAfterCompletion()
                                ->requiresConfirmation(),
                                Tables\Actions\BulkAction::make('mark_completed')
                                ->label('Mark as Completed')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->action(fn($records) => $records->each->update([
                                            'status' => 'completed',
                                            'completed_at' => now(),
                                            'completed_by' => auth()->id(),
                                        ]))
                                ->deselectRecordsAfterCompletion()
                                ->requiresConfirmation(),
                                Tables\Actions\DeleteBulkAction::make()
                                ->requiresConfirmation(),
                            ]),
                        ])
                        ->emptyStateHeading('No assessments yet')
                        ->emptyStateDescription('Start by creating your first facility assessment.')
                        ->emptyStateIcon('heroicon-o-clipboard-document-check')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make()
                            ->label('Create Assessment')
                            ->icon('heroicon-o-plus'),
                        ])
                        ->defaultSort('created_at', 'desc')
                        ->striped()
                        ->poll('30s'); // Auto-refresh every 30 seconds
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListAssessments::route('/'),
            'create' => Pages\CreateAssessment::route('/create'),
            'dashboard' => Pages\AssessmentDashboard::route('/{record}/dashboard'),
            'edit-infrastructure' => Pages\EditInfrastructure::route('/{record}/infrastructure'),
            'edit-skills-lab' => Pages\EditSkillsLab::route('/{record}/skills-lab'),
            'edit-human-resources' => Pages\EditHumanResources::route('/{record}/human-resources'),
            'edit-health-products' => Pages\EditHealthProducts::route('/{record}/health-products'),
            'edit-information-systems' => Pages\EditInformationSystems::route('/{record}/information-systems'),
            'edit-quality-of-care' => Pages\EditQualityOfCare::route('/{record}/quality-of-care'),
            'summary' => Pages\ViewAssessmentSummary::route('/{record}/summary'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        return static::getModel()::where('status', 'in_progress')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string {
        return 'warning';
    }
}
