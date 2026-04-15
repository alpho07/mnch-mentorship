<?php

namespace App\Filament\Widgets;

use App\Models\Training;
use App\Models\MentorshipClass;
use App\Models\ClassModule;
use App\Models\ClassSession;
use App\Models\ClassParticipant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MentorStatsWidget extends BaseWidget {

    protected static ?int $sort = 1;

    protected function getStats(): array {
        $userId = auth()->id();

        // Active mentorships
        $activeMentorships = Training::where('mentor_id', $userId)
                ->where('status', 'active')
                ->count();

        // Total classes
        $totalClasses = MentorshipClass::whereHas('training', function ($query) use ($userId) {
                    $query->where('mentor_id', $userId);
                })->count();

        // Active mentees
        $activeMentees = ClassParticipant::whereHas('mentorshipClass.training', function ($query) use ($userId) {
                    $query->where('mentor_id', $userId);
                })
                ->whereIn('status', ['enrolled', 'active'])
                ->distinct('user_id')
                ->count();

        // Upcoming sessions today
        $upcomingSessions = ClassSession::whereHas('classModule.mentorshipClass.training', function ($query) use ($userId) {
                    $query->where('mentor_id', $userId);
                })
                ->where('scheduled_date', today())
                ->where('status', 'scheduled')
                ->count();

        // Modules in progress
        $modulesInProgress = ClassModule::whereHas('mentorshipClass.training', function ($query) use ($userId) {
                    $query->where('mentor_id', $userId);
                })
                ->where('status', 'in_progress')
                ->count();

        // Completed this month
        $completedThisMonth = ClassModule::whereHas('mentorshipClass.training', function ($query) use ($userId) {
                    $query->where('mentor_id', $userId);
                })
                ->where('status', 'completed')
                ->whereMonth('completed_at', now()->month)
                ->count();

        return [
                    Stat::make('Active Mentorships', $activeMentorships)
                    ->description('Currently running')
                    ->descriptionIcon('heroicon-o-academic-cap')
                    ->color('success')
                    ->chart([7, 3, 4, 5, 6, 3, 5]),
                    Stat::make('Total Classes', $totalClasses)
                    ->description('All cohorts')
                    ->descriptionIcon('heroicon-o-user-group')
                    ->color('primary'),
                    Stat::make('Active Mentees', $activeMentees)
                    ->description('Currently enrolled')
                    ->descriptionIcon('heroicon-o-users')
                    ->color('info'),
                    Stat::make('Sessions Today', $upcomingSessions)
                    ->description($upcomingSessions > 0 ? 'Scheduled for today' : 'No sessions today')
                    ->descriptionIcon('heroicon-o-calendar')
                    ->color($upcomingSessions > 0 ? 'warning' : 'gray'),
                    Stat::make('In Progress', $modulesInProgress)
                    ->description('Active modules')
                    ->descriptionIcon('heroicon-o-arrow-path')
                    ->color('warning'),
                    Stat::make('Completed This Month', $completedThisMonth)
                    ->description('Modules finished')
                    ->descriptionIcon('heroicon-o-check-circle')
                    ->color('success'),
        ];
    }

    public static function canView(): bool {
        // Only show for mentors
        return auth()->check() && Training::where('mentor_id', auth()->id())->exists();
    }
}
