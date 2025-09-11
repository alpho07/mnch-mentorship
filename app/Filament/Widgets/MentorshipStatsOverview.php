<?php

namespace App\Filament\Widgets;

use App\Models\Training;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MentorshipStatsOverview extends BaseWidget {

    protected function getStats(): array {
        $stats = $this->getQuickStats();

       // dd($stats);

        return [
                    Stat::make('All Mentorships', $stats['total'])
                    ->description('All mentorships')
                    ->descriptionIcon('heroicon-m-academic-cap')
                    ->color('primary'),
                    Stat::make('Active Mentorships', $stats['new'])
                    ->description('Currently running')
                    ->descriptionIcon('heroicon-m-play')
                    ->color('success'),
                    Stat::make('Total Mentees', $stats['mentees'])
                    ->description('Total Enrolled')
                    ->descriptionIcon('heroicon-m-users')
                    ->color('info'),
                /* Stat::make('Upcoming Programs', $stats['upcoming'])
                  ->description('Scheduled to start')
                  ->descriptionIcon('heroicon-m-calendar')
                  ->color('warning'), */
        ];
    }

    protected function getQuickStats(): array {
        return [
            'total' => Training::where('type', 'facility_mentorship')->count(),
            'ongoing' => Training::where('type', 'facility_mentorship')
                    ->where('status', 'ongoing')
                    ->count(),
            'completed' => Training::where('type', 'facility_mentorship')
                    ->where('status', 'completed')
                    ->count(),
            'new' => Training::where('type', 'facility_mentorship')
                    ->where('status', 'new') // fixed typo "statu"
                    ->count(),
            'upcoming' => Training::where('type', 'facility_mentorship')
                    ->where('start_date', '>', now())
                    ->count(),
            'mentees' => Training::where('type', 'facility_mentorship')
                    ->withCount('participants')
                    ->get()
                    ->sum('participants_count'),
        ];
    }
}
