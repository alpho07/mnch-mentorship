<?php

namespace App\Filament\Widgets;

use App\Models\Training;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class MentorshipStatsOverview extends BaseWidget {

    protected function getStats(): array {
        $stats = $this->getQuickStats();

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

    /**
     * Build a base query scoped by role: admins see all, others see only their own.
     */
    protected function getScopedBaseQuery(): Builder {
        $query = Training::where('type', 'facility_mentorship');

        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'admin', 'division'])) {
            $query->where('mentor_id', $user->id);
        }

        return $query;
    }

    protected function getQuickStats(): array {
        return [
            'total' => $this->getScopedBaseQuery()->count(),
            'ongoing' => $this->getScopedBaseQuery()->where('status', 'ongoing')->count(),
            'completed' => $this->getScopedBaseQuery()->where('status', 'completed')->count(),
            'new' => $this->getScopedBaseQuery()->where('status', 'new')->count(),
            'upcoming' => $this->getScopedBaseQuery()->where('start_date', '>', now())->count(),
            'mentees' => $this->getScopedBaseQuery()->withCount('participants')->get()->sum('participants_count'),
        ];
    }
}
