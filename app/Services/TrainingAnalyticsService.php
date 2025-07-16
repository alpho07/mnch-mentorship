<?php

namespace App\Services;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\County;
use App\Models\Facility;
use App\Traits\HasTrainingFilters;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TrainingAnalyticsService
{
    use HasTrainingFilters;

    /**
     * Get core dashboard statistics
     */
    public function getCoreStats(array $filters = []): array
    {
        $trainingQuery = Training::query();
        $this->applyAllFilters($trainingQuery, $filters);

        $participantQuery = TrainingParticipant::whereHas('training', function ($q) use ($filters) {
            $this->applyAllFilters($q, $filters);
        });

        return [
            'total_trainings' => $trainingQuery->count(),
            'total_participants' => $participantQuery->count(),
            'unique_participants' => $participantQuery->distinct('email')->count('email'),
            'facilities_covered' => $trainingQuery->distinct('facility_id')->count(),
            'counties_covered' => $trainingQuery->distinct(
                DB::raw('(SELECT county_id FROM subcounties WHERE subcounties.id = facilities.subcounty_id)')
            )->join('facilities', 'trainings.facility_id', '=', 'facilities.id')->count(),
            'programs_delivered' => $trainingQuery->distinct('program_id')->count(),
            'avg_participants_per_training' => round($participantQuery->count() / max($trainingQuery->count(), 1), 1),
            'tot_participants' => $participantQuery->where('is_tot', true)->count(),
            'tot_percentage' => $participantQuery->count() > 0
                ? round(($participantQuery->where('is_tot', true)->count() / $participantQuery->count()) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get monthly training trends
     */
    public function getMonthlyTrends(array $filters = []): array
    {
        $query = Training::select(
            DB::raw('YEAR(start_date) as year'),
            DB::raw('MONTH(start_date) as month'),
            DB::raw('COUNT(*) as training_count'),
            DB::raw('COUNT(DISTINCT facility_id) as facility_count')
        )
            ->with('participants')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month');

        $this->applyAllFilters($query, $filters);

        return $query->get()->map(function ($item) {
            $date = Carbon::createFromDate($item->year, $item->month, 1);
            return [
                'period' => $date->format('M Y'),
                'month_year' => $date->format('Y-m'),
                'trainings' => $item->training_count,
                'facilities' => $item->facility_count,
                'participants' => $item->participants->count(),
            ];
        })->toArray();
    }

    /**
     * Get county-wise training distribution
     */
    public function getCountyDistribution(array $filters = []): array
    {
        $query = County::select('counties.id', 'counties.name')
            ->withCount([
                'subcounties as training_count' => function ($q) use ($filters) {
                    $q->withCount([
                        'facilities as facility_training_count' => function ($subQ) use ($filters) {
                            $subQ->whereHas('trainings', function ($trainQ) use ($filters) {
                                $this->applyAllFilters($trainQ, $filters);
                            });
                        }
                    ]);
                }
            ])
            ->whereHas('subcounties.facilities.trainings', function ($q) use ($filters) {
                $this->applyAllFilters($q, $filters);
            });

        return $query->get()->map(function ($county) {
            $trainingCount = Training::whereHas('facility.subcounty.county', function ($q) use ($county) {
                $q->where('id', $county->id);
            })->count();

            $participantCount = TrainingParticipant::whereHas('training.facility.subcounty.county', function ($q) use ($county) {
                $q->where('id', $county->id);
            })->count();

            $facilityCount = Facility::whereHas('subcounty.county', function ($q) use ($county) {
                $q->where('id', $county->id);
            })->whereHas('trainings')->count();

            return [
                'county' => $county->name,
                'trainings' => $trainingCount,
                'participants' => $participantCount,
                'facilities' => $facilityCount,
                'intensity' => $trainingCount, // For heatmap coloring
            ];
        })->toArray();
    }

    /**
     * Get cadre distribution
     */
    public function getCadreDistribution(array $filters = []): array
    {
        $query = DB::table('training_participants')
            ->join('cadres', 'training_participants.cadre_id', '=', 'cadres.id')
            ->join('trainings', 'training_participants.training_id', '=', 'trainings.id')
            ->select('cadres.name as cadre', DB::raw('COUNT(*) as count'))
            ->groupBy('cadres.id', 'cadres.name')
            ->orderBy('count', 'desc');

        // Apply filters through join
        $this->applyJoinFilters($query, $filters);

        return $query->get()->map(function ($item) {
            return [
                'cadre' => $item->cadre,
                'count' => $item->count,
            ];
        })->toArray();
    }

    /**
     * Get facility type distribution
     */
    public function getFacilityTypeDistribution(array $filters = []): array
    {
        $query = DB::table('trainings')
            ->join('facilities', 'trainings.facility_id', '=', 'facilities.id')
            ->join('facility_types', 'facilities.facility_type_id', '=', 'facility_types.id')
            ->select('facility_types.name as facility_type', DB::raw('COUNT(DISTINCT trainings.id) as training_count'))
            ->groupBy('facility_types.id', 'facility_types.name')
            ->orderBy('training_count', 'desc');

        $this->applyJoinFilters($query, $filters);

        return $query->get()->map(function ($item) {
            return [
                'facility_type' => $item->facility_type,
                'count' => $item->training_count,
            ];
        })->toArray();
    }

    /**
     * Get training approach distribution
     */
    public function getApproachDistribution(array $filters = []): array
    {
        $query = Training::select('approach', DB::raw('COUNT(*) as count'))
            ->groupBy('approach')
            ->orderBy('count', 'desc');

        $this->applyAllFilters($query, $filters);

        return $query->get()->map(function ($item) {
            return [
                'approach' => ucfirst($item->approach),
                'count' => $item->count,
            ];
        })->toArray();
    }

    /**
     * Get retention analysis (participants who attended multiple trainings)
     */
    public function getRetentionAnalysis(array $filters = []): array
    {
        $query = TrainingParticipant::select('email', DB::raw('COUNT(DISTINCT training_id) as training_count'))
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('training', function ($q) use ($filters) {
                $this->applyAllFilters($q, $filters);
            })
            ->groupBy('email')
            ->havingRaw('COUNT(DISTINCT training_id) > 1');

        $retentionData = $query->get();
        $totalUniqueParticipants = TrainingParticipant::whereHas('training', function ($q) use ($filters) {
            $this->applyAllFilters($q, $filters);
        })->distinct('email')->count('email');

        return [
            'repeat_participants' => $retentionData->count(),
            'total_unique_participants' => $totalUniqueParticipants,
            'retention_rate' => $totalUniqueParticipants > 0
                ? round(($retentionData->count() / $totalUniqueParticipants) * 100, 2)
                : 0,
            'retention_distribution' => $retentionData->groupBy('training_count')->map(function ($group, $count) {
                return [
                    'training_count' => $count,
                    'participant_count' => $group->count(),
                ];
            })->values()->toArray(),
        ];
    }

    /**
     * Get top performing organizers
     */
    public function getTopOrganizers(array $filters = [], int $limit = 10): array
    {
        $query = DB::table('trainings')
            ->join('users', 'trainings.organizer_id', '=', 'users.id')
            ->select(
                'users.name as organizer',
                DB::raw('COUNT(DISTINCT trainings.id) as training_count'),
                DB::raw('COUNT(DISTINCT trainings.facility_id) as facility_count')
            )
            ->groupBy('users.id', 'users.name')
            ->orderBy('training_count', 'desc')
            ->limit($limit);

        $this->applyJoinFilters($query, $filters);

        return $query->get()->map(function ($item) {
            return [
                'organizer' => $item->organizer,
                'trainings' => $item->training_count,
                'facilities' => $item->facility_count,
            ];
        })->toArray();
    }

    /**
     * Apply filters to join queries
     */
    private function applyJoinFilters($query, array $filters): void
    {
        // Time-based filters
        if (!empty($filters['years'])) {
            $query->whereIn(DB::raw('YEAR(trainings.start_date)'), $filters['years']);
        }

        if (!empty($filters['quarters'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['quarters'] as $quarter) {
                    if (str_contains($quarter, '-Q')) {
                        [$year, $quarterPart] = explode('-Q', $quarter);
                        $quarterNum = (int) $quarterPart;
                        $q->orWhere(function ($subQ) use ($year, $quarterNum) {
                            $subQ->whereYear('trainings.start_date', $year)
                                ->whereRaw('QUARTER(trainings.start_date) = ?', [$quarterNum]);
                        });
                    }
                }
            });
        }

        if (!empty($filters['months'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['months'] as $month) {
                    if (str_contains($month, '-')) {
                        [$year, $monthNum] = explode('-', $month);
                        $q->orWhere(function ($subQ) use ($year, $monthNum) {
                            $subQ->whereYear('trainings.start_date', $year)
                                ->whereMonth('trainings.start_date', $monthNum);
                        });
                    }
                }
            });
        }

        // Other filters
        if (!empty($filters['programs'])) {
            $query->whereIn('trainings.program_id', $filters['programs']);
        }

        if (!empty($filters['approaches'])) {
            $query->whereIn('trainings.approach', $filters['approaches']);
        }
    }
}
