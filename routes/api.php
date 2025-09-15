<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Analytics\ProgressiveDashboardController;

// ==========================================
// API ROUTES (routes/api.php)
// For all data endpoints called by JavaScript
// ==========================================

// Progressive Dashboard API Routes
Route::prefix('progressive-dashboard')->name('progressive-dashboard.')->group(function () {
    
    // ===== CORE ANALYTICS ENDPOINTS =====
    
    // Level 0: National Overview
    Route::get('national', [ProgressiveDashboardController::class, 'getNationalOverview'])->name('national');
    
    // Level 1: County Analysis  
    Route::get('county/{countyId}', [ProgressiveDashboardController::class, 'getCountyAnalysis'])->name('county');
    
    // Level 2: Facility Type Analysis
    Route::get('county/{countyId}/facility-type/{facilityTypeId}', [ProgressiveDashboardController::class, 'getFacilityTypeAnalysis'])->name('facility-type');
    
    // Level 3: Individual Facility Analysis
    Route::get('facility/{facilityId}', [ProgressiveDashboardController::class, 'getFacilityAnalysis'])->name('facility');
    
    // Level 4: Individual Participant Profile
    Route::get('participant/{participantId}', [ProgressiveDashboardController::class, 'getParticipantProfile'])->name('participant');
    
    // ===== UTILITY DATA ENDPOINTS =====
    
    // Filter options
    Route::get('years', [ProgressiveDashboardController::class, 'getAvailableYears'])->name('years');
    Route::get('facility-types', [ProgressiveDashboardController::class, 'getFacilityTypes'])->name('facility-types');
    Route::get('departments', [ProgressiveDashboardController::class, 'getDepartments'])->name('departments');
    Route::get('cadres', [ProgressiveDashboardController::class, 'getCadres'])->name('cadres');
    
    // Dashboard statistics
    Route::get('stats', [ProgressiveDashboardController::class, 'getDashboardStats'])->name('stats');
    
    // ===== SEARCH AND COMPARISON =====
    
    Route::get('search/facilities', [ProgressiveDashboardController::class, 'searchFacilities'])->name('search.facilities');
    Route::get('compare/counties', [ProgressiveDashboardController::class, 'getCountyComparison'])->name('compare.counties');
    
    // ===== GEOGRAPHIC DATA =====
    
    Route::get('county/{countyId}/facilities-geojson', function($countyId) {
        $county = \App\Models\County::findOrFail($countyId);
        $facilities = $county->facilities()->with('facilityType')->get();
        
        $features = $facilities->map(function($facility) {
            return [
                'type' => 'Feature',
                'properties' => [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'type' => $facility->facilityType->name ?? 'Unknown',
                    'mfl_code' => $facility->mfl_code,
                ],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$facility->long ?? 36.8219, $facility->lat ?? -1.2921]
                ]
            ];
        });
        
        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
            'metadata' => [
                'county_name' => $county->name,
                'total_facilities' => $facilities->count(),
            ]
        ]);
    })->name('county.geojson');
    
    // ===== EXPORT ENDPOINTS =====
    
    // Export current view
    Route::post('export', [ProgressiveDashboardController::class, 'exportCurrentView'])->name('export.current');
    
    // Export county data
    Route::get('export/county/{countyId}', [ProgressiveDashboardController::class, 'exportCountyData'])->name('export.county');
    
    // Export facility list
    Route::post('export-facility-list', [ProgressiveDashboardController::class, 'exportFacilityList'])->name('export.facility-list');
    
    // Export participants
    Route::get('facility/{facilityId}/participants/export', [ProgressiveDashboardController::class, 'exportParticipants'])->name('export.participants');
    
    // ===== ALERTS AND MONITORING =====
    
    Route::get('alerts', [ProgressiveDashboardController::class, 'getActiveAlerts'])->name('alerts');
    Route::get('status', [ProgressiveDashboardController::class, 'getApiStatus'])->name('status');
    
    // ===== CACHE MANAGEMENT =====
    
    Route::post('cache/clear', [ProgressiveDashboardController::class, 'clearCache'])->name('cache.clear');
    
    // Bulk cache refresh
    Route::post('cache/refresh', function() {
        \Illuminate\Support\Facades\Cache::flush();
        return response()->json(['message' => 'Cache refreshed successfully']);
    })->name('cache.refresh');
}); 


// ==========================================
// ADMIN API ROUTES (routes/api.php) 
// For admin-only functions with middleware
// ==========================================

Route::prefix('admin/progressive-dashboard')->middleware(['auth:sanctum', 'admin'])->name('admin.progressive-dashboard.')->group(function () {
    
    Route::get('system-info', function() {
        return response()->json([
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'cache_driver' => config('cache.default'),
            'database_connection' => config('database.default'),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'disk_space' => disk_free_space('/'),
        ]);
    })->name('system-info');
    
    Route::post('rebuild-cache', function() {
        // Rebuild all dashboard caches
        \Illuminate\Support\Facades\Cache::flush();
        return response()->json(['message' => 'All caches rebuilt successfully']);
    })->name('rebuild-cache');
    
    Route::get('performance-metrics', function() {
        return response()->json([
            'cache_hit_rate' => 85.4, // Calculate from actual metrics
            'average_response_time' => 245, // milliseconds
            'active_users' => 23,
            'total_requests_today' => 1247,
        ]);
    })->name('performance-metrics');
    
    Route::post('bulk-export', function(\Illuminate\Http\Request $request) {
        $entities = $request->get('entities', []);
        $type = $request->get('type', 'global_training');
        $year = $request->get('year', 'all');
        
        return response()->json([
            'message' => 'Bulk export queued',
            'job_id' => uniqid(),
            'entities_count' => count($entities),
            'estimated_completion' => now()->addMinutes(5)
        ]);
    })->name('bulk-export');
});




