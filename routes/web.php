<?php

use Illuminate\Support\Facades\Route;
use League\Csv\Writer;
use App\Http\Controllers\Frontend\ResourceController;
use App\Http\Controllers\Frontend\CategoryController;
use App\Http\Controllers\Frontend\SearchController;
use App\Http\Controllers\Frontend\CommentController;
use App\Http\Controllers\Frontend\InteractionController;

// Training specific routes (keep existing)
Route::get('/training-heatmap', function () {
    $widget = new \App\Filament\Widgets\KenyaTrainingHeatmapWidget();
    return view('dashboard', ['widget' => $widget]);
})->name('training.heatmap');

Route::get('/training/{training}/participants/template', function ($trainingId) {
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
})->name('training.participants.template');

// Homepage
Route::get('/', [ResourceController::class, 'home'])->name('home');

// Resource Center Routes with improved organization
Route::prefix('resources')->name('resources.')->group(function () {
    // Main resource routes
    Route::get('/', [ResourceController::class, 'index'])->name('index');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/browse', [ResourceController::class, 'browse'])->name('browse');

    // Filter routes with consistent naming
    Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('category');
    Route::get('/type/{type:slug}', [ResourceController::class, 'byType'])->name('type');
    Route::get('/tag/{tag:slug}', [ResourceController::class, 'byTag'])->name('tag');

    // Individual resource routes
    Route::get('/{resource:slug}', [ResourceController::class, 'show'])->name('show');

    // Download route with middleware
    Route::get('/{resource:slug}/download', [ResourceController::class, 'download'])
        ->name('download')
        ->middleware(['auth', 'throttle:10,1']); // Rate limit downloads

    // Resource interactions (require auth with rate limiting)
    Route::middleware(['auth', 'throttle:30,1'])->group(function () {
        Route::post('/{resource:slug}/like', [InteractionController::class, 'like'])->name('like');
        Route::post('/{resource:slug}/dislike', [InteractionController::class, 'dislike'])->name('dislike');
        Route::post('/{resource:slug}/bookmark', [InteractionController::class, 'bookmark'])->name('bookmark');
    });

    // Comment routes with proper middleware
    Route::middleware('auth')->group(function () {
        Route::post('/{resource:slug}/comment', [CommentController::class, 'store'])
            ->name('comment.store')
            ->middleware('throttle:10,1');

        Route::put('/comment/{comment}', [CommentController::class, 'update'])
            ->name('comment.update')
            ->middleware('throttle:5,1');

        Route::delete('/comment/{comment}', [CommentController::class, 'destroy'])
            ->name('comment.destroy');
    });

    // Public comment routes (for guests) with heavy rate limiting
    Route::post('/{resource:slug}/comment/guest', [CommentController::class, 'storeGuest'])
        ->name('comment.guest')
        ->middleware('throttle:3,1'); // Stricter rate limit for guests
});

// Category Routes
Route::prefix('categories')->name('categories.')->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->name('index');
    Route::get('/{category:slug}', [CategoryController::class, 'show'])->name('show');
});

// API Routes for AJAX functionality
Route::prefix('api/v1')->name('api.')->middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::post('/resources/{resource:slug}/interactions/{type}', [InteractionController::class, 'toggle'])
        ->name('interactions.toggle')
        ->where('type', 'like|dislike|bookmark|share');

    // Add other API endpoints as needed
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

// Admin redirect (if not using subdomain)
Route::redirect('/admin', '/admin/login')->name('admin');

// Health check route with caching
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

// Sitemap route for SEO
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

// RSS Feed route (optional)
Route::get('/feed', function () {
    $resources = \App\Models\Resource::published()
        ->public()
        ->latest('published_at')
        ->limit(20)
        ->with(['author', 'category'])
        ->get();

    return response()->view('feed.rss', compact('resources'))
        ->header('Content-Type', 'application/rss+xml');
})->name('feed');

// Robots.txt route
Route::get('/robots.txt', function () {
    $content = "User-agent: *\n";
    $content .= "Allow: /\n";
    $content .= "Sitemap: " . route('sitemap') . "\n";

    return response($content, 200)
        ->header('Content-Type', 'text/plain');
})->name('robots');
