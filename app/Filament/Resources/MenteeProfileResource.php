<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenteeProfileResource\Pages;
use App\Models\User;
use App\Models\MenteeStatusLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Support\Enums\FontWeight;

class MenteeProfileResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Mentee Profiles';

    protected static ?string $navigationGroup = 'Training Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'mentee-profiles';

    protected static ?string $recordTitleAttribute = 'full_name';

    // Only show users who have participated in mentorship trainings
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('trainingParticipations.training', function ($query) {
                $query->where('type', 'facility_mentorship');
            })
            ->with([
                'facility.subcounty.county',
                'department',
                'cadre',
                'statusLogs' => fn($q) => $q->latest('effective_date'),
                'trainingParticipations.training',
                'trainingParticipations.objectiveResults'
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Personal Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('full_name')
                                    ->label('Full Name')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),
                                    
                                TextEntry::make('phone')
                                    ->icon('heroicon-o-phone'),
                                    
                                TextEntry::make('email')
                                    ->icon('heroicon-o-envelope'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('facility.name')
                                    ->label('Primary Facility')
                                    ->badge()
                                    ->color('info'),
                                    
                                TextEntry::make('department.name')
                                    ->badge()
                                    ->color('success'),
                                    
                                TextEntry::make('cadre.name')
                                    ->badge()
                                    ->color('warning'),
                            ]),
                    ]),

                Section::make('Current Status & Performance')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('current_status')
                                    ->label('Current Status')
                                    ->badge()
                                    ->color(function ($state): string {
                                        return match($state) {
                                            'active' => 'success',
                                            'study_leave' => 'info',
                                            'transferred' => 'warning',
                                            'resigned', 'retired' => 'secondary',
                                            default => 'danger',
                                        };
                                    })
                                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state))),

                                TextEntry::make('overall_training_score')
                                    ->label('Overall Score')
                                    ->suffix('%')
                                    ->badge()
                                    ->color(function ($state): string {
                                        if (!$state) return 'gray';
                                        if ($state >= 85) return 'success';
                                        if ($state >= 70) return 'warning';
                                        return 'danger';
                                    })
                                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 1) . '%' : 'Not assessed'),

                                TextEntry::make('training_completion_rate')
                                    ->label('Completion Rate')
                                    ->suffix('%')
                                    ->badge()
                                    ->color(function ($state): string {
                                        if ($state >= 90) return 'success';
                                        if ($state >= 70) return 'warning';
                                        return 'danger';
                                    }),

                                TextEntry::make('performance_trend')
                                    ->badge()
                                    ->color(function ($state): string {
                                        return match($state) {
                                            'Improving' => 'success',
                                            'Stable' => 'info',
                                            'Declining' => 'danger',
                                            default => 'gray',
                                        };
                                    }),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('training_history_summary.total_trainings')
                                    ->label('Total Trainings')
                                    ->numeric(),

                                TextEntry::make('training_history_summary.completed')
                                    ->label('Completed')
                                    ->numeric(),

                                TextEntry::make('training_history_summary.passed')
                                    ->label('Passed')
                                    ->numeric(),
                            ]),
                    ]),

                Section::make('Training History')
                    ->schema([
                        RepeatableEntry::make('trainingParticipations')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('training.title')
                                            ->label('Training Program')
                                            ->weight(FontWeight::Medium),

                                        TextEntry::make('training.start_date')
                                            ->label('Date')
                                            ->date('M j, Y'),

                                        TextEntry::make('completion_status')
                                            ->badge()
                                            ->color(function ($state): string {
                                                return match($state) {
                                                    'completed' => 'success',
                                                    'in_progress' => 'warning',
                                                    default => 'gray',
                                                };
                                            }),

                                        TextEntry::make('overall_score')
                                            ->label('Score')
                                            ->getStateUsing(function ($record): string {
                                                $scores = $record->objectiveResults->pluck('score');
                                                if ($scores->isEmpty()) return 'Not assessed';
                                                return number_format($scores->avg(), 1) . '%';
                                            })
                                            ->badge()
                                            ->color(function ($record): string {
                                                $scores = $record->objectiveResults->pluck('score');
                                                if ($scores->isEmpty()) return 'gray';
                                                $avg = $scores->avg();
                                                if ($avg >= 80) return 'success';
                                                if ($avg >= 70) return 'warning';
                                                return 'danger';
                                            }),
                                    ]),
                            ])
                            ->contained(false),
                    ])
                    ->collapsible(),

                Section::make('Status History')
                    ->schema([
                        RepeatableEntry::make('statusLogs')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('new_status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn ($state) => match($state) {
                                                'active' => 'success',
                                                'study_leave' => 'info',
                                                'transferred' => 'warning',
                                                'resigned', 'retired' => 'secondary',
                                                default => 'danger',
                                            })
                                            ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state))),

                                        TextEntry::make('effective_date')
                                            ->label('Date')
                                            ->date('M j, Y'),

                                        TextEntry::make('reason')
                                            ->label('Reason')
                                            ->limit(30),

                                        TextEntry::make('changedBy.full_name')
                                            ->label('Changed By')
                                            ->default('System'),
                                    ]),

                                TextEntry::make('notes')
                                    ->label('Notes')
                                    ->prose()
                                    ->visible(fn ($state) => !empty($state))
                                    ->columnSpanFull(),
                            ])
                            ->contained(false),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Mentee Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                TextColumn::make('facility.name')
                    ->label('Facility')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('department.name')
                    ->label('Department')
                    ->badge()
                    ->color('success'),

                TextColumn::make('cadre.name')
                    ->label('Cadre')
                    ->badge()
                    ->color('warning'),

                BadgeColumn::make('current_status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'info' => 'study_leave',
                        'warning' => 'transferred',
                        'secondary' => fn ($state) => in_array($state, ['resigned', 'retired']),
                        'danger' => fn ($state) => in_array($state, ['defected', 'deceased', 'suspended']),
                    ])
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state))),

                TextColumn::make('training_history_summary.total_trainings')
                    ->label('Trainings')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('overall_training_score')
                    ->label('Avg Score')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 1) . '%' : 'N/A')
                    ->badge()
                    ->color(function ($state): string {
                        if (!$state) return 'gray';
                        if ($state >= 85) return 'success';
                        if ($state >= 70) return 'warning';
                        return 'danger';
                    }),

                TextColumn::make('performance_trend')
                    ->badge()
                    ->color(function ($state): string {
                        return match($state) {
                            'Improving' => 'success',
                            'Stable' => 'info',
                            'Declining' => 'danger',
                            default => 'gray',
                        };
                    }),

                TextColumn::make('attrition_risk')
                    ->label('Risk Level')
                    ->badge()
                    ->color(function ($state): string {
                        return match($state) {
                            'Low' => 'success',
                            'Medium' => 'warning',
                            'High' => 'danger',
                            default => 'gray',
                        };
                    }),
            ])
            ->filters([
                SelectFilter::make('facility')
                    ->relationship('facility', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('department')
                    ->relationship('department', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('cadre')
                    ->relationship('cadre', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('current_status')
                    ->options(MenteeStatusLog::getStatusOptions())
                    ->multiple(),

                SelectFilter::make('performance')
                    ->options([
                        'high' => 'High Performers (85%+)',
                        'good' => 'Good Performers (70-84%)',
                        'low' => 'Low Performers (<70%)',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value']) {
                            $query->when($data['value'] === 'high', function ($q) {
                                $q->whereHas('trainingParticipations.objectiveResults', function ($qq) {
                                    $qq->havingRaw('AVG(score) >= 85');
                                });
                            })
                            ->when($data['value'] === 'good', function ($q) {
                                $q->whereHas('trainingParticipations.objectiveResults', function ($qq) {
                                    $qq->havingRaw('AVG(score) >= 70 AND AVG(score) < 85');
                                });
                            })
                            ->when($data['value'] === 'low', function ($q) {
                                $q->whereHas('trainingParticipations.objectiveResults', function ($qq) {
                                    $qq->havingRaw('AVG(score) < 70');
                                });
                            });
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                
                Tables\Actions\Action::make('update_status')
                    ->label('Update Status')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('new_status')
                            ->label('New Status')
                            ->options(MenteeStatusLog::getStatusOptions())
                            ->required(),

                        Forms\Components\DatePicker::make('effective_date')
                            ->label('Effective Date')
                            ->default(now())
                            ->required(),

                        Forms\Components\TextInput::make('reason')
                            ->label('Reason for Change')
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Additional Notes')
                            ->rows(3),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->updateStatus(
                            $data['new_status'],
                            $data['reason'],
                            $data['notes'] ?? null,
                            $data['effective_date']
                        );

                        Notification::make()
                            ->title('Status Updated')
                            ->body("Status updated for {$record->full_name}")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenteeProfiles::route('/'),
            'view' => Pages\ViewMenteeProfile::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        // Show count of active mentees
        $count = static::getEloquentQuery()
            ->whereHas('statusLogs', function ($query) {
                $query->whereIn('new_status', ['active', 'study_leave'])
                      ->latest('effective_date')
                      ->limit(1);
            })
            ->orWhereDoesntHave('statusLogs')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'info';
    }
}