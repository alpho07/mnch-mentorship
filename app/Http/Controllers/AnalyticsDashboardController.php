<?php

namespace App\Http\Controllers;

use App\Models\County;
use App\Models\Training;
use App\Models\Facility;
use App\Models\TrainingParticipant;
use App\Models\Department;
use App\Models\Cadre;
use App\Models\FacilityType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class AnalyticsDashboardController extends Controller
{
    public function index(Request $request)
    {
        $currentYear = Carbon::now()->year;
        $selectedYear = $request->get('year', ''); // Default to empty for "All Years"
        $mode = $request->get('mode', 'training'); // training or mentorship
        
        // Get available years
        $availableYears = Training::selectRaw('YEAR(start_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->filter();

        $counties = $this->getCountiesData($selectedYear, $mode);
        $heatmapData = $this->generateHeatmapData($counties);
        $summaryStats = $this->getSummaryStats($selectedYear, $mode);

        return view('analytics.dashboard.index', compact(
            'counties', 'heatmapData', 'summaryStats', 'availableYears', 
            'selectedYear', 'mode'
        ));
    }

 public function geojson(Request $request)
    {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');
        
        // Load the actual GeoJSON file
        $path = public_path('kenyan-counties.geojson');
        if (!File::exists($path)) {
            return response()->json([
                'type' => 'FeatureCollection', 
                'features' => [],
                'error' => 'GeoJSON file not found'
            ], 404);
        }
        
        // Get the GeoJSON data
        $geojsonContent = File::get($path);
        $geojsonData = json_decode($geojsonContent, true);
        
        if (!$geojsonData || !isset($geojsonData['features'])) {
            return response()->json([
                'type' => 'FeatureCollection', 
                'features' => [],
                'error' => 'Invalid GeoJSON format'
            ], 400);
        }
        
        // Get counties data with statistics
        $counties = $this->getCountiesData($selectedYear, $mode);
        
        // Create a mapping of county statistics by normalized name
        $countyStatsMap = [];
        foreach ($counties as $county) {
            // Calculate total programs for this county
            $totalPrograms = $this->getCountyPrograms($county->id, $selectedYear, $mode);
            
            // Create multiple normalized variations for better matching
            $variations = [
                strtoupper(trim($county->name)),
                strtoupper(trim(str_replace(' ', '', $county->name))),
                strtoupper(trim(str_replace([' ', '-', "'", '.'], '', $county->name))),
                trim($county->name),
                trim(str_replace(' ', '', $county->name))
            ];
            
            $stats = [
                'county_id' => $county->id,
                'county_name' => $county->name,
                'coverage_percentage' => $county->coverage_percentage ?? 0,
                'total_programs' => $totalPrograms, // Use calculated value
                'total_participants' => $county->total_participants ?? 0,
                'total_facilities' => $county->total_facilities ?? 0,
                'facilities_with_programs' => $county->facilities_with_programs ?? 0
            ];
            
            // Map all variations to the same stats
            foreach ($variations as $variation) {
                $countyStatsMap[$variation] = $stats;
            }
        }
        
        // Process each feature and merge with statistics
        $processedFeatures = [];
        foreach ($geojsonData['features'] as $feature) {
            // Get county name from the COUNTY property (based on your sample structure)
            $geoCountyName = $feature['properties']['COUNTY'] ?? null;
            
            if ($geoCountyName) {
                // Try to match with our database county statistics
                $matched = false;
                
                // Try exact match first (case-sensitive)
                if (isset($countyStatsMap[$geoCountyName])) {
                    $feature['properties'] = array_merge(
                        $feature['properties'], 
                        $countyStatsMap[$geoCountyName]
                    );
                    $matched = true;
                } else {
                    // Try various normalized matching approaches
                    $matchingVariations = [
                        strtoupper($geoCountyName),                                    // TURKANA
                        strtolower($geoCountyName),                                    // turkana  
                        ucfirst(strtolower($geoCountyName)),                          // Turkana
                        strtoupper(str_replace(' ', '', $geoCountyName)),             // Remove spaces + uppercase
                        strtoupper(str_replace([' ', '-', "'", '.'], '', $geoCountyName)) // Remove special chars
                    ];
                    
                    foreach ($matchingVariations as $variation) {
                        if (isset($countyStatsMap[$variation])) {
                            $feature['properties'] = array_merge(
                                $feature['properties'], 
                                $countyStatsMap[$variation]
                            );
                            $matched = true;
                            break;
                        }
                    }
                }
                
                // If still no match, set default values but preserve the GeoJSON county name
                if (!$matched) {
                    $feature['properties'] = array_merge($feature['properties'], [
                        'county_id' => null,
                        'county_name' => $geoCountyName,
                        'coverage_percentage' => 0,
                        'total_programs' => 0,
                        'total_participants' => 0,
                        'total_facilities' => 0,
                        'facilities_with_programs' => 0
                    ]);
                }
            } else {
                // No COUNTY property found - this shouldn't happen with your structure
                $feature['properties'] = array_merge($feature['properties'], [
                    'county_id' => null,
                    'county_name' => 'Unknown County',
                    'coverage_percentage' => 0,
                    'total_programs' => 0,
                    'total_participants' => 0,
                    'total_facilities' => 0,
                    'facilities_with_programs' => 0
                ]);
            }
            
            $processedFeatures[] = $feature;
        }
        
        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $processedFeatures
        ]);
    }

    public function county($countyId, Request $request)
    {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');
        
        $county = County::with(['subcounties', 'facilities.facilityType'])->findOrFail($countyId);
        
        if ($mode === 'training') {
            $programs = $this->getTrainingsForCounty($countyId, $selectedYear);
        } else {
            $programs = $this->getMentorshipsForCounty($countyId, $selectedYear);
        }

        $coverageData = $this->getCoverageData($countyId, $selectedYear, $mode);
        
        $availableYears = Training::selectRaw('YEAR(start_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->filter();

        $breadcrumbs = [
            ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
            ['name' => $county->name . ' County', 'url' => null]
        ];

        return view('analytics.dashboard.county', compact(
            'county', 'programs', 'coverageData', 'availableYears', 
            'selectedYear', 'mode', 'breadcrumbs'
        ));
    }

    public function program($countyId, $programId, Request $request)
    {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');
        
        $county = County::findOrFail($countyId);
        $program = Training::findOrFail($programId);
        
        $facilities = $this->getFacilitiesForProgram($countyId, $programId, $mode);
        $programStats = $this->getProgramStats($countyId, $programId, $mode);
        
        $availableYears = Training::selectRaw('YEAR(start_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->filter();

        $breadcrumbs = [
            ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
            ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.county', ['county' => $countyId, 'year' => $selectedYear, 'mode' => $mode])],
            ['name' => $program->title, 'url' => null]
        ];

        return view('analytics.dashboard.program', compact(
            'county', 'program', 'facilities', 'programStats', 'availableYears', 
            'selectedYear', 'mode', 'breadcrumbs'
        ));
    }

    public function facility($countyId, $programId, $facilityId, Request $request)
    {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');
        
        $county = County::findOrFail($countyId);
        $program = Training::findOrFail($programId);
        $facility = Facility::with(['facilityType', 'subcounty'])->findOrFail($facilityId);
        
        $participants = $this->getParticipantsForFacility($programId, $facilityId);
        $facilityStats = $this->getFacilityStats($programId, $facilityId);
        
        $availableYears = Training::selectRaw('YEAR(start_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->filter();

        $breadcrumbs = [
            ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
            ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.county', ['county' => $countyId, 'year' => $selectedYear, 'mode' => $mode])],
            ['name' => $program->title, 'url' => route('analytics.dashboard.program', ['county' => $countyId, 'program' => $programId, 'year' => $selectedYear, 'mode' => $mode])],
            ['name' => $facility->name, 'url' => null]
        ];

        return view('analytics.dashboard.facility', compact(
            'county', 'program', 'facility', 'participants', 'facilityStats', 
            'availableYears', 'selectedYear', 'mode', 'breadcrumbs'
        ));
    }

    public function participant($countyId, $programId, $facilityId, $participantId, Request $request)
    {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');
        
        $county = County::findOrFail($countyId);
        $program = Training::findOrFail($programId);
        $facility = Facility::findOrFail($facilityId);
        $participant = TrainingParticipant::with([
            'user.facility', 'user.department', 'user.cadre',
            'assessmentResults.assessmentCategory'
        ])->findOrFail($participantId);

        // Get training history
        $trainingHistory = TrainingParticipant::where('user_id', $participant->user_id)
            ->where('id', '!=', $participantId)
            ->with(['training', 'assessmentResults'])
            ->latest('registration_date')
            ->get();

        $availableYears = Training::selectRaw('YEAR(start_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->filter();

        $breadcrumbs = [
            ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
            ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.county', ['county' => $countyId, 'year' => $selectedYear, 'mode' => $mode])],
            ['name' => $program->title, 'url' => route('analytics.dashboard.program', ['county' => $countyId, 'program' => $programId, 'year' => $selectedYear, 'mode' => $mode])],
            ['name' => $facility->name, 'url' => route('analytics.dashboard.facility', ['county' => $countyId, 'program' => $programId, 'facility' => $facilityId, 'year' => $selectedYear, 'mode' => $mode])],
            ['name' => $participant->user->full_name, 'url' => null]
        ];

        return view('analytics.dashboard.participant', compact(
            'county', 'program', 'facility', 'participant', 'trainingHistory', 
            'availableYears', 'selectedYear', 'mode', 'breadcrumbs'
        ));
    }

    // Helper Methods
    private function getCountiesData($year, $mode)
    {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';
        
        $query = County::withCount([
            'facilities as total_facilities',
            'facilities as facilities_with_programs' => function ($query) use ($trainingType, $year) {
                if ($trainingType === 'global_training') {
                    $query->whereHas('users.trainingParticipations.training', function ($q) use ($year) {
                        $q->where('type', 'global_training');
                        if (!empty($year)) {
                            $q->whereYear('start_date', $year);
                        }
                    });
                } else {
                    $query->whereHas('trainings', function ($q) use ($year) {
                        $q->where('type', 'facility_mentorship');
                        if (!empty($year)) {
                            $q->whereYear('start_date', $year);
                        }
                    });
                }
            }
        ]);

        return $query->get()->map(function ($county) use ($year, $mode) {
            $county->coverage_percentage = $county->total_facilities > 0 
                ? round(($county->facilities_with_programs / $county->total_facilities) * 100, 1)
                : 0;
            
            $county->total_participants = $this->getCountyParticipants($county->id, $year, $mode);
            return $county;
        });
    }

    private function getCountyParticipants($countyId, $year, $mode)
    {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';
        
        $query = TrainingParticipant::whereHas('user.facility.subcounty', function ($query) use ($countyId) {
            $query->where('county_id', $countyId);
        })->whereHas('training', function ($query) use ($trainingType, $year) {
            $query->where('type', $trainingType);
            if (!empty($year)) {
                $query->whereYear('start_date', $year);
            }
        });

        return $query->distinct('user_id')->count();
    }

    private function getCountyPrograms($countyId, $year, $mode)
    {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';
        
        $query = Training::where('type', $trainingType);
        
        if (!empty($year)) {
            $query->whereYear('start_date', $year);
        }

        if ($mode === 'training') {
            $query->whereHas('participants.user.facility.subcounty', function ($q) use ($countyId) {
                $q->where('county_id', $countyId);
            });
        } else {
            $query->whereHas('facility.subcounty', function ($q) use ($countyId) {
                $q->where('county_id', $countyId);
            });
        }

        return $query->count();
    }

    private function generateHeatmapData($counties)
    {
        return $counties->map(function ($county) {
            return [
                'name' => $county->name,
                'value' => $county->coverage_percentage,
                'participants' => $county->total_participants,
                'facilities' => $county->total_facilities,
                'id' => $county->id
            ];
        });
    }

    private function getSummaryStats($year, $mode)
    {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';
        
        $totalProgramsQuery = Training::where('type', $trainingType);
        if (!empty($year)) {
            $totalProgramsQuery->whereYear('start_date', $year);
        }
        $totalPrograms = $totalProgramsQuery->count();

        $totalParticipantsQuery = TrainingParticipant::whereHas('training', function ($query) use ($trainingType, $year) {
            $query->where('type', $trainingType);
            if (!empty($year)) {
                $query->whereYear('start_date', $year);
            }
        });
        $totalParticipants = $totalParticipantsQuery->distinct('user_id')->count();

        $totalFacilitiesQuery = Facility::whereHas($mode === 'training' ? 'users.trainingParticipations.training' : 'trainings', function ($query) use ($trainingType, $year) {
            $query->where('type', $trainingType);
            if (!empty($year)) {
                $query->whereYear('start_date', $year);
            }
        });
        $totalFacilities = $totalFacilitiesQuery->count();

        $completionRate = $this->getOverallCompletionRate($year, $mode);

        return compact('totalPrograms', 'totalParticipants', 'totalFacilities', 'completionRate');
    }

    private function getTrainingsForCounty($countyId, $year)
    {
        $query = Training::where('type', 'global_training')
            ->whereHas('participants.user.facility.subcounty', function ($query) use ($countyId) {
                $query->where('county_id', $countyId);
            });

        if (!empty($year)) {
            $query->whereYear('start_date', $year);
        }

        return $query->withCount([
                'participants as county_participants' => function ($query) use ($countyId) {
                    $query->whereHas('user.facility.subcounty', function ($q) use ($countyId) {
                        $q->where('county_id', $countyId);
                    });
                }
            ])
            ->get();
    }

    private function getMentorshipsForCounty($countyId, $year)
    {
        $query = Training::where('type', 'facility_mentorship')
            ->whereHas('facility.subcounty', function ($query) use ($countyId) {
                $query->where('county_id', $countyId);
            });

        if (!empty($year)) {
            $query->whereYear('start_date', $year);
        }

        return $query->withCount(['participants as mentees_count'])
            ->with('facility')
            ->get();
    }

    private function getCoverageData($countyId, $year, $mode)
    {
        // Coverage by Department
        $departmentCoverage = Department::withCount([
            'users as county_users' => function ($query) use ($countyId) {
                $query->whereHas('facility.subcounty', function ($q) use ($countyId) {
                    $q->where('county_id', $countyId);
                });
            },
            'users as trained_users' => function ($query) use ($countyId, $year, $mode) {
                $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';
                $query->whereHas('facility.subcounty', function ($q) use ($countyId) {
                    $q->where('county_id', $countyId);
                })->whereHas('trainingParticipations.training', function ($q) use ($trainingType, $year) {
                    $q->where('type', $trainingType);
                    if (!empty($year)) {
                        $q->whereYear('start_date', $year);
                    }
                });
            }
        ])->get()->map(function ($dept) {
            $dept->coverage_percentage = $dept->county_users > 0 
                ? round(($dept->trained_users / $dept->county_users) * 100, 1) 
                : 0;
            return $dept;
        });

        // Coverage by Cadre
        $cadreCoverage = Cadre::withCount([
            'users as county_users' => function ($query) use ($countyId) {
                $query->whereHas('facility.subcounty', function ($q) use ($countyId) {
                    $q->where('county_id', $countyId);
                });
            },
            'users as trained_users' => function ($query) use ($countyId, $year, $mode) {
                $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';
                $query->whereHas('facility.subcounty', function ($q) use ($countyId) {
                    $q->where('county_id', $countyId);
                })->whereHas('trainingParticipations.training', function ($q) use ($trainingType, $year) {
                    $q->where('type', $trainingType);
                    if (!empty($year)) {
                        $q->whereYear('start_date', $year);
                    }
                });
            }
        ])->get()->map(function ($cadre) {
            $cadre->coverage_percentage = $cadre->county_users > 0 
                ? round(($cadre->trained_users / $cadre->county_users) * 100, 1) 
                : 0;
            return $cadre;
        });

        // Coverage by Facility Type
        $facilityTypeCoverage = FacilityType::withCount([
            'facilities as county_facilities' => function ($query) use ($countyId) {
                $query->whereHas('subcounty', function ($q) use ($countyId) {
                    $q->where('county_id', $countyId);
                });
            },
            'facilities as facilities_with_training' => function ($query) use ($countyId, $year, $mode) {
                $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';
                $query->whereHas('subcounty', function ($q) use ($countyId) {
                    $q->where('county_id', $countyId);
                });
                
                if ($mode === 'training') {
                    $query->whereHas('users.trainingParticipations.training', function ($q) use ($year) {
                        $q->where('type', 'global_training');
                        if (!empty($year)) {
                            $q->whereYear('start_date', $year);
                        }
                    });
                } else {
                    $query->whereHas('trainings', function ($q) use ($year) {
                        $q->where('type', 'facility_mentorship');
                        if (!empty($year)) {
                            $q->whereYear('start_date', $year);
                        }
                    });
                }
            }
        ])->get()->map(function ($type) {
            $type->coverage_percentage = $type->county_facilities > 0 
                ? round(($type->facilities_with_training / $type->county_facilities) * 100, 1) 
                : 0;
            return $type;
        });

        return compact('departmentCoverage', 'cadreCoverage', 'facilityTypeCoverage');
    }

    private function getFacilitiesForProgram($countyId, $programId, $mode)
    {
        if ($mode === 'training') {
            return Facility::whereHas('users.trainingParticipations', function ($query) use ($programId) {
                    $query->where('training_id', $programId);
                })
                ->whereHas('subcounty', function ($query) use ($countyId) {
                    $query->where('county_id', $countyId);
                })
                ->with(['subcounty', 'facilityType'])
                ->withCount([
                    'users as participants_count' => function ($query) use ($programId) {
                        $query->whereHas('trainingParticipations', function ($q) use ($programId) {
                            $q->where('training_id', $programId);
                        });
                    }
                ])
                ->get();
        } else {
            // For mentorship, there's typically one facility per program
            $training = Training::with('facility.facilityType', 'facility.subcounty')->findOrFail($programId);
            return collect([$training->facility]);
        }
    }

    private function getProgramStats($countyId, $programId, $mode)
    {
        $totalParticipants = TrainingParticipant::where('training_id', $programId)
            ->whereHas('user.facility.subcounty', function ($query) use ($countyId) {
                $query->where('county_id', $countyId);
            })->count();

        $completedParticipants = TrainingParticipant::where('training_id', $programId)
            ->whereHas('user.facility.subcounty', function ($query) use ($countyId) {
                $query->where('county_id', $countyId);
            })
            ->where('completion_status', 'completed')
            ->count();

        $completionPercentage = $totalParticipants > 0 ? round(($completedParticipants / $totalParticipants) * 100, 1) : 0;

        return compact('totalParticipants', 'completedParticipants', 'completionPercentage');
    }

    private function getParticipantsForFacility($programId, $facilityId)
    {
        return TrainingParticipant::where('training_id', $programId)
            ->whereHas('user', function ($query) use ($facilityId) {
                $query->where('facility_id', $facilityId);
            })
            ->with(['user.department', 'user.cadre', 'assessmentResults.assessmentCategory'])
            ->get();
    }

    private function getFacilityStats($programId, $facilityId)
    {
        $participants = $this->getParticipantsForFacility($programId, $facilityId);

        $departmentStats = $participants->groupBy('user.department.name')->map(function ($group, $dept) {
            return [
                'department' => $dept,
                'count' => $group->count(),
                'completed' => $group->where('completion_status', 'completed')->count()
            ];
        });

        $cadreStats = $participants->groupBy('user.cadre.name')->map(function ($group, $cadre) {
            return [
                'cadre' => $cadre,
                'count' => $group->count(),
                'completed' => $group->where('completion_status', 'completed')->count()
            ];
        });

        return compact('departmentStats', 'cadreStats');
    }

    private function getOverallCompletionRate($year, $mode)
    {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';
        
        $totalQuery = TrainingParticipant::whereHas('training', function ($query) use ($trainingType, $year) {
            $query->where('type', $trainingType);
            if (!empty($year)) {
                $query->whereYear('start_date', $year);
            }
        });
        $total = $totalQuery->count();

        $completedQuery = TrainingParticipant::whereHas('training', function ($query) use ($trainingType, $year) {
            $query->where('type', $trainingType);
            if (!empty($year)) {
                $query->whereYear('start_date', $year);
            }
        })->where('completion_status', 'completed');
        $completed = $completedQuery->count();

        return $total > 0 ? round(($completed / $total) * 100, 1) : 0;
    }
}