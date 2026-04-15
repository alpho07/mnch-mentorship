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

class AnalyticsDashboardController extends Controller {

    public function index(Request $request) {
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
        $trainingsList = $this->getTrainingsList($selectedYear, $mode);
        $summaryStats = $this->getSummaryStats($selectedYear, $mode);
        $chartData = $this->getChartData($selectedYear, $mode);

        return view('analytics.dashboard.index', compact(
                        'counties', 'trainingsList', 'summaryStats', 'chartData',
                        'availableYears', 'selectedYear', 'mode'
                ));
    }

    public function geojson(Request $request) {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');
        $trainingId = $request->get('training_id', '');

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
        $counties = $this->getCountiesData($selectedYear, $mode, $trainingId);

        // Create a mapping of county statistics by normalized name
        $countyStatsMap = [];
        foreach ($counties as $county) {
            // Calculate total programs for this county properly
            $totalPrograms = $this->getCountyPrograms($county->id, $selectedYear, $mode, $trainingId);

            // Get total facilities with programs for this county
            if ($mode === 'training') {
                $facilitiesWithPrograms = Facility::whereHas('users.trainingParticipations.training', function ($query) use ($selectedYear, $trainingId) {
                    $query->where('type', 'global_training');
                    if (!empty($selectedYear)) {
                        $query->whereYear('start_date', $selectedYear);
                    }
                    if ($trainingId) {
                        $query->where('id', $trainingId);
                    }
                })->whereHas('subcounty', function ($query) use ($county) {
                    $query->where('county_id', $county->id);
                })->count();
            } else {
                // For mentorship, count facilities that host mentorship programs
                $facilitiesWithPrograms = Facility::whereHas('trainings', function ($query) use ($selectedYear, $trainingId) {
                    $query->where('type', 'facility_mentorship');
                    if (!empty($selectedYear)) {
                        $query->whereYear('start_date', $selectedYear);
                    }
                    if ($trainingId) {
                        $query->where('id', $trainingId);
                    }
                })->whereHas('subcounty', function ($query) use ($county) {
                    $query->where('county_id', $county->id);
                })->count();
            }

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
                'total_programs' => $totalPrograms,
                'total_participants' => $county->total_participants ?? 0,
                'total_facilities' => $county->total_facilities ?? 0,
                'facilities_with_programs' => $facilitiesWithPrograms
            ];

            // Map all variations to the same stats
            foreach ($variations as $variation) {
                $countyStatsMap[$variation] = $stats;
            }
        }

