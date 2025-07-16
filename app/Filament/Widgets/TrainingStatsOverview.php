<?php

namespace App\Filament\Widgets;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\Facility;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TrainingStatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $filters = $this->filters ?? [];
        $dateRange = $this->getDateRange($filters);
        $previousRange = $this->getPreviousDateRange($dateRange);

        // Current period stats
        $currentHCWs = $this->getTrainedHCWs($dateRange['start'], $dateRange['end'], $filters);
        $previousHCWs = $this->getTrainedHCWs($previousRange['start'], $previousRange['end'], $filters);
        
        $currentFacilities = $this->getActiveFacilities($dateRange['start'], $dateRange['end'], $filters);
        $previousFacilities = $this->getActiveFacilities($previousRange['start'], $previousRange['end'], $filters);
        
        $completionRate = $this->getCompletionRate($dateRange['start'], $dateRange['end'], $filters);
        $previousCompletionRate = $this->getCompletionRate($previousRange['start'], $previousRange['end'], $filters);

        return [
            Stat::make('Healthcare Workers Trained', number_format($currentHCWs))
                ->description($this->getChangeDescription($currentHCWs, $previousHCWs))
                ->descriptionIcon($this->getChangeIcon($currentHCWs, $previousHCWs))
                ->color($this->getChangeColor($currentHCWs, $previousHCWs))
                ->chart($this->getMonthlyTrend('participants', $filters)),

            Stat::make('Active Training Facilities', number_format($currentFacilities))
                ->description($this->getChangeDescription($currentFacilities, $previousFacilities))
                ->descriptionIcon($this->getChangeIcon($currentFacilities, $previousFacilities))
                ->color($this->getChangeColor($currentFacilities, $previousFacilities))
                ->chart($this->getMonthlyTrend('facilities', $filters)),

            Stat::make('Training Completion Rate', number_format($completionRate, 1) . '%')
                ->description($this->getChangeDescription($completionRate, $previousCompletionRate, true))
                ->descriptionIcon($this->getChangeIcon($completionRate, $previousCompletionRate))
                ->color($this->getChangeColor($completionRate, $previousCompletionRate))
                ->chart($this->getMonthlyTrend('completion', $filters)),
        ];
    }

    private function getDateRange(array $filters): array
    {
        if (isset($filters['startDate']) && isset($filters['endDate'])) {
            return [
                'start' => Carbon::parse($filters['startDate']),
                'end' => Carbon::parse($filters['endDate']),
            ];
        }

        $now = Carbon::now();
        $range = $filters['dateRange'] ?? 'mtd';

        switch ($range) {
            case 'qtd':
                return [
                    'start' => $now->copy()->firstOfQuarter(),
                    'end' => $now,
                ];
            case 'ytd':
                return [
                    'start' => $now->copy()->startOfYear(),
                    'end' => $now,
                ];
            case 'last_month':
                return [
                    'start' => $now->copy()->subMonth()->startOfMonth(),
                    'end' => $now->copy()->subMonth()->endOfMonth(),
                ];
            case 'last_quarter':
                return [
                    'start' => $now->copy()->subQuarter()->firstOfQuarter(),
                    'end' => $now->copy()->subQuarter()->lastOfQuarter(),
                ];
            case 'last_year':
                return [
                    'start' => $now->copy()->subYear()->startOfYear(),
                    'end' => $now->copy()->subYear()->endOfYear(),
                ];
            default: // mtd
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now,
                ];
        }
    }

    private function getPreviousDateRange(array $currentRange): array
    {
        $duration = $currentRange['start']->diffInDays($currentRange['end']);
        
        return [
            'start' => $currentRange['start']->copy()->subDays($duration + 1),
            'end' => $currentRange['start']->copy()->subDay(),
        ];
    }

    private function getTrainedHCWs($startDate, $endDate, array $filters): int
    {
        $query = TrainingParticipant::query()
            ->whereHas('training', function ($q) use ($startDate, $endDate, $filters) {
                $q->whereBetween('start_date', [$startDate, $endDate]);
                $this->applyFilters($q, $filters);
            });

        if (!empty($filters['cadres'])) {
            $query->whereIn('cadre_id', $filters['cadres']);
        }

        return $query->distinct('user_id')->count('user_id');
    }

    private function getActiveFacilities($startDate, $endDate, array $filters): int
    {
        $query = Training::query()
            ->whereBetween('start_date', [$startDate, $endDate]);
        
        $this->applyFilters($query, $filters);

        return $query->distinct('facility_id')->count('facility_id');
    }

    private function getCompletionRate($startDate, $endDate, array $filters): float
    {
        $query = TrainingParticipant::query()
            ->whereHas('training', function ($q) use ($startDate, $endDate, $filters) {
                $q->whereBetween('end_date', [$startDate, $endDate]);
                $this->applyFilters($q, $filters);
            });

        $total = $query->count();
        if ($total === 0) return 0;

        $completed = $query->whereNotNull('outcome_id')->count();
        
        return ($completed / $total) * 100;
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['facility'])) {
            $query->where('facility_id', $filters['facility']);
        } elseif (!empty($filters['subcounty'])) {
            $query->whereHas('facility', fn ($q) => $q->where('subcounty_id', $filters['subcounty']));
        } elseif (!empty($filters['county'])) {
            $query->whereHas('facility.subcounty', fn ($q) => $q->where('county_id', $filters['county']));
        }

        if (!empty($filters['program'])) {
            $query->where('program_id', $filters['program']);
        }

        if (!empty($filters['module'])) {
            $query->where('module_id', $filters['module']);
        }

        if (!empty($filters['facilityType'])) {
            $query->whereHas('facility', fn ($q) => $q->where('facility_type_id', $filters['facilityType']));
        }

        if (!empty($filters['approach'])) {
            $query->where('approach', $filters['approach']);
        }

        if ($filters['hubOnly'] ?? false) {
            $query->whereHas('facility', fn ($q) => $q->where('is_hub', true));
        }
    }

    private function getChangeDescription($current, $previous, $isPercentage = false): string
    {
        if ($previous == 0) {
            return 'No previous data';
        }

        $change = (($current - $previous) / $previous) * 100;
        $prefix = $change >= 0 ? '+' : '';
        
        return $prefix . number_format(abs($change), 1) . '% from previous period';
    }

    private function getChangeIcon($current, $previous): string
    {
        if ($current > $previous) {
            return 'heroicon-m-arrow-trending-up';
        } elseif ($current < $previous) {
            return 'heroicon-m-arrow-trending-down';
        }
        return 'heroicon-m-minus';
    }

    private function getChangeColor($current, $previous): string
    {
        if ($current > $previous) {
            return 'success';
        } elseif ($current < $previous) {
            return 'danger';
        }
        return 'gray';
    }

    private function getMonthlyTrend($type, array $filters): array
    {
        $data = [];
        $endDate = Carbon::now();
        
        for ($i = 6; $i >= 0; $i--) {
            $monthStart = $endDate->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $endDate->copy()->subMonths($i)->endOfMonth();
            
            switch ($type) {
                case 'participants':
                    $data[] = $this->getTrainedHCWs($monthStart, $monthEnd, $filters);
                    break;
                case 'facilities':
                    $data[] = $this->getActiveFacilities($monthStart, $monthEnd, $filters);
                    break;
                case 'completion':
                    $data[] = $this->getCompletionRate($monthStart, $monthEnd, $filters);
                    break;
            }
        }
        
        return $data;
    }
}