<?php

use Illuminate\Support\Facades\Route;
use League\Csv\Writer;
use App\Http\Controllers\Frontend\ResourceController;
use App\Http\Controllers\Frontend\CategoryController;
use App\Http\Controllers\Frontend\SearchController;
use App\Http\Controllers\Frontend\CommentController;
use App\Http\Controllers\Frontend\InteractionController;
use App\Http\Controllers\TrainingHeatmapController;
use App\Http\Controllers\TrainingPagesController;

use App\Http\Controllers\Analytics\KenyaHeatmapController;
use App\Http\Controllers\Analytics\TrainingExplorerController;

/*
  |--------------------------------------------------------------------------
  | Web Routes - Complete Resource Management System
  |--------------------------------------------------------------------------
 */




// Alternative: If you want to handle it within Filament's context
Route::middleware(['auth', 'web'])->group(function () {
    Route::get('/training-export/download/{export_id}', [\App\Http\Controllers\TrainingExportController::class, 'download'])
        ->name('training-export.download');
});

// Training participant template download
Route::get('/{training}/participants/template', function ($trainingId) {
    $csv = Writer::createFromString('');

    // Add headers
    $csv->insertOne([
        'first_name',
        'last_name',
        'phone',
        'email',
        'facility_name',
        'facility_mfl_code',
        'department_name',
        'cadre_name'
    ]);

    // Add sample data
    $csv->insertOne([
        'John',
        'Doe',
        '+254700123456',
        'john.doe@example.com',
        'Kenyatta National Hospital',
        'KNH001',
        'Nursing',
        'Registered Nurse'
    ]);

    $csv->insertOne([
        'Jane',
        'Smith',
        '+254711234567',
        'jane.smith@example.com',
        'Moi Teaching Hospital',
        'MTH002',
        'Laboratory',
        'Lab Technician'
    ]);

    $filename = 'participants_import_template.csv';

    return response($csv->toString(), 200, [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
    ]);
})->name('participants.template');

Route::get('/', [ResourceController::class, 'home'])->name('home');

// ===== RESOURCE CENTER ROUTES =====
Route::prefix('resources')->name('resources.')->group(function () {

    // === MAIN RESOURCE ROUTES ===
    Route::get('/', [ResourceController::class, 'index'])->name('index');
    Route::get('/search', [SearchController::class, 'index'])
            ->name('search')
            ->middleware('throttle:search');
    Route::get('/browse', [ResourceController::class, 'browse'])->name('browse');

    // === USER-SPECIFIC ROUTES (AUTHENTICATED) ===
    Route::middleware('auth')->group(function () {
        Route::get('/bookmarks', [ResourceController::class, 'bookmarks'])->name('bookmarks');
        Route::get('/downloads', [ResourceController::class, 'downloads'])->name('downloads');
    });

    // === FILTER & BROWSE ROUTES ===
    Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('category');
    Route::get('/type/{type:slug}', [ResourceController::class, 'byType'])->name('type');
    Route::get('/tag/{tag:slug}', [ResourceController::class, 'byTag'])->name('tag');

    // === INDIVIDUAL RESOURCE ROUTES ===
    Route::get('/{resource:slug}', [ResourceController::class, 'show'])->name('show');

    // === FILE HANDLING ROUTES ===
    Route::get('/{resource:slug}/download', [ResourceController::class, 'download'])
            ->name('download');
    //->middleware('throttle:downloads');

    Route::get('/{resource:slug}/preview', [ResourceController::class, 'preview'])
            ->name('preview');
    //->middleware('throttle:previews');

    Route::get('/{resource:slug}/thumbnail', [ResourceController::class, 'thumbnail'])
            ->name('thumbnail');

    // === RESOURCE INTERACTIONS (AUTHENTICATED USERS) ===
    Route::middleware(['auth', 'throttle:interactions'])->group(function () {
        Route::post('/{resource:slug}/like', [InteractionController::class, 'like'])->name('like');
        Route::post('/{resource:slug}/dislike', [InteractionController::class, 'dislike'])->name('dislike');
        Route::post('/{resource:slug}/bookmark', [InteractionController::class, 'bookmark'])->name('bookmark');
    });

    // === COMMENT ROUTES ===
    Route::middleware('auth')->group(function () {
        Route::post('/{resource:slug}/comment', [CommentController::class, 'store'])
                ->name('comment.store')
                ->middleware('throttle:comments');

        Route::put('/comment/{comment}', [CommentController::class, 'update'])
                ->name('comment.update')
                ->middleware('throttle:comment-updates');

        Route::delete('/comment/{comment}', [CommentController::class, 'destroy'])
                ->name('comment.destroy');
    });

    // Public comment routes for guests
    Route::post('/{resource:slug}/comment/guest', [CommentController::class, 'storeGuest'])
            ->name('comment.guest')
            ->middleware('throttle:guest-comments');
});

// ===== CATEGORY ROUTES =====
Route::prefix('categories')->name('categories.')->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->name('index');
    Route::get('/{category:slug}', [CategoryController::class, 'show'])->name('show');
});

