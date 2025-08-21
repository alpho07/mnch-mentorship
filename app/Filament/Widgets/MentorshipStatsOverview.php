<?php

namespace App\Filament\Widgets;

use App\Models\Training;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MentorshipStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $stats = $this->getQuickStats();

        return [
            Stat::make('Total Programs', $stats['total'])
                ->description('All mentorship programs')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            Stat::make('Active Programs', $stats['ongoing'])
                ->description('Currently running')
                ->descriptionIcon('heroicon-m-play')
                ->color('success'),

            Stat::make('Total Mentees', $stats['mentees'])
                ->description('Across all programs')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Upcoming Programs', $stats['upcoming'])
                ->description('Scheduled to start')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('warning'),
        ];
    }

    protected function getQuickStats(): array
    {
        $query = Training::where('type', 'facility_mentorship');

        return [
            'total' => $query->count(),
            'ongoing' => $query->where('status', 'ongoing')->count(),
            'completed' => $query->where('status', 'completed')->count(),
            'draft' => $query->where('status', 'draft')->count(),
            'upcoming' => $query->where('start_date', '>', now())->count(),
            'mentees' => $query->withCount('participants')->get()->sum('participants_count'),
        ];
    }
}