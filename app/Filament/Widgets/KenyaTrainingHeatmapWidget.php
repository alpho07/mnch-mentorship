<?php

namespace App\Filament\Widgets;

use App\Models\County;
use App\Models\Training;
use App\Models\TrainingParticipant;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class KenyaTrainingHeatmapWidget extends Widget
{
    protected static string $view = 'filament.widgets.kenya-training-heatmap';

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Training Coverage Across Kenya';

    // Filter properties
    public ?array $filterData = [];

    // Listen to filter changes
    protected $listeners = [
        'filtersUpdated' => 'updateFilters',
    ];

    public function mount()
    {
        // Initialize with empty filters
        $this->filterData = [
            'program_id' => [],
            'period' => [],
            'county_id' => [],
            'subcounty_id' => [],
            'facility_id' => [],
            'department_id' => null,
            'cadre_id' => null,
        ];
    }

    public function updateFilters($filterData = [])
    {
        $this->filterData = array_merge($this->filterData, $filterData ?: []);
    }

    private function getFilteredTrainings(): Builder
    {
        $query = Training::query();

        // Only apply filters if they exist and are not empty
        if (isset($this->filterData['program_id']) && !empty($this->filterData['program_id'])) {
            $query->whereIn('program_id', $this->filterData['program_id']);
        }

        if (isset($this->filterData['period']) && !empty($this->filterData['period'])) {
            $query->where(function ($q) {
                foreach ($this->filterData['period'] as $period) {
                    try {
                        $date = Carbon::createFromFormat('Y-m', $period);
                        $q->orWhereBetween('start_date', [
                            $date->startOfMonth(),
                            $date->endOfMonth()
                        ]);
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            });
        }

        if (isset($this->filterData['facility_id']) && !empty($this->filterData['facility_id'])) {
            $query->whereIn('facility_id', $this->filterData['facility_id']);
        } elseif (isset($this->filterData['subcounty_id']) && !empty($this->filterData['subcounty_id'])) {
            $query->whereHas('facility', fn(Builder $q) => $q->whereIn('subcounty_id', $this->filterData['subcounty_id']));
        } elseif (isset($this->filterData['county_id']) && !empty($this->filterData['county_id'])) {
            $query->whereHas('facility.subcounty', fn(Builder $q) => $q->whereIn('county_id', $this->filterData['county_id']));
        }

        if (isset($this->filterData['department_id']) && !empty($this->filterData['department_id'])) {
            $query->whereHas('departments', fn(Builder $q) => $q->where('departments.id', $this->filterData['department_id']));
        }

        return $query;
    }

    public function getTrainingDataByCounty()
    {
        try {
            // Get all counties first
            $allCounties = County::all(['id', 'name']);

            if ($allCounties->isEmpty()) {
                // Return sample data if no counties in database
                return $this->getSampleCountyData();
            }

            // Get training counts by county using simpler approach
            $trainingsByCounty = [];
            $participantsByCounty = [];
            $facilitiesByCounty = [];

            // Get all trainings with relationships
            $trainings = $this->getFilteredTrainings()
                ->with(['facility.subcounty.county', 'participants'])
                ->get();

            // Process trainings to get county data
            foreach ($trainings as $training) {
                if ($training->facility &&
                    $training->facility->subcounty &&
                    $training->facility->subcounty->county) {

                    $countyName = $training->facility->subcounty->county->name;

                    // Count trainings
                    $trainingsByCounty[$countyName] = ($trainingsByCounty[$countyName] ?? 0) + 1;

                    // Count participants
                    $participantsByCounty[$countyName] = ($participantsByCounty[$countyName] ?? 0) + $training->participants->count();

                    // Count unique facilities
                    if (!isset($facilitiesByCounty[$countyName])) {
                        $facilitiesByCounty[$countyName] = [];
                    }
                    $facilitiesByCounty[$countyName][$training->facility_id] = true;
                }
            }

            // Convert facility arrays to counts
            foreach ($facilitiesByCounty as $county => $facilities) {
                $facilitiesByCounty[$county] = count($facilities);
            }

            // If no training data, return sample data
            if (empty($trainingsByCounty)) {
                return $this->getSampleCountyData();
            }

            // Combine data for all counties
            $countyData = [];
            foreach ($allCounties as $county) {
                $trainings = $trainingsByCounty[$county->name] ?? 0;
                $participants = $participantsByCounty[$county->name] ?? 0;
                $facilities = $facilitiesByCounty[$county->name] ?? 0;

                $countyData[] = [
                    'name' => $county->name,
                    'trainings' => $trainings,
                    'participants' => $participants,
                    'facilities' => $facilities,
                    'intensity' => $this->calculateIntensity($trainings, $participants, $facilities)
                ];
            }

            return collect($countyData);

        } catch (\Exception $e) {
            // Return sample data on error
            return $this->getSampleCountyData();
        }
    }

 
    private function calculateIntensity($trainings, $participants, $facilities)
    {
        if ($trainings == 0) return 0;

        // Calculate intensity score based on multiple factors
        $trainingScore = $trainings * 0.4;
        $participantScore = ($participants / max(1, $trainings)) * 0.4;
        $facilityScore = $facilities * 0.2;

        return $trainingScore + $participantScore + $facilityScore;
    }

    public function getIntensityLevels()
    {
        $data = $this->getTrainingDataByCounty();
        $maxIntensity = $data->max('intensity') ?: 100;

        return [
            'low' => $maxIntensity * 0.25,
            'medium' => $maxIntensity * 0.5,
            'high' => $maxIntensity * 0.75,
            'max' => $maxIntensity
        ];
    }

    public function getMapData()
    {
        $countyData = $this->getTrainingDataByCounty();
        //\Log::info('MapData:', $countyData->toArray());

        return [
            'countyData' => $countyData,
            'intensityLevels' => $this->getIntensityLevels(),
            'totalTrainings' => $countyData->sum('trainings'),
            'totalParticipants' => $countyData->sum('participants'),
            'hasData' => $countyData->sum('trainings') > 0,
            'debug' => [
                'filterData' => $this->filterData,
                'dataCount' => $countyData->count(),
                'activeCounties' => $countyData->filter(fn($c) => $c['trainings'] > 0)->count()
            ]
        ];
    }
}