        // Process each feature and merge with statistics
        $processedFeatures = [];
        foreach ($geojsonData['features'] as $feature) {
            // Get county name from the COUNTY property
            $geoCountyName = $feature['properties']['COUNTY'] ?? null;

            if ($geoCountyName) {
                // Try to match with our database county statistics
                $matched = false;

                // Try exact match first
                if (isset($countyStatsMap[$geoCountyName])) {
                    $feature['properties'] = array_merge(
                            $feature['properties'],
                            $countyStatsMap[$geoCountyName]
                    );
                    $matched = true;
                } else {
                    // Try various normalized matching approaches
                    $matchingVariations = [
                        strtoupper($geoCountyName),
                        strtolower($geoCountyName),
                        ucfirst(strtolower($geoCountyName)),
                        strtoupper(str_replace(' ', '', $geoCountyName)),
                        strtoupper(str_replace([' ', '-', "'", '.'], '', $geoCountyName))
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

                // If still no match, set default values
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
                // No COUNTY property found
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

    public function county($countyId, Request $request) {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');
        $selectedTraining = $request->get('training_id', ''); // This is key for training flow
        $view = $request->get('view', 'programs'); // NEW: 'programs' or 'facilities'

        if ($mode === 'training') {
            // For training mode, show facilities filtered by selected program (if any)
            $county = County::with(['subcounties', 'facilities.facilityType'])->findOrFail($countyId);
            
            if ($selectedTraining) {
                // EXISTING PATH: Program is selected - show facilities for that specific program
                $facilities = $this->getFacilitiesForSpecificTraining($countyId, $selectedTraining);
                $programs = Training::where('id', $selectedTraining)->get(); // The selected program
                $breadcrumbTitle = $programs->first()->title ?? 'Training Program';
                $view = 'facilities'; // Force facilities view when training is selected
            } elseif ($view === 'facilities') {
                // NEW PATH: No program selected, but user wants to see all training facilities in county
                return $this->countyFacilities($countyId, $request);
            } else {
                // EXISTING PATH: No program selected - show programs list
                $facilities = null; // Don't show facilities, show programs instead
                $programs = $this->getTrainingsForCounty($countyId, $selectedYear);
                $breadcrumbTitle = 'All Training Programs';
            }

            $coverageData = $this->getCoverageData($countyId, $selectedYear, $mode);

            $availableYears = Training::selectRaw('YEAR(start_date) as year')
                    ->distinct()
                    ->orderBy('year', 'desc')
                    ->pluck('year')
                    ->filter();

            $breadcrumbs = [
                ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
                ['name' => $county->name . ' County - ' . $breadcrumbTitle, 'url' => null]
            ];

            return view('analytics.dashboard.county', compact(
                            'county', 'programs', 'facilities', 'coverageData', 'availableYears',
                            'selectedYear', 'selectedTraining', 'mode', 'breadcrumbs', 'view'
                    ));
            
        } else {
            // For mentorship mode, return county mentorships JSON for AJAX
            return $this->countyMentorships($countyId, $request);
        }
    }

    /**
     * NEW METHOD: Handle county facilities view when no specific training is selected
     * Path: County -> All Training Facilities -> Individual Facility Programs -> Participants
     */
    public function countyFacilities($countyId, Request $request) {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');
        
        $county = County::with(['subcounties'])->findOrFail($countyId);
        
        // Get all facilities in this county that have training participants
        $facilities = Facility::whereHas('users.trainingParticipations.training', function ($query) use ($selectedYear) {
            $query->where('type', 'global_training');
            if (!empty($selectedYear)) {
                $query->whereYear('start_date', $selectedYear);
            }
        })
        ->whereHas('subcounty', function ($query) use ($countyId) {
            $query->where('county_id', $countyId);
        })
        ->with(['subcounty', 'facilityType'])
        ->withCount([
            'users as total_participants' => function ($query) use ($selectedYear) {
                $query->whereHas('trainingParticipations.training', function ($q) use ($selectedYear) {
                    $q->where('type', 'global_training');
                    if (!empty($selectedYear)) {
                        $q->whereYear('start_date', $selectedYear);
                    }
                });
            }
        ])
        ->addSelect([
            'unique_training_programs' => function ($query) use ($selectedYear) {
                $query->selectRaw('COUNT(DISTINCT trainings.id)')
                        ->from('training_participants')
                        ->join('users', 'training_participants.user_id', '=', 'users.id')
                        ->join('trainings', 'training_participants.training_id', '=', 'trainings.id')
                        ->whereColumn('users.facility_id', 'facilities.id')
                        ->where('trainings.type', 'global_training');
                if (!empty($selectedYear)) {
                    $query->whereYear('trainings.start_date', $selectedYear);
                }
            }
        ])
        ->orderBy('total_participants', 'desc')
        ->get();

        $coverageData = $this->getCoverageData($countyId, $selectedYear, $mode);

        $availableYears = Training::selectRaw('YEAR(start_date) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->filter();

        $breadcrumbs = [
            ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index', ['mode' => 'training'])],
            ['name' => $county->name . ' County Programs', 'url' => route('analytics.dashboard.county', ['county' => $countyId, 'mode' => 'training', 'year' => $selectedYear])],
            ['name' => 'Training Facilities', 'url' => null]
        ];

        return view('analytics.dashboard.county-facilities', compact(
                        'county', 'facilities', 'coverageData', 'availableYears',
                        'selectedYear', 'mode', 'breadcrumbs'
                ));
    }

    /**
     * NEW METHOD: Handle individual facility's training programs 
     * Path: County -> Facilities -> Facility Programs -> Participants
     */
    public function facilityPrograms($countyId, $facilityId, Request $request) {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');

        $county = County::findOrFail($countyId);
        $facility = Facility::with(['facilityType', 'subcounty'])->findOrFail($facilityId);

        // Get all training programs this facility has participated in
        $programs = Training::where('type', 'global_training')
                ->whereHas('participants.user', function ($query) use ($facilityId) {
                    $query->where('facility_id', $facilityId);
                })
                ->when(!empty($selectedYear), function ($query) use ($selectedYear) {
                    $query->whereYear('start_date', $selectedYear);
                })
                ->withCount([
                    'participants as facility_participants' => function ($query) use ($facilityId) {
                        $query->whereHas('user', function ($q) use ($facilityId) {
                            $q->where('facility_id', $facilityId);
                        });
                    }
                ])
                ->orderBy('start_date', 'desc')
                ->get();

        $availableYears = Training::selectRaw('YEAR(start_date) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->filter();

        $breadcrumbs = [
            ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index', ['mode' => 'training'])],
            ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.county', ['county' => $countyId, 'mode' => 'training', 'view' => 'facilities', 'year' => $selectedYear])],
            ['name' => $facility->name . ' Programs', 'url' => null]
        ];

        return view('analytics.dashboard.facility-programs', compact(
                        'county', 'facility', 'programs', 'availableYears',
                        'selectedYear', 'mode', 'breadcrumbs'
                ));
    }

    // County mentorships API for sidebar (mentorship mode)
    public function countyMentorships($countyId, Request $request) {
        $selectedYear = $request->get('year', '');
        
        $county = County::findOrFail($countyId);

        // Get facilities with mentorship programs
        $facilities = Facility::whereHas('trainings', function ($query) use ($selectedYear) {
            $query->where('type', 'facility_mentorship');
            if (!empty($selectedYear)) {
                $query->whereYear('start_date', $selectedYear);
            }
        })
        ->whereHas('subcounty', function ($query) use ($countyId) {
            $query->where('county_id', $countyId);
        })
        ->with(['subcounty', 'facilityType'])
        ->withCount([
            'trainings as mentorship_count' => function ($query) use ($selectedYear) {
                $query->where('type', 'facility_mentorship');
                if (!empty($selectedYear)) {
                    $query->whereYear('start_date', $selectedYear);
                }
            }
        ])
        ->addSelect([
            'total_mentees' => function ($query) use ($selectedYear) {
                $query->selectRaw('COUNT(DISTINCT training_participants.user_id)')
                        ->from('trainings')
                        ->join('training_participants', 'trainings.id', '=', 'training_participants.training_id')
                        ->whereColumn('trainings.facility_id', 'facilities.id')
                        ->where('trainings.type', 'facility_mentorship');
                if (!empty($selectedYear)) {
                    $query->whereYear('trainings.start_date', $selectedYear);
                }
            }
        ])
        ->get();

        // Calculate summary stats
        $totalMentorships = $facilities->sum('mentorship_count');
        $totalMentees = $facilities->sum('total_mentees');
        $totalFacilities = $facilities->count();

        return response()->json([
            'county' => [
                'id' => $county->id,
                'name' => $county->name
            ],
            'summary' => [
                'total_facilities' => $totalFacilities,
                'total_mentorships' => $totalMentorships,
                'total_mentees' => $totalMentees
            ],
            'facilities' => $facilities->map(function ($facility) {
                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'subcounty' => $facility->subcounty->name ?? 'N/A',
                    'facility_type' => $facility->facilityType->name ?? 'N/A',
                    'mfl_code' => $facility->mfl_code,
                    'mentorship_count' => $facility->mentorship_count ?? 0,
                    'total_mentees' => $facility->total_mentees ?? 0
                ];
            })
        ]);
    }

    // Facility mentorships view (mentorship mode)
    public function facilityMentorships($countyId, $facilityId, Request $request) {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'mentorship');

        $county = County::findOrFail($countyId);
        $facility = Facility::with(['facilityType', 'subcounty'])->findOrFail($facilityId);

        // Get mentorship programs for this facility
        $mentorships = Training::where('type', 'facility_mentorship')
                ->where('facility_id', $facilityId)
                ->when(!empty($selectedYear), function ($query) use ($selectedYear) {
                    $query->whereYear('start_date', $selectedYear);
                })
                ->withCount(['participants as mentees_count'])
                ->with(['mentor'])
                ->orderBy('start_date', 'desc')
                ->get();

        $availableYears = Training::selectRaw('YEAR(start_date) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->filter();

        $breadcrumbs = [
            ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
            ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.index', ['mode' => 'mentorship'])],
            ['name' => $facility->name . ' Mentorships', 'url' => null]
        ];

        return view('analytics.dashboard.facility-mentorships', compact(
                        'county', 'facility', 'mentorships', 'availableYears',
                        'selectedYear', 'mode', 'breadcrumbs'
                ));
    }

    public function program($countyId, $programId, Request $request) {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');

        $county = County::findOrFail($countyId);
        $program = Training::findOrFail($programId);

        if ($mode === 'training') {
            // For training: show facilities that participated in this specific program
            $facilities = $this->getFacilitiesForProgram($countyId, $programId, $mode);
            $programStats = $this->getProgramStats($countyId, $programId, $mode);

            $availableYears = Training::selectRaw('YEAR(start_date) as year')
                    ->distinct()
                    ->orderBy('year', 'desc')
                    ->pluck('year')
                    ->filter();

            $breadcrumbs = [
                ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
                ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.county', ['county' => $countyId, 'year' => $selectedYear, 'mode' => $mode, 'training_id' => $programId])],
                ['name' => $program->title, 'url' => null]
            ];

            return view('analytics.dashboard.county-program', compact(
                            'county', 'program', 'facilities', 'programStats', 'availableYears',
                            'selectedYear', 'mode', 'breadcrumbs'
                    ));
        } else {
            // For mentorships, go directly to participants
            $participants = $this->getParticipantsForProgram($programId);
            
            $availableYears = Training::selectRaw('YEAR(start_date) as year')
                    ->distinct()
                    ->orderBy('year', 'desc')
                    ->pluck('year')
                    ->filter();

            $breadcrumbs = [
                ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
                ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.index', ['mode' => 'mentorship'])],
                ['name' => $program->title . ' Participants', 'url' => null]
            ];

            return view('analytics.dashboard.mentorship-participants', compact(
                            'county', 'program', 'participants', 'availableYears',
                            'selectedYear', 'mode', 'breadcrumbs'
                    ));
        }
    }

    public function facility($countyId, $programId, $facilityId, Request $request) {
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
            ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.county', ['county' => $countyId, 'year' => $selectedYear, 'mode' => $mode, 'training_id' => $programId])],
            ['name' => $program->title, 'url' => route('analytics.dashboard.program', ['county' => $countyId, 'program' => $programId, 'year' => $selectedYear, 'mode' => $mode])],
            ['name' => $facility->name, 'url' => null]
        ];

        return view('analytics.dashboard.facility', compact(
                        'county', 'program', 'facility', 'participants', 'facilityStats',
                        'availableYears', 'selectedYear', 'mode', 'breadcrumbs'
                ));
    }
    
    public function mentorshipParticipant($countyId, $programId, $participantId, Request $request) {
    $selectedYear = $request->get('year', '');
    $mode = $request->get('mode', 'mentorship');

    $county = County::findOrFail($countyId);
    $program = Training::findOrFail($programId);
    
    $participant = TrainingParticipant::with([
                'user.facility', 'user.department', 'user.cadre',
                'assessmentResults.assessmentCategory'
            ])->findOrFail($participantId);

    // Get the facility from the participant's user
    $facility = $participant->user->facility;

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
        ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.index', ['mode' => 'mentorship'])],
        ['name' => $program->title . ' Participants', 'url' => route('analytics.dashboard.program', ['county' => $countyId, 'program' => $programId, 'year' => $selectedYear, 'mode' => $mode])],
        ['name' => $participant->user->full_name, 'url' => null]
    ];

    return view('analytics.dashboard.participant', compact(
                    'county', 'program', 'facility', 'participant', 'trainingHistory',
                    'availableYears', 'selectedYear', 'mode', 'breadcrumbs'
            ));
}

   public function participant($countyId, $programId, $facilityId, $participantId, Request $request) {
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

    // Only for training mode (mentorship uses mentorshipParticipant method)
    $breadcrumbs = [
        ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index')],
        ['name' => $county->name . ' County', 'url' => route('analytics.dashboard.county', ['county' => $countyId, 'year' => $selectedYear, 'mode' => $mode, 'training_id' => $programId])],
        ['name' => $program->title, 'url' => route('analytics.dashboard.program', ['county' => $countyId, 'program' => $programId, 'year' => $selectedYear, 'mode' => $mode])],
        ['name' => $facility->name, 'url' => route('analytics.dashboard.facility', ['county' => $countyId, 'program' => $programId, 'facility' => $facilityId, 'year' => $selectedYear, 'mode' => $mode])],
        ['name' => $participant->user->full_name, 'url' => null]
    ];

    return view('analytics.dashboard.participant', compact(
                    'county', 'program', 'facility', 'participant', 'trainingHistory',
                    'availableYears', 'selectedYear', 'mode', 'breadcrumbs'
            ));
}

    // AJAX endpoints (existing functionality preserved)
    public function getCountyData(Request $request) {
        // Existing functionality preserved
        return response()->json(['message' => 'Method not implemented yet']);
    }

    public function getCoverageCharts(Request $request) {
        // Existing functionality preserved
        return response()->json(['message' => 'Method not implemented yet']);
    }

    public function exportData(Request $request) {
        // Existing functionality preserved
        return response()->json(['message' => 'Method not implemented yet']);
    }

    public function getTrainingData(Request $request) {
        $selectedYear = $request->get('year', '');
        $mode = $request->get('mode', 'training');
        $trainingId = $request->get('training_id', '');

        try {
            // Get chart data
            $chartData = $this->getChartDataSimple($selectedYear, $mode, $trainingId);

            // Get summary stats
            $summaryStats = $this->getSummaryStatsSimple($selectedYear, $mode, $trainingId);

            return response()->json([
                        'success' => true,
                        'chartData' => $chartData,
                        'summaryStats' => $summaryStats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                        'success' => false,
                        'error' => $e->getMessage()
                            ], 500);
        }
    }

    // Helper Methods
    private function getFacilitiesForSpecificTraining($countyId, $trainingId) {
        return Facility::whereHas('users.trainingParticipations', function ($query) use ($trainingId) {
                    $query->where('training_id', $trainingId);
                })
                ->whereHas('subcounty', function ($query) use ($countyId) {
                    $query->where('county_id', $countyId);
                })
                ->with(['subcounty', 'facilityType'])
                ->withCount([
                    'users as participants_count' => function ($query) use ($trainingId) {
                        $query->whereHas('trainingParticipations', function ($q) use ($trainingId) {
                            $q->where('training_id', $trainingId);
                        });
                    }
                ])
                ->get();
    }

    // Updated counties data method
    private function getCountiesData($year, $mode, $trainingId = null) {
        if ($mode === 'training') {
            // Existing training logic
            $query = County::withCount([
                'facilities as total_facilities',
                'facilities as facilities_with_programs' => function ($query) use ($year, $trainingId) {
                    $query->whereHas('users.trainingParticipations.training', function ($q) use ($year, $trainingId) {
                        $q->where('type', 'global_training');
                        if (!empty($year)) {
                            $q->whereYear('start_date', $year);
                        }
                        if ($trainingId) {
                            $q->where('id', $trainingId);
                        }
                    });
                }
            ]);
        } else {
            // Mentorship logic - get counties based on facilities hosting mentorship programs
            $query = County::withCount([
                'facilities as total_facilities',
                'facilities as facilities_with_programs' => function ($query) use ($year, $trainingId) {
                    // Facilities that have mentorship programs
                    $query->whereHas('trainings', function ($q) use ($year, $trainingId) {
                        $q->where('type', 'facility_mentorship');
                        if (!empty($year)) {
                            $q->whereYear('start_date', $year);
                        }
                        if ($trainingId) {
                            $q->where('id', $trainingId);
                        }
                    });
                }
            ]);
        }

        return $query->get()->map(function ($county) use ($year, $mode, $trainingId) {
            $county->coverage_percentage = $county->total_facilities > 0 ? 
                round(($county->facilities_with_programs / $county->total_facilities) * 100, 1) : 0;
            $county->total_participants = $this->getCountyParticipants($county->id, $year, $mode, $trainingId);
            return $county;
        });
    }

    private function getTrainingsList($year, $mode) {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';

        $query = Training::where('type', $trainingType);

        if (!empty($year)) {
            $query->whereYear('start_date', $year);
        }

        return $query->withCount([
                            'participants as total_participants'
                        ])
                        ->addSelect([
                            'facilities_count' => function ($query) use ($mode) {
                                if ($mode === 'training') {
                                    // For training: count distinct facilities with participants
                                    $query->selectRaw('COUNT(DISTINCT users.facility_id)')
                                            ->from('training_participants')
                                            ->join('users', 'training_participants.user_id', '=', 'users.id')
                                            ->whereColumn('training_participants.training_id', 'trainings.id');
                                } else {
                                    // For mentorship: count is always 1 (the facility hosting the mentorship)
                                    $query->selectRaw('1')
                                            ->whereColumn('trainings.facility_id', 'trainings.facility_id');
                                }
                            }
                        ])
                        ->with(['facility', 'county', 'partner'])
                        ->orderBy('start_date', 'desc')
                        ->get()
                        ->map(function ($training) {
                            // Calculate coverage percentage
                            $totalTargetFacilities = $this->getTotalTargetFacilities($training);
                            $training->coverage_percentage = $totalTargetFacilities > 0 ? 
                                round(($training->facilities_count / $totalTargetFacilities) * 100, 1) : 0;

                            // Get involved counties
                            $training->involved_counties = $this->getTrainingCounties($training->id);

                            return $training;
                        });
    }

    // Updated summary stats method
    private function getSummaryStats($year, $mode, $trainingId = null) {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';

        $totalProgramsQuery = Training::where('type', $trainingType);
        if (!empty($year)) {
            $totalProgramsQuery->whereYear('start_date', $year);
        }
        if ($trainingId) {
            $totalProgramsQuery->where('id', $trainingId);
        }
        $totalPrograms = $totalProgramsQuery->count();

        $totalParticipantsQuery = TrainingParticipant::whereHas('training', function ($query) use ($trainingType, $year, $trainingId) {
            $query->where('type', $trainingType);
            if (!empty($year)) {
                $query->whereYear('start_date', $year);
            }
            if ($trainingId) {
                $query->where('id', $trainingId);
            }
        });
        $totalParticipants = $totalParticipantsQuery->distinct('user_id')->count();

        // Total facilities calculation for mentorship
        if ($mode === 'training') {
            // For training: facilities with users participating in training programs
            $totalFacilitiesQuery = Facility::whereHas('users.trainingParticipations.training', function ($query) use ($year, $trainingId) {
                $query->where('type', 'global_training');
                if (!empty($year)) {
                    $query->whereYear('start_date', $year);
                }
                if ($trainingId) {
                    $query->where('id', $trainingId);
                }
            });
        } else {
            // For mentorship: facilities that host mentorship programs
            $totalFacilitiesQuery = Facility::whereHas('trainings', function ($query) use ($year, $trainingId) {
                $query->where('type', 'facility_mentorship');
                if (!empty($year)) {
                    $query->whereYear('start_date', $year);
                }
                if ($trainingId) {
                    $query->where('id', $trainingId);
                }
            });
        }
        $totalFacilities = $totalFacilitiesQuery->count();

        // Calculate facility coverage
        $allFacilities = Facility::count();
        $facilityCoverage = $allFacilities > 0 ? round(($totalFacilities / $allFacilities) * 100, 1) : 0;

        return compact('totalPrograms', 'totalParticipants', 'totalFacilities', 'facilityCoverage');
    }

    // Updated chart data method
    private function getChartData($year, $mode, $trainingId = null) {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';

        // Base query for participants
        $baseQuery = TrainingParticipant::whereHas('training', function ($query) use ($trainingType, $year, $trainingId) {
            $query->where('type', $trainingType);
            if (!empty($year)) {
                $query->whereYear('start_date', $year);
            }
            if ($trainingId) {
                $query->where('id', $trainingId);
            }
        });

        // Department data
        $departmentData = (clone $baseQuery)
                ->join('users', 'training_participants.user_id', '=', 'users.id')
                ->join('departments', 'users.department_id', '=', 'departments.id')
                ->select('departments.name', DB::raw('COUNT(DISTINCT training_participants.user_id) as count'))
                ->groupBy('departments.id', 'departments.name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

        // Cadre data
        $cadreData = (clone $baseQuery)
                ->join('users', 'training_participants.user_id', '=', 'users.id')
                ->join('cadres', 'users.cadre_id', '=', 'cadres.id')
                ->select('cadres.name', DB::raw('COUNT(DISTINCT training_participants.user_id) as count'))
                ->groupBy('cadres.id', 'cadres.name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

        // Facility type coverage - adjusted for mentorship
        if ($mode === 'training') {
            $facilityTypeData = FacilityType::withCount([
                'facilities as total_facilities',
                'facilities as facilities_with_training' => function ($query) use ($year, $trainingId) {
                    $query->whereHas('users.trainingParticipations.training', function ($q) use ($year, $trainingId) {
                        $q->where('type', 'global_training');
                        if (!empty($year)) {
                            $q->whereYear('start_date', $year);
                        }
                        if ($trainingId) {
                            $q->where('id', $trainingId);
                        }
                    });
                }
            ])->get()->map(function ($type) {
                $type->coverage_percentage = $type->total_facilities > 0 ? 
                    round(($type->facilities_with_training / $type->total_facilities) * 100, 1) : 0;
                return $type;
            });
        } else {
            // For mentorship - facilities hosting mentorship programs
            $facilityTypeData = FacilityType::withCount([
                'facilities as total_facilities',
                'facilities as facilities_with_training' => function ($query) use ($year, $trainingId) {
                    $query->whereHas('trainings', function ($q) use ($year, $trainingId) {
                        $q->where('type', 'facility_mentorship');
                        if (!empty($year)) {
                            $q->whereYear('start_date', $year);
                        }
                        if ($trainingId) {
                            $q->where('id', $trainingId);
                        }
                    });
                }
            ])->get()->map(function ($type) {
                $type->coverage_percentage = $type->total_facilities > 0 ? 
                    round(($type->facilities_with_training / $type->total_facilities) * 100, 1) : 0;
                return $type;
            });
        }

        // Monthly trends (last 6 months)
        $monthlyData = collect();
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $count = TrainingParticipant::whereHas('training', function ($query) use ($trainingType, $year, $trainingId) {
                        $query->where('type', $trainingType);
                        if (!empty($year)) {
                            $query->whereYear('start_date', $year);
                        }
                        if ($trainingId) {
                            $query->where('id', $trainingId);
                        }
                    })
                    ->whereBetween('registration_date', [$monthStart, $monthEnd])
                    ->count();

            $monthlyData->push([
                'month' => $date->format('M'),
                'count' => $count
            ]);
        }

        return [
            'departments' => $departmentData,
            'cadres' => $cadreData,
            'facilityTypes' => $facilityTypeData,
            'monthly' => $monthlyData
        ];
    }

    private function getChartDataSimple($year, $mode, $trainingId = null) {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';

        // Base query for participants
        $baseQuery = TrainingParticipant::whereHas('training', function ($query) use ($trainingType, $year, $trainingId) {
            $query->where('type', $trainingType);
            if (!empty($year)) {
                $query->whereYear('start_date', $year);
            }
            if ($trainingId) {
                $query->where('id', $trainingId);
            }
        });

        // Department data
        $departmentData = collect();
        try {
            $departmentData = (clone $baseQuery)
                    ->join('users', 'training_participants.user_id', '=', 'users.id')
                    ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
                    ->select('departments.name', DB::raw('COUNT(DISTINCT training_participants.user_id) as count'))
                    ->whereNotNull('departments.name')
                    ->groupBy('departments.id', 'departments.name')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get();
        } catch (\Exception $e) {
            $departmentData = collect([
                (object) ['name' => 'No Data', 'count' => 0]
            ]);
        }

        // Cadre data
        $cadreData = collect();
        try {
            $cadreData = (clone $baseQuery)
                    ->join('users', 'training_participants.user_id', '=', 'users.id')
                    ->leftJoin('cadres', 'users.cadre_id', '=', 'cadres.id')
                    ->select('cadres.name', DB::raw('COUNT(DISTINCT training_participants.user_id) as count'))
                    ->whereNotNull('cadres.name')
                    ->groupBy('cadres.id', 'cadres.name')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get();
        } catch (\Exception $e) {
            $cadreData = collect([
                (object) ['name' => 'No Data', 'count' => 0]
            ]);
        }

        // Facility type data for mentorship
        $facilityTypeData = collect();
        try {
            if ($mode === 'training') {
                $facilityTypeData = FacilityType::withCount([
                    'facilities as total_facilities',
                    'facilities as facilities_with_training' => function ($query) use ($year, $trainingId) {
                        $query->whereHas('users.trainingParticipations.training', function ($q) use ($year, $trainingId) {
                            $q->where('type', 'global_training');
                            if (!empty($year)) {
                                $q->whereYear('start_date', $year);
                            }
                            if ($trainingId) {
                                $q->where('id', $trainingId);
                            }
                        });
                    }
                ])->get()->map(function ($type) {
                    $type->coverage_percentage = $type->total_facilities > 0 ? 
                        round(($type->facilities_with_training / $type->total_facilities) * 100, 1) : 0;
                    return $type;
                });
            } else {
                // For mentorship
                $facilityTypeData = FacilityType::withCount([
                    'facilities as total_facilities',
                    'facilities as facilities_with_training' => function ($query) use ($year, $trainingId) {
                        $query->whereHas('trainings', function ($q) use ($year, $trainingId) {
                            $q->where('type', 'facility_mentorship');
                            if (!empty($year)) {
                                $q->whereYear('start_date', $year);
                            }
                            if ($trainingId) {
                                $q->where('id', $trainingId);
                            }
                        });
                    }
                ])->get()->map(function ($type) {
                    $type->coverage_percentage = $type->total_facilities > 0 ? 
                        round(($type->facilities_with_training / $type->total_facilities) * 100, 1) : 0;
                    return $type;
                });
            }
        } catch (\Exception $e) {
            $facilityTypeData = collect();
        }

        // Monthly trends
        $monthlyData = collect();
        try {
            for ($i = 5; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $monthStart = $date->copy()->startOfMonth();
                $monthEnd = $date->copy()->endOfMonth();

                $count = TrainingParticipant::whereHas('training', function ($query) use ($trainingType, $year, $trainingId) {
                            $query->where('type', $trainingType);
                            if (!empty($year)) {
                                $query->whereYear('start_date', $year);
                            }
                            if ($trainingId) {
                                $query->where('id', $trainingId);
                            }
                        })
                        ->whereBetween('registration_date', [$monthStart, $monthEnd])
                        ->count();

                $monthlyData->push([
                    'month' => $date->format('M'),
                    'count' => $count
                ]);
            }
        } catch (\Exception $e) {
            // Fallback data
            $monthlyData = collect([
                ['month' => 'Jan', 'count' => 0],
                ['month' => 'Feb', 'count' => 0],
                ['month' => 'Mar', 'count' => 0],
                ['month' => 'Apr', 'count' => 0],
                ['month' => 'May', 'count' => 0],
                ['month' => 'Jun', 'count' => 0]
            ]);
        }

        return [
            'departments' => $departmentData,
            'cadres' => $cadreData,
            'facilityTypes' => $facilityTypeData,
            'monthly' => $monthlyData
        ];
    }

    private function getSummaryStatsSimple($year, $mode, $trainingId = null) {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';

        try {
            $totalProgramsQuery = Training::where('type', $trainingType);
            if (!empty($year)) {
                $totalProgramsQuery->whereYear('start_date', $year);
            }
            if ($trainingId) {
                $totalProgramsQuery->where('id', $trainingId);
            }
            $totalPrograms = $totalProgramsQuery->count();

            $totalParticipantsQuery = TrainingParticipant::whereHas('training', function ($query) use ($trainingType, $year, $trainingId) {
                $query->where('type', $trainingType);
                if (!empty($year)) {
                    $query->whereYear('start_date', $year);
                }
                if ($trainingId) {
                    $query->where('id', $trainingId);
                }
            });
            $totalParticipants = $totalParticipantsQuery->distinct('user_id')->count();

            // Total facilities for mentorship
            if ($mode === 'training') {
                $totalFacilitiesQuery = Facility::whereHas('users.trainingParticipations.training', function ($query) use ($year, $trainingId) {
                    $query->where('type', 'global_training');
                    if (!empty($year)) {
                        $query->whereYear('start_date', $year);
                    }
                    if ($trainingId) {
                        $query->where('id', $trainingId);
                    }
                });
            } else {
                $totalFacilitiesQuery = Facility::whereHas('trainings', function ($query) use ($year, $trainingId) {
                    $query->where('type', 'facility_mentorship');
                    if (!empty($year)) {
                        $query->whereYear('start_date', $year);
                    }
                    if ($trainingId) {
                        $query->where('id', $trainingId);
                    }
                });
            }
            $totalFacilities = $totalFacilitiesQuery->count();

            // Calculate facility coverage
            $allFacilities = Facility::count();
            $facilityCoverage = $allFacilities > 0 ? round(($totalFacilities / $allFacilities) * 100, 1) : 0;

            return [
                'totalPrograms' => $totalPrograms,
                'totalParticipants' => $totalParticipants,
                'totalFacilities' => $totalFacilities,
                'facilityCoverage' => $facilityCoverage
            ];
        } catch (\Exception $e) {
            return [
                'totalPrograms' => 0,
                'totalParticipants' => 0,
                'totalFacilities' => 0,
                'facilityCoverage' => 0
            ];
        }
    }

    // Updated county participants method
    private function getCountyParticipants($countyId, $year, $mode, $trainingId = null) {
        if ($mode === 'training') {
            // For training: participants from facilities in this county
            $query = TrainingParticipant::whereHas('user.facility.subcounty', function ($query) use ($countyId) {
                        $query->where('county_id', $countyId);
                    })->whereHas('training', function ($query) use ($year, $trainingId) {
                $query->where('type', 'global_training');
                if (!empty($year)) {
                    $query->whereYear('start_date', $year);
                }
                if ($trainingId) {
                    $query->where('id', $trainingId);
                }
            });
        } else {
            // For mentorship: participants in mentorship programs hosted by facilities in this county
            $query = TrainingParticipant::whereHas('training', function ($query) use ($countyId, $year, $trainingId) {
                $query->where('type', 'facility_mentorship')
                    ->whereHas('facility.subcounty', function ($q) use ($countyId) {
                        $q->where('county_id', $countyId);
                    });
                if (!empty($year)) {
                    $query->whereYear('start_date', $year);
                }
                if ($trainingId) {
                    $query->where('id', $trainingId);
                }
            });
        }

        return $query->distinct('user_id')->count();
    }

    // Updated county programs method
    private function getCountyPrograms($countyId, $year, $mode, $trainingId = null) {
        $trainingType = $mode === 'training' ? 'global_training' : 'facility_mentorship';

        $query = Training::where('type', $trainingType);

        if (!empty($year)) {
            $query->whereYear('start_date', $year);
        }

        if ($trainingId) {
            $query->where('id', $trainingId);
        }

        if ($mode === 'training') {
            // For training: programs that have participants from facilities in this county
            $query->whereHas('participants.user.facility.subcounty', function ($q) use ($countyId) {
                $q->where('county_id', $countyId);
            });
        } else {
            // For mentorship: programs that are based in facilities in this county
            $query->whereHas('facility.subcounty', function ($q) use ($countyId) {
                $q->where('county_id', $countyId);
            });
        }

        return $query->count();
    }

    private function getTotalTargetFacilities($training) {
        if ($training->type === 'facility_mentorship') {
            return 1; // Only one facility for mentorship
        }

        // For global training, count facilities in participating counties
        $counties = $this->getTrainingCounties($training->id);
        return Facility::whereHas('subcounty', function ($query) use ($counties) {
                    $query->whereIn('county_id', $counties->pluck('id'));
                })->count();
    }

    private function getTrainingCounties($trainingId) {
        $training = Training::find($trainingId);
        
        if ($training && $training->type === 'facility_mentorship') {
            // For mentorship, get the county of the facility hosting the mentorship
            return County::whereHas('facilities.trainings', function ($query) use ($trainingId) {
                $query->where('id', $trainingId);
            })->get();
        } else {
            // For training, get counties with participants
            return County::whereHas('facilities.users.trainingParticipations', function ($query) use ($trainingId) {
                        $query->where('training_id', $trainingId);
                    })->get();
        }
    }

    private function getTrainingsForCounty($countyId, $year) {
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

    private function getMentorshipsForCounty($countyId, $year) {
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

    private function getCoverageData($countyId, $year, $mode) {
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
            $dept->coverage_percentage = $dept->county_users > 0 ? round(($dept->trained_users / $dept->county_users) * 100, 1) : 0;
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
            $cadre->coverage_percentage = $cadre->county_users > 0 ? round(($cadre->trained_users / $cadre->county_users) * 100, 1) : 0;
            return $cadre;
        });

        // Coverage by Facility Type for mentorship
        if ($mode === 'training') {
            $facilityTypeCoverage = FacilityType::withCount([
                        'facilities as county_facilities' => function ($query) use ($countyId) {
                            $query->whereHas('subcounty', function ($q) use ($countyId) {
                                $q->where('county_id', $countyId);
                            });
                        },
                        'facilities as facilities_with_training' => function ($query) use ($countyId, $year) {
                            $query->whereHas('subcounty', function ($q) use ($countyId) {
                                $q->where('county_id', $countyId);
                            })->whereHas('users.trainingParticipations.training', function ($q) use ($year) {
                                $q->where('type', 'global_training');
                                if (!empty($year)) {
                                    $q->whereYear('start_date', $year);
                                }
                            });
                        }
                    ])->get()->map(function ($type) {
                $type->coverage_percentage = $type->county_facilities > 0 ? 
                    round(($type->facilities_with_training / $type->county_facilities) * 100, 1) : 0;
                return $type;
            });
        } else {
            // For mentorship mode
            $facilityTypeCoverage = FacilityType::withCount([
                        'facilities as county_facilities' => function ($query) use ($countyId) {
                            $query->whereHas('subcounty', function ($q) use ($countyId) {
                                $q->where('county_id', $countyId);
                            });
                        },
                        'facilities as facilities_with_training' => function ($query) use ($countyId, $year) {
                           $query->whereHas('subcounty', function ($q) use ($countyId) {
                                $q->where('county_id', $countyId);
                            })->whereHas('trainings', function ($q) use ($year) {
                                $q->where('type', 'facility_mentorship');
                                if (!empty($year)) {
                                    $q->whereYear('start_date', $year);
                                }
                            });
                        }
                    ])->get()->map(function ($type) {
                $type->coverage_percentage = $type->county_facilities > 0 ? 
                    round(($type->facilities_with_training / $type->county_facilities) * 100, 1) : 0;
                return $type;
            });
        }

        return compact('departmentCoverage', 'cadreCoverage', 'facilityTypeCoverage');
    }

    private function getFacilitiesForProgram($countyId, $programId, $mode) {
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

    private function getProgramStats($countyId, $programId, $mode) {
        if ($mode === 'training') {
            $totalParticipants = TrainingParticipant::where('training_id', $programId)
                            ->whereHas('user.facility.subcounty', function ($query) use ($countyId) {
                                $query->where('county_id', $countyId);
                            })->count();
        } else {
            // For mentorship, all participants are typically in the same facility/county
            $totalParticipants = TrainingParticipant::where('training_id', $programId)->count();
        }

        return compact('totalParticipants');
    }

    private function getParticipantsForFacility($programId, $facilityId) {
        return TrainingParticipant::where('training_id', $programId)
                        ->whereHas('user', function ($query) use ($facilityId) {
                            $query->where('facility_id', $facilityId);
                        })
                        ->with(['user.department', 'user.cadre', 'assessmentResults.assessmentCategory'])
                        ->get();
    }

    private function getParticipantsForProgram($programId) {
        return TrainingParticipant::where('training_id', $programId)
                        ->with(['user.facility', 'user.department', 'user.cadre', 'assessmentResults.assessmentCategory'])
                        ->get();
    }

    private function getFacilityStats($programId, $facilityId) {
        $participants = $this->getParticipantsForFacility($programId, $facilityId);

        $departmentStats = $participants->groupBy('user.department.name')->map(function ($group, $dept) {
            return [
                'department' => $dept,
                'count' => $group->count()
            ];
        });

        $cadreStats = $participants->groupBy('user.cadre.name')->map(function ($group, $cadre) {
            return [
                'cadre' => $cadre,
                'count' => $group->count()
            ];
        });

        return compact('departmentStats', 'cadreStats');
    }
}