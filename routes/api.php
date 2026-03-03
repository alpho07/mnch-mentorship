<?php

use App\Http\Controllers\Analytics\ProgressiveDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AssessmentSectionController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AssessmentResponseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\FacilityController;



/*
  |--------------------------------------------------------------------------
  | MNCH Mobile API Routes
  |--------------------------------------------------------------------------
  |
  | All routes are versioned under /api/v1/
  | Authentication uses Laravel Sanctum token-based auth.
  | Unauthenticated requests to protected routes return 401.
  |
 */

Route::prefix('api/v1')->name('api.v1.')->group(function () {

    // =========================================================================
    // PUBLIC — No authentication required
    // =========================================================================
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    });

    // Health check
    Route::get('health', fn() => response()->json([
                'status' => 'ok',
                'service' => 'MNCH Assessment API',
                'version' => '1.0.0',
                'timestamp' => now()->toIso8601String(),
            ]))->name('health');

    // =========================================================================
    // PROTECTED — Requires Sanctum token (Authorization: Bearer {token})
    // =========================================================================
    Route::middleware(['auth:sanctum', 'api.active'])->group(function () {

        // ── Auth ──────────────────────────────────────────────────────────────
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
        });

        // ── Profile ───────────────────────────────────────────────────────────
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'show'])->name('show');
            Route::put('/', [ProfileController::class, 'update'])->name('update');
            Route::put('password', [ProfileController::class, 'changePassword'])->name('change-password');
            Route::post('avatar', [ProfileController::class, 'uploadAvatar'])->name('avatar');
            Route::get('stats', [ProfileController::class, 'stats'])->name('stats');
        });

        // ── Facilities (lookup/search for facility picker) ────────────────────
        Route::prefix('facilities')->name('facilities.')->group(function () {
            Route::get('/', [FacilityController::class, 'index'])->name('index');
            Route::get('{facility}', [FacilityController::class, 'show'])->name('show');
            Route::get('county/{countyId}', [FacilityController::class, 'byCounty'])->name('by-county');
        });

        // ── Assessment Sections & Questions (form schema) ─────────────────────
        // These endpoints drive the dynamic form in the mobile app.
        Route::prefix('sections')->name('sections.')->group(function () {
            Route::get('/', [AssessmentSectionController::class, 'index'])->name('index');
            Route::get('{section}', [AssessmentSectionController::class, 'show'])->name('show');
            // Returns all active sections with their questions — the app bootstraps from this
            Route::get('schema/full', [AssessmentSectionController::class, 'fullSchema'])->name('schema');
        });

        // ── Assessments ───────────────────────────────────────────────────────
        Route::prefix('assessments')->name('assessments.')->group(function () {
            Route::get('/', [AssessmentController::class, 'index'])->name('index');
            Route::post('/', [AssessmentController::class, 'store'])->name('store');
            Route::get('{assessment}', [AssessmentController::class, 'show'])->name('show');
            Route::put('{assessment}', [AssessmentController::class, 'update'])->name('update');
            Route::delete('{assessment}', [AssessmentController::class, 'destroy'])->name('destroy');
            Route::post('{assessment}/submit', [AssessmentController::class, 'submit'])->name('submit');

            // Section progress (mark section done)
            Route::put('{assessment}/sections/{sectionCode}/progress',
                    [AssessmentController::class, 'updateSectionProgress'])->name('section-progress');

            // ── Responses (bulk save per section) ─────────────────────────────
            Route::prefix('{assessment}/responses')->name('responses.')->group(function () {
                Route::get('/', [AssessmentResponseController::class, 'index'])->name('index');
                Route::post('/', [AssessmentResponseController::class, 'bulkStore'])->name('bulk-store');
                Route::get('{questionCode}', [AssessmentResponseController::class, 'show'])->name('show');
            });

            // ── Reports ───────────────────────────────────────────────────────
            Route::prefix('{assessment}/report')->name('report.')->group(function () {
                Route::get('/', [ReportController::class, 'show'])->name('show');
                Route::get('pdf', [ReportController::class, 'downloadPdf'])->name('pdf');
                Route::get('summary', [ReportController::class, 'summary'])->name('summary');
            });
        });

        // ── Aggregate Reports ─────────────────────────────────────────────────
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('dashboard', [ReportController::class, 'dashboard'])->name('dashboard');
            Route::get('section-averages', [ReportController::class, 'sectionAverages'])->name('section-averages');
        });
    });
});

