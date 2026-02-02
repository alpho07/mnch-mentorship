<?php

namespace App\Filament\Pages;

use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use Filament\Pages\Page;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class MenteeDashboard extends Page implements HasTable {

    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationLabel = 'My Progress';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.mentee-dashboard';
    


    public function getTitle(): string {
        return 'My Learning Dashboard';
    }

    public function getHeading(): string {
        $user = auth()->user();
        return "Welcome, {$user->first_name}!";
    }

    public function getSubheading(): ?string {
        $activeEnrollments = ClassParticipant::where('user_id', auth()->id())
                ->whereIn('status', ['enrolled', 'active'])
                ->count();

        return "You are enrolled in {$activeEnrollments} active class(es)";
    }

    protected function getHeaderWidgets(): array {
        return [
            \App\Filament\Widgets\MenteeStatsWidget::class,
        ];
    }

    public function table(Table $table): Table {
        return $table
                        ->query(
                                MenteeModuleProgress::query()
                                ->whereHas('classParticipant', function ($query) {
                                    $query->where('user_id', auth()->id());
                                })
                                ->with([
                                    'classModule.programModule',
                                    'classModule.mentorshipClass',
                                    'classParticipant.mentorshipClass.training.program'
                                ])
                                ->latest()
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('classParticipant.mentorshipClass.training.program.name')
                            ->label('Program')
                            ->searchable()
                            ->weight('bold')
                            ->icon('heroicon-o-academic-cap'),
                            Tables\Columns\TextColumn::make('classModule.programModule.name')
                            ->label('Module')
                            ->searchable()
                            ->description(fn($record) =>
                                    $record->classModule->mentorshipClass->name
                            ),
                            Tables\Columns\BadgeColumn::make('status')
                            ->colors([
                                'secondary' => 'not_started',
                                'warning' => 'in_progress',
                                'success' => 'completed',
                                'info' => 'exempted',
                            ])
                            ->formatStateUsing(fn(string $state): string =>
                                    match ($state) {
                                        'not_started' => 'Not Started',
                                        'in_progress' => 'In Progress',
                                        'completed' => 'Completed',
                                        'exempted' => 'Exempted',
                                        default => ucfirst($state),
                                    }
                            ),
                            Tables\Columns\IconColumn::make('completed_in_previous_class')
                            ->label('Previous')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-badge')
                            ->falseIcon('heroicon-o-x-mark')
                            ->trueColor('info')
                            ->falseColor('gray')
                            ->tooltip(fn($record) =>
                                    $record->completed_in_previous_class ? 'Completed in previous class' : 'First time'
                            ),
                            Tables\Columns\TextColumn::make('attendance_percentage')
                            ->label('Attendance')
                            ->suffix('%')
                            ->badge()
                            ->color(fn($state) => match (true) {
                                        $state >= 80 => 'success',
                                        $state >= 60 => 'warning',
                                        $state === null => 'gray',
                                        default => 'danger',
                                    })
                            ->default('N/A')
                            ->sortable(),
                            Tables\Columns\TextColumn::make('assessment_score')
                            ->label('Assessment')
                            ->formatStateUsing(fn($state) => $state ? number_format($state, 1) . '%' : 'N/A')
                            ->badge()
                            ->color(fn($state) => $state >= 70 ? 'success' : ($state ? 'danger' : 'gray'))
                            ->sortable(),
                            Tables\Columns\BadgeColumn::make('assessment_status')
                            ->colors([
                                'gray' => 'pending',
                                'success' => 'passed',
                                'danger' => 'failed',
                            ]),
                            Tables\Columns\TextColumn::make('completed_at')
                            ->label('Completed')
                            ->dateTime('M j, Y')
                            ->placeholder('Not completed')
                            ->sortable(),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('status')
                            ->options([
                                'not_started' => 'Not Started',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'exempted' => 'Exempted',
                            ]),
                            Tables\Filters\SelectFilter::make('assessment_status')
                            ->label('Assessment Status')
                            ->options([
                                'pending' => 'Pending',
                                'passed' => 'Passed',
                                'failed' => 'Failed',
                            ]),
                        ])
                        ->defaultSort('created_at', 'desc')
                        ->emptyStateHeading('No Modules Yet')
                        ->emptyStateDescription('You haven\'t been enrolled in any modules yet.')
                        ->emptyStateIcon('heroicon-o-book-open');
    }

    public static function shouldRegisterNavigation(): bool {
        // Show for users who are mentees
         return true;
    }
}