// ===== API ROUTES FOR AJAX FUNCTIONALITY =====
Route::prefix('api/v1')->name('api.')->middleware('throttle:api')->group(function () {

    // === AUTHENTICATED API ENDPOINTS ===
    Route::middleware('auth')->group(function () {
        Route::post('/resources/{resource:slug}/interactions/{type}', [InteractionController::class, 'toggle'])
                ->name('interactions.toggle')
                ->where('type', 'like|dislike|bookmark|share');

        Route::get('/resources/{resource:slug}/stats', function (\App\Models\Resource $resource) {
            if (!$resource->canUserAccess(auth()->user())) {
                abort(403);
            }

            return response()->json([
                        'views' => $resource->view_count,
                        'downloads' => $resource->download_count,
                        'likes' => $resource->like_count,
                        'comments' => $resource->comments()->approved()->count(),
            ]);
        })->name('resource.stats');
    });
});

// ===== UTILITY & SEO ROUTES =====
Route::get('/health', function () {
    return cache()->remember('health_check', 60, function () {
                return response()->json([
                            'status' => 'ok',
                            'timestamp' => now(),
                            'resources_count' => \App\Models\Resource::published()->count(),
                            'categories_count' => \App\Models\ResourceCategory::active()->count(),
                ]);
            });
})->name('health');

Route::get('/sitemap.xml', function () {
    return cache()->remember('sitemap', 3600, function () {
                $resources = \App\Models\Resource::published()
                        ->public()
                        ->select(['slug', 'updated_at'])
                        ->get();

                $categories = \App\Models\ResourceCategory::active()
                        ->select(['slug', 'updated_at'])
                        ->get();

                return response()->view('sitemap', compact('resources', 'categories'))
                                ->header('Content-Type', 'text/xml');
            });
})->name('sitemap');

Route::get('/feed', function () {
    return cache()->remember('rss_feed', 1800, function () {
                $resources = \App\Models\Resource::published()
                        ->public()
                        ->latest('published_at')
                        ->limit(20)
                        ->with(['author', 'category'])
                        ->get();

                return response()->view('feed.rss', compact('resources'))
                                ->header('Content-Type', 'application/rss+xml');
            });
})->name('feed');

Route::get('/robots.txt', function () {
    $content = "User-agent: *\n";
    $content .= "Allow: /\n";
    $content .= "Sitemap: " . route('sitemap') . "\n";

    return response($content, 200)
                    ->header('Content-Type', 'text/plain');
})->name('robots');

// ===== ADMIN ROUTES =====
Route::middleware(['auth', 'throttle:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/stats', function () {
        $stats = cache()->remember('admin_stats', 300, function () {
            return [
                'resources' => [
                    'total' => \App\Models\Resource::count(),
                    'published' => \App\Models\Resource::published()->count(),
                    'draft' => \App\Models\Resource::where('status', 'draft')->count(),
                ],
                'activity' => [
                    'views_today' => \App\Models\ResourceView::whereDate('created_at', today())->count(),
                    'downloads_today' => \App\Models\ResourceDownload::whereDate('created_at', today())->count(),
                ]
            ];
        });

        return response()->json($stats);
    })->name('stats');
});

// ===== REDIRECTS & FALLBACKS =====
Route::redirect('/admin', '/admin/login')->name('admin');
Route::redirect('/resource/{slug}', '/resources/{slug}', 301);
Route::redirect('/category/{slug}', '/resources/category/{slug}', 301); 

Route::middleware(['web'])->prefix('analytics')->name('analytics.')->group(function () {
    // Heatmap (controller-based replacement for the widget usage)
    Route::get('/heatmap', [KenyaHeatmapController::class, 'index'])->name('heatmap');
    Route::get('/heatmap/data', [KenyaHeatmapController::class, 'data'])->name('heatmap.data');
    Route::get('/geo/kenyan-counties.geojson', [KenyaHeatmapController::class, 'geojson'])->name('heatmap.geojson');

    // Explorer (County → Trainings → Facility → Participants → Profile)
    Route::get('/training-explorer', [TrainingExplorerController::class, 'index'])->name('training-explorer');

    // JSON APIs used by Explorer
    Route::get('/counties', [TrainingExplorerController::class, 'apiCounties'])->name('counties');
    Route::get('/{county}/trainings', [TrainingExplorerController::class, 'apiCountyTrainings'])->name('county.trainings');
    Route::get('/{county}/trainings/{training}/facilities', [TrainingExplorerController::class, 'apiTrainingFacilities'])->name('training.facilities');
    Route::get('/trainings/{training}/facilities/{facility}/participants', [TrainingExplorerController::class, 'apiFacilityParticipants'])->name('facility.participants');
    Route::get('/participants/{user}', [TrainingExplorerController::class, 'apiParticipantProfile'])->name('participant.profile');
    
       Route::get('/participants/{user}', [TrainingExplorerController::class, 'apiParticipantProfile'])->name('participant.profile');
    Route::get('/participants/{user}/attrition-logs', [TrainingExplorerController::class, 'apiAttritionLogs'])->name('participant.attrition.logs');
    Route::post('/participants/{user}/attrition-logs', [TrainingExplorerController::class, 'storeAttritionLog'])->name('participant.attrition.store');
}); 
 





