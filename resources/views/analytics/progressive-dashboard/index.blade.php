{{-- resources/views/analytics/progressive-dashboard/index.blade.php --}}

@extends('layouts.app')

@section('title', 'Progressive Training Analytics Dashboard')

@push('styles')
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .county-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .county-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.15);
        }
        .coverage-high { background: linear-gradient(135deg, #10B981, #059669); }
        .coverage-medium { background: linear-gradient(135deg, #F59E0B, #D97706); }
        .coverage-low { background: linear-gradient(135deg, #EF4444, #DC2626); }
        .coverage-none { background: linear-gradient(135deg, #6B7280, #4B5563); }

        .slide-in { animation: slideIn 0.5s ease-out; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .drill-path { background: linear-gradient(90deg, #3B82F6, #1D4ED8); }
        .metric-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .loading-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }

        .facility-map { height: 400px; border-radius: 0.5rem; }
        .participant-avatar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    </style>
@endpush

@section('content')
    <div id="progressive-dashboard-app">
        <!-- Header with breadcrumb -->
        <header class="bg-white shadow-lg border-b-2 border-blue-500">
            <div class="max-w-7xl mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <nav class="text-sm mb-2" id="breadcrumb">
                            <span class="text-blue-600 cursor-pointer" onclick="dashboard.goToLevel('national')">National Overview</span>
                        </nav>
                        <h1 class="text-3xl font-bold text-gray-800" id="page-title">Healthcare Training Analytics</h1>
                        <p class="text-gray-600 mt-1" id="page-subtitle">Progressive drill-down analytics for training coverage</p>
                    </div>

                    <!-- Controls -->
                    <div class="flex items-center space-x-4">
                        <!-- Training Type Toggle -->
                        <div class="bg-gray-100 p-1 rounded-lg">
                            <button id="global-training-btn" class="px-4 py-2 rounded-md text-sm font-medium transition-colors bg-blue-600 text-white">
                                Global Training
                            </button>
                            <button id="mentorship-btn" class="px-4 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-gray-900">
                                Mentorship
                            </button>
                        </div>

                        <!-- Year Filter -->
                        <select id="year-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                            <option value="all">All Years</option>
                        </select>

                        <!-- Export Button -->
                        <button id="export-current-view" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Dashboard Container -->
        <div class="max-w-7xl mx-auto px-6 py-6">

            <!-- Level 0: National Overview -->
            <div id="national-level" class="dashboard-level">
                <!-- Key Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                    <div class="metric-card rounded-xl p-6 text-white">
                        <div class="text-2xl font-bold" id="national-counties">--</div>
                        <div class="text-sm opacity-90">Counties Covered</div>
                    </div>
                    <div class="metric-card rounded-xl p-6 text-white">
                        <div class="text-2xl font-bold" id="national-facilities">--</div>
                        <div class="text-sm opacity-90">Facilities Reached</div>
                    </div>
                    <div class="metric-card rounded-xl p-6 text-white">
                        <div class="text-2xl font-bold" id="national-participants">--</div>
                        <div class="text-sm opacity-90" id="participants-label">Participants</div>
                    </div>
                    <div class="metric-card rounded-xl p-6 text-white">
                        <div class="text-2xl font-bold" id="national-coverage">--</div>
                        <div class="text-sm opacity-90">Average Coverage</div>
                    </div>
                    <div class="metric-card rounded-xl p-6 text-white">
                        <div class="text-2xl font-bold" id="national-programs">--</div>
                        <div class="text-sm opacity-90" id="programs-label">Programs</div>
                    </div>
                </div>

                <!-- Counties Grid with Search -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Counties Overview</h2>
                        <div class="flex items-center space-x-4">
                            <!-- Search Counties -->
                            <input type="text" id="county-search" placeholder="Search counties..." 
                                   class="px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">

                            <!-- Coverage Legend -->
                            <div class="flex items-center space-x-4 text-sm">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 coverage-high rounded mr-2"></div>
                                    <span>80-100%</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 coverage-medium rounded mr-2"></div>
                                    <span>40-79%</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 coverage-low rounded mr-2"></div>
                                    <span>1-39%</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 coverage-none rounded mr-2"></div>
                                    <span>0%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="counties-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <!-- Counties populated by JavaScript -->
                    </div>
                </div>

                <!-- National Insights -->
                <div class="mt-8 bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Strategic Insights</h3>
                    <div id="national-insights" class="space-y-3">
                        <div class="flex justify-center py-8">
                            <div class="loading-pulse w-8 h-8 bg-blue-600 rounded-full"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Level 1: County Analysis -->
            <div id="county-level" class="dashboard-level hidden">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Main Analysis -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- County Summary -->
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-gray-800" id="county-title">County Analysis</h3>
                                <button onclick="dashboard.showCountyMap()" class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm">
                                    View on Map
                                </button>
                            </div>
                            <div class="grid grid-cols-3 gap-4 mb-6">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-blue-600" id="county-total-facilities">--</div>
                                    <div class="text-sm text-gray-600">Total Facilities</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-green-600" id="county-covered-facilities">--</div>
                                    <div class="text-sm text-gray-600">Covered</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-red-600" id="county-uncovered-facilities">--</div>
                                    <div class="text-sm text-gray-600">Uncovered</div>
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div id="county-coverage-bar" class="bg-gradient-to-r from-green-400 to-green-600 h-3 rounded-full transition-all duration-500" style="width: 0%"></div>
                            </div>
                            <div class="text-center mt-2">
                                <span id="county-coverage-percentage" class="text-lg font-bold text-gray-800">0%</span>
                                <span class="text-gray-600"> coverage</span>
                            </div>
                        </div>

                        <!-- Facility Types Breakdown -->
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h4 class="text-lg font-bold text-gray-800 mb-4">Coverage by Facility Type</h4>
                            <div id="facility-types-chart" class="h-64 mb-4">
                                <canvas id="facility-types-canvas"></canvas>
                            </div>
                            <div id="facility-types-list" class="space-y-2">
                                <!-- Facility types list populated by JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Quick Stats -->
                        <div class="bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                            <h4 class="text-lg font-bold mb-4">Quick Stats</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span>Participants:</span>
                                    <span id="county-participants" class="font-bold">--</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Programs:</span>
                                    <span id="county-programs" class="font-bold">--</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Departments:</span>
                                    <span id="county-departments-count" class="font-bold">--</span>
                                </div>
                            </div>
                        </div>

                        <!-- Department Analysis -->
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h4 class="text-lg font-bold text-gray-800 mb-4">Department Coverage</h4>
                            <div id="departments-list" class="space-y-2 max-h-64 overflow-y-auto">
                                <!-- Departments populated by JavaScript -->
                            </div>
                        </div>

                        <!-- Recommended Actions -->
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h4 class="text-lg font-bold text-gray-800 mb-4">Priority Actions</h4>
                            <div id="recommended-actions" class="space-y-3">
                                <!-- Actions populated by JavaScript -->
                            </div>
                        </div>

                        <!-- County Insights -->
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h4 class="text-lg font-bold text-gray-800 mb-4">Key Insights</h4>
                            <div id="county-insights" class="space-y-3">
                                <!-- Insights populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Level 2: Facility Type Analysis -->
            <div id="facility-type-level" class="dashboard-level hidden">
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4" id="facility-type-title">Facility Type Analysis</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600" id="type-total-facilities">--</div>
                            <div class="text-sm text-gray-600">Total Facilities</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600" id="type-covered-facilities">--</div>
                            <div class="text-sm text-gray-600">Covered</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600" id="type-total-participants">--</div>
                            <div class="text-sm text-gray-600" id="type-participants-label">Participants</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-orange-600" id="type-coverage-percentage">--</div>
                            <div class="text-sm text-gray-600">Coverage</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Facility Map -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-bold text-gray-800">Facility Locations</h4>
                            <div class="flex space-x-2">
                                <button onclick="dashboard.toggleCoveredOnly()" id="toggle-covered" 
                                        class="px-3 py-1 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition-colors">
                                    Show All
                                </button>
                            </div>
                        </div>
                        <div id="facility-type-map" class="facility-map bg-gray-100 rounded-lg">
                            <!-- Map with facility markers -->
                        </div>
                    </div>

                    <!-- Performance Metrics -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h4 class="text-lg font-bold text-gray-800 mb-4">Performance Distribution</h4>
                        <div id="performance-chart" class="h-64 mb-4">
                            <canvas id="performance-canvas"></canvas>
                        </div>
                        <div id="performance-insights" class="space-y-2">
                            <!-- Performance insights -->
                        </div>
                    </div>
                </div>

                <!-- Facility List with Filters -->
                <div class="mt-8 bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-lg font-bold text-gray-800">Individual Facilities</h4>
                        <div class="flex space-x-2">
                            <select id="facility-filter" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                                <option value="all">All Facilities</option>
                                <option value="covered">Covered Only</option>
                                <option value="uncovered">Uncovered Only</option>
                            </select>
                            <button onclick="dashboard.exportFacilityList()" 
                                    class="px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm">
                                Export List
                            </button>
                        </div>
                    </div>
                    <div id="facilities-list" class="space-y-3">
                        <!-- Individual facilities populated by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Level 3: Individual Facility -->
            <div id="facility-level" class="dashboard-level hidden">
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-bold text-gray-800" id="facility-title">Facility Analysis</h3>
                        <div class="flex space-x-2">
                            <button id="facility-details-btn" class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm">
                                View Details
                            </button>
                            <button id="contact-facility-btn" class="px-3 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors text-sm">
                                Contact Info
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600" id="facility-participants">--</div>
                            <div class="text-sm text-gray-600" id="facility-participants-label">Participants</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600" id="facility-programs">--</div>
                            <div class="text-sm text-gray-600">Programs</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600" id="facility-departments">--</div>
                            <div class="text-sm text-gray-600">Departments</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-orange-600" id="facility-completion">--</div>
                            <div class="text-sm text-gray-600">Completion Rate</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Department Breakdown -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h4 class="text-lg font-bold text-gray-800 mb-4">Department Participation</h4>
                        <div id="facility-departments-chart" class="h-64">
                            <canvas id="facility-departments-canvas"></canvas>
                        </div>
                    </div>

                    <!-- Cadre Analysis -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h4 class="text-lg font-bold text-gray-800 mb-4">Professional Cadres</h4>
                        <div id="facility-cadres-chart" class="h-64">
                            <canvas id="facility-cadres-canvas"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Training History -->
                <div class="mt-8 bg-white rounded-xl shadow-lg p-6">
                    <h4 class="text-lg font-bold text-gray-800 mb-4">Training Program History</h4>
                    <div id="training-history" class="space-y-3">
                        <!-- Training history timeline -->
                    </div>
                </div>

                <!-- Participants List with Search and Filters -->
                <div class="mt-8 bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-lg font-bold text-gray-800">Participants</h4>
                        <div class="flex space-x-2">
                            <input type="text" id="participant-search" placeholder="Search participants..." 
                                   class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                            <select id="participant-filter" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                                <option value="all">All Participants</option>
                                <option value="completed">Completed</option>
                                <option value="in-progress">In Progress</option>
                                <option value="dropped">Dropped</option>
                            </select>
                            <button id="export-participants" class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm">
                                Export List
                            </button>
                        </div>
                    </div>
                    <div id="participants-table" class="overflow-x-auto">
                        <!-- Participants table populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="loading-overlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
                <div class="loading-pulse w-6 h-6 bg-blue-600 rounded-full"></div>
                <span class="text-gray-800">Loading analytics...</span>
            </div>
        </div>

        <!-- Modals -->
        <div id="facility-details-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-800">Facility Details</h3>
                    <button onclick="dashboard.closeFacilityDetails()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div id="facility-details-content">
                    <!-- Facility details content -->
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/progressive-dashboard.js') }}"></script>
@endpush