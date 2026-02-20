<?php

namespace App\Filament\Pages;

use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\ClassAttendance;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\DB;

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
        $stats = $this->getDashboardStats();
        return "You are enrolled in {$stats['active_enrollments']} active class(es) • {$stats['completed_modules']}/{$stats['total_modules']} modules completed";
    }

    /**
     * Compute accurate dashboard stats for the mentee.
     */
    public function getDashboardStats(): array {
        $userId = auth()->id();

        $activeEnrollments = ClassParticipant::where('user_id', $userId)
                ->whereIn('status', ['enrolled', 'active'])
                ->count();

        $totalEnrollments = ClassParticipant::where('user_id', $userId)->count();

        $completedEnrollments = ClassParticipant::where('user_id', $userId)
                ->where('status', 'completed')
                ->count();

        // Module stats from mentee_module_progress
        $moduleStats = MenteeModuleProgress::whereHas('classParticipant', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->selectRaw("
            COUNT(*) as total_modules,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_modules,
            SUM(CASE WHEN status = 'exempted' THEN 1 ELSE 0 END) as exempted_modules,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_modules,
            SUM(CASE WHEN status = 'not_started' THEN 1 ELSE 0 END) as not_started_modules,
            AVG(CASE WHEN assessment_score IS NOT NULL THEN assessment_score END) as avg_assessment_score,
            AVG(CASE WHEN attendance_percentage IS NOT NULL THEN attendance_percentage END) as avg_attendance
        ")
                ->first();

        // Attendance from class_attendances table
        $attendanceCount = ClassAttendance::where('user_id', $userId)->count();

        $totalModules = $moduleStats->total_modules ?? 0;
        $completedModules = $moduleStats->completed_modules ?? 0;
        $exemptedModules = $moduleStats->exempted_modules ?? 0;
        $effectiveCompleted = $completedModules + $exemptedModules;

        return [
            'active_enrollments' => $activeEnrollments,
            'total_enrollments' => $totalEnrollments,
            'completed_enrollments' => $completedEnrollments,
            'total_modules' => $totalModules,
            'completed_modules' => $completedModules,
            'exempted_modules' => $exemptedModules,
            'in_progress_modules' => $moduleStats->in_progress_modules ?? 0,
            'not_started_modules' => $moduleStats->not_started_modules ?? 0,
            'module_completion_rate' => $totalModules > 0 ? round(($effectiveCompleted / $totalModules) * 100, 1) : 0,
            'avg_assessment_score' => $moduleStats->avg_assessment_score ? round($moduleStats->avg_assessment_score, 1) : null,
            'avg_attendance' => $moduleStats->avg_attendance ? round($moduleStats->avg_attendance, 1) : null,
            'attendance_records' => $attendanceCount,
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
                                    'classParticipant.mentorshipClass.training.program',
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
        return auth()->check() && auth()->user()->hasRole(['super_admin', 'mentee','division']);
    }
}
