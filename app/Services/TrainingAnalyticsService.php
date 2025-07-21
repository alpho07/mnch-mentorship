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
     * Get monthly training trends - FIXED
     */
    public function getMonthlyTrends(array $filters = []): array
    {
        $query = Training::select(
            DB::raw('YEAR(start_date) as year'),
            DB::raw('MONTH(start_date) as month'),
            DB::raw('COUNT(*) as training_count')
        )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month');

        $this->applyAllFilters($query, $filters);

        $trainings = $query->get();

        return $trainings->map(function ($item) use ($filters) {
            $date = Carbon::createFromDate($item->year, $item->month, 1);

            // Get participant count for this month with filters applied
            $participantQuery = TrainingParticipant::whereHas('training', function ($q) use ($filters, $item) {
                $this->applyAllFilters($q, $filters);
                $q->whereYear('start_date', $item->year)
                    ->whereMonth('start_date', $item->month);
            });

            return [
                'period' => $date->format('M Y'),
                'month_year' => $date->format('Y-m'),
                'trainings' => $item->training_count,
                'participants' => $participantQuery->count(),
                'facilities' => Training::whereYear('start_date', $item->year)
                    ->whereMonth('start_date', $item->month)
                    ->when(!empty($filters), function ($q) use ($filters) {
                        $this->applyAllFilters($q, $filters);
                    })
                    ->distinct('facility_id')
                    ->count(),
            ];
        })->toArray();
    }

    /**
     * Get county-wise training distribution - FIXED
     */
    public function getCountyDistribution(array $filters = []): array
    {
        $query = County::select('counties.id', 'counties.name')
            ->whereHas('subcounties.facilities.trainings', function ($q) use ($filters) {
                $this->applyAllFilters($q, $filters);
            });

        return $query->get()->map(function ($county) use ($filters) {
            // Get training count for this county with filters
            $trainingQuery = Training::whereHas('facility.subcounty.county', function ($q) use ($county) {
                $q->where('id', $county->id);
            });
            $this->applyAllFilters($trainingQuery, $filters);
            $trainingCount = $trainingQuery->count();

            // Get participant count for this county with filters
            $participantQuery = TrainingParticipant::whereHas('training.facility.subcounty.county', function ($q) use ($county) {
                $q->where('id', $county->id);
            })->whereHas('training', function ($q) use ($filters) {
                $this->applyAllFilters($q, $filters);
            });
            $participantCount = $participantQuery->count();

            // Get facility count for this county with filters
            $facilityQuery = Facility::whereHas('subcounty.county', function ($q) use ($county) {
                $q->where('id', $county->id);
            })->whereHas('trainings', function ($q) use ($filters) {
                $this->applyAllFilters($q, $filters);
            });
            $facilityCount = $facilityQuery->count();

            return [
                'county' => $county->name,
                'trainings' => $trainingCount,
                'participants' => $participantCount,
                'facilities' => $facilityCount,
                'intensity' => $trainingCount, // For heatmap coloring
            ];
        })->filter(function ($county) {
            return $county['trainings'] > 0; // Only show counties with training data
        })->values()->toArray();
    }

    /**
     * Get cadre distribution - FIXED
     */
    public function getCadreDistribution(array $filters = []): array
    {
        $query = TrainingParticipant::join('cadres', 'training_participants.cadre_id', '=', 'cadres.id')
            ->select('cadres.name as cadre', DB::raw('COUNT(*) as count'))
            ->whereHas('training', function ($q) use ($filters) {
                $this->applyAllFilters($q, $filters);
            })
            ->groupBy('cadres.id', 'cadres.name')
            ->orderBy('count', 'desc');

        return $query->get()->map(function ($item) {
            return [
                'cadre' => $item->cadre,
                'count' => $item->count,
            ];
        })->toArray();
    }

    /**
     * Get facility type distribution - FIXED
     */
    public function getFacilityTypeDistribution(array $filters = []): array
    {
        $query = Training::join('facilities', 'trainings.facility_id', '=', 'facilities.id')
            ->join('facility_types', 'facilities.facility_type_id', '=', 'facility_types.id')
            ->select('facility_types.name as facility_type', DB::raw('COUNT(DISTINCT trainings.id) as training_count'))
            ->groupBy('facility_types.id', 'facility_types.name')
            ->orderBy('training_count', 'desc');

        $this->applyAllFilters($query, $filters);

        return $query->get()->map(function ($item) {
            return [
                'facility_type' => $item->facility_type,
                'count' => $item->training_count,
            ];
        })->toArray();
    }

    /**
     * Get training approach distribution - FIXED
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
     * Get retention analysis (participants who attended multiple trainings) - FIXED
     */
    public function getRetentionAnalysis(array $filters = []): array
    {
        $participantQuery = TrainingParticipant::select('email', DB::raw('COUNT(DISTINCT training_id) as training_count'))
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('training', function ($q) use ($filters) {
                $this->applyAllFilters($q, $filters);
            })
            ->groupBy('email')
            ->havingRaw('COUNT(DISTINCT training_id) > 1');

        $retentionData = $participantQuery->get();

        $totalUniqueQuery = TrainingParticipant::whereHas('training', function ($q) use ($filters) {
            $this->applyAllFilters($q, $filters);
        });
        $totalUniqueParticipants = $totalUniqueQuery->distinct('email')->count('email');

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
     * Get top performing organizers - FIXED
     */
    public function getTopOrganizers(array $filters = [], int $limit = 10): array
    {
        $query = Training::join('users', 'trainings.organizer_id', '=', 'users.id')
            ->select(
                'users.name as organizer',
                DB::raw('COUNT(DISTINCT trainings.id) as training_count'),
                DB::raw('COUNT(DISTINCT trainings.facility_id) as facility_count')
            )
            ->groupBy('users.id', 'users.name')
            ->orderBy('training_count', 'desc')
            ->limit($limit);

        $this->applyAllFilters($query, $filters);

        return $query->get()->map(function ($item) use ($filters) {
            // Get participant count for this organizer with filters
            $participantCount = TrainingParticipant::whereHas('training', function ($q) use ($filters, $item) {
                $this->applyAllFilters($q, $filters);
                $q->whereHas('organizer', function ($userQ) use ($item) {
                    $userQ->where('name', $item->organizer);
                });
            })->count();

            return [
                'organizer' => $item->organizer,
                'trainings' => $item->training_count,
                'facilities' => $item->facility_count,
                'participants' => $participantCount,
            ];
        })->toArray();
    }


    public function debugFacilityTypes(array $filters = []): array
    {
        // Check if facilities have facility_type_id
        $facilitiesWithoutType = \App\Models\Facility::whereNull('facility_type_id')->count();

        // Check if trainings have facilities
        $trainingsWithoutFacility = \App\Models\Training::whereNull('facility_id')->count();

        // Check basic query
        $basicQuery = \App\Models\Training::join('facilities', 'trainings.facility_id', '=', 'facilities.id')
            ->whereNotNull('facilities.facility_type_id')
            ->count();

        // Check if facility_types table has data
        $facilityTypesCount = \App\Models\FacilityType::count();

        return [
            'facilities_without_type' => $facilitiesWithoutType,
            'trainings_without_facility' => $trainingsWithoutFacility,
            'trainings_with_facility_type' => $basicQuery,
            'total_facility_types' => $facilityTypesCount,
            'sample_facility_types' => \App\Models\FacilityType::take(5)->pluck('name')->toArray(),
        ];
    }
}
