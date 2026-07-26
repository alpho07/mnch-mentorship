<?php

namespace App\Filament\Widgets;

use App\Models\ClassParticipant;
use App\Models\Training;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MentorshipStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $colors = ['success', 'info', 'warning', 'primary', 'danger', 'gray'];

        $byProgram = $this->getScopedBaseQuery()
            ->join('programs', 'trainings.program_id', '=', 'programs.id')
            ->leftJoin('mentorship_classes as mc', function ($join) {
                $join->on('mc.training_id', '=', 'trainings.id')
                     ->whereNull('mc.deleted_at');
            })
            ->leftJoin('class_participants as cp', function ($join) {
                $join->on('cp.mentorship_class_id', '=', 'mc.id')
                     ->whereIn('cp.status', ['enrolled', 'active']);
            })
            ->select(
                'programs.id',
                'programs.name',
                DB::raw('COUNT(DISTINCT trainings.id) as count'),
                DB::raw('COUNT(DISTINCT cp.user_id) as mentees')
            )
            ->groupBy('programs.id', 'programs.name')
            ->orderByDesc('count')
            ->get();

        $programStats = $byProgram->map(fn ($row, $i) =>
            Stat::make($row->name, $row->count)
                ->description($row->count . ' mentorships · ' . $row->mentees . ' mentees')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color($colors[$i % count($colors)])
        )->all();

        $totalMentees = $this->getScopedMenteesQuery()
            ->distinct('class_participants.user_id')
            ->count('class_participants.user_id');

        return array_merge(
            [
                Stat::make('Total Mentees', $totalMentees)
                    ->description('Total Enrolled')
                    ->descriptionIcon('heroicon-m-users')
                    ->color('info'),
                Stat::make('All Mentorships', $this->getScopedBaseQuery()->count())
                    ->description('All mentorships')
                    ->descriptionIcon('heroicon-m-academic-cap')
                    ->color('primary'),
            ],
            $programStats
        );
    }

    /**
     * Build a base query scoped by role: admins see all, others see only their own.
     */
    protected function getScopedBaseQuery(): Builder
    {
        $query = Training::where('type', 'facility_mentorship')
            ->where('is_pilot', false);   // pilots excluded from all KPI counts

        $user = auth()->user();
        if (! $user->hasRole(['super_admin', 'admin', 'division'])) {
            $query->forMentorOrCoMentor($user->id);
        }

        return $query;
    }

    protected function getScopedMenteesQuery(): Builder
    {
        $user = auth()->user();

        return ClassParticipant::query()
            ->whereIn('status', ['enrolled', 'active'])
            ->whereHas('mentorshipClass.training', function (Builder $query) use ($user) {
                $query->where('type', 'facility_mentorship')
                      ->where('is_pilot', false)
                      ->where('status', '!=', 'cancelled');

                if (! $user->hasRole(['super_admin', 'admin', 'division'])) {
                    $query->forMentorOrCoMentor($user->id);
                }
            });
    }
}

