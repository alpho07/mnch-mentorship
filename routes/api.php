<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AssessmentSectionController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AssessmentResponseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\FacilityController;
use App\Http\Controllers\Api\HumanResourceController;
use App\Http\Controllers\Api\HealthProductsController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\MentorshipController;
use App\Http\Middleware\MobileApiCors;

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

// MobileApiCors is the outermost middleware — it runs on every request
// including OPTIONS preflight, and unconditionally sets CORS headers.
// This covers: emulator (https://localhost), real device over WiFi (no Origin
// header), iOS (capacitor://localhost), and browser dev (localhost:5173).
Route::prefix('v1')->name('api.v1.')->middleware(MobileApiCors::class)->group(function () {

    // =========================================================================
    // PUBLIC — No authentication required
    // =========================================================================
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    });

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

        // ── Facilities ────────────────────────────────────────────────────────
        Route::prefix('facilities')->name('facilities.')->group(function () {
            Route::get('/', [FacilityController::class, 'index'])->name('index');
            Route::get('{facility}', [FacilityController::class, 'show'])->name('show');
            Route::get('county/{countyId}', [FacilityController::class, 'byCounty'])->name('by-county');
        });

        // ── Assessment Sections & Questions (form schema) ─────────────────────
        Route::prefix('sections')->name('sections.')->group(function () {
            Route::get('/', [AssessmentSectionController::class, 'index'])->name('index');
            Route::get('schema/full', [AssessmentSectionController::class, 'fullSchema'])->name('schema');
            Route::get('{section}', [AssessmentSectionController::class, 'show'])->name('show');
        });

        // ── Assessments ───────────────────────────────────────────────────────
        Route::prefix('assessments')->name('assessments.')->group(function () {
            Route::get('/', [AssessmentController::class, 'index'])->name('index');
            Route::post('/', [AssessmentController::class, 'store'])->name('store');
            Route::get('{assessment}', [AssessmentController::class, 'show'])->name('show');
            Route::put('{assessment}', [AssessmentController::class, 'update'])->name('update');
            Route::delete('{assessment}', [AssessmentController::class, 'destroy'])->name('destroy');
            Route::post('{assessment}/submit', [AssessmentController::class, 'submit'])->name('submit');

            // ── Human Resources ───────────────────────────────────────────────
            Route::get('{assessment}/human-resources', [HumanResourceController::class, 'index'])->name('human-resources.index');
            Route::post('{assessment}/human-resources', [HumanResourceController::class, 'store'])->name('human-resources.store');

            // ── Health Products ───────────────────────────────────────────────
            Route::get('{assessment}/health-products', [HealthProductsController::class, 'index'])->name('health-products.index');
            Route::post('{assessment}/health-products', [HealthProductsController::class, 'store'])->name('health-products.store');

            // ── Section progress ──────────────────────────────────────────────
            Route::put('{assessment}/sections/{sectionCode}/progress',
                    [AssessmentController::class, 'updateSectionProgress'])->name('section-progress');

            // ── Responses ─────────────────────────────────────────────────────
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
                Route::post('email', [ReportController::class, 'emailReport'])->name('email');
                Route::get('email/{emailJob}', [ReportController::class, 'emailJobStatus'])->name('email-status');
            });
        });

        // ── Aggregate Reports ─────────────────────────────────────────────────
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('dashboard', [ReportController::class, 'dashboard'])->name('dashboard');
            Route::get('section-averages', [ReportController::class, 'sectionAverages'])->name('section-averages');
            Route::get('email-jobs', [ReportController::class, 'emailJobs'])->name('email-jobs');
        });

        // ── Mentorships ───────────────────────────────────────────────────────
        Route::prefix('mentorships')->name('mentorships.')->group(function () {
            Route::get('/', [MentorshipController::class, 'index'])->name('index');
            Route::get('{training}', [MentorshipController::class, 'show'])->name('show');
            Route::get('{training}/classes', [MentorshipController::class, 'classes'])->name('classes');
            Route::get('{training}/classes/{class}', [MentorshipController::class, 'classDetail'])->name('class-detail');
        });

        // ── Class modules ─────────────────────────────────────────────────────────
        Route::prefix('classes/{class}/modules')->name('class.modules.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\ClassModuleController::class, 'index'])->name('index');
        });
        Route::prefix('modules/{module}')->name('module.')->group(function () {
            Route::post('start', [\App\Http\Controllers\Api\ClassModuleController::class, 'start'])->name('start');
            Route::post('complete', [\App\Http\Controllers\Api\ClassModuleController::class, 'complete'])->name('complete');
            Route::get('sessions', [\App\Http\Controllers\Api\ClassModuleController::class, 'sessions'])->name('sessions');
            Route::get('attendance', [\App\Http\Controllers\Api\AttendanceApiController::class, 'roster'])->name('attendance.roster');
            Route::post('attendance/bulk', [\App\Http\Controllers\Api\AttendanceApiController::class, 'bulk'])->name('attendance.bulk');
            Route::post('attendance/{participant}', [\App\Http\Controllers\Api\AttendanceApiController::class, 'mark'])->name('attendance.mark');
        });

        // ── Classes / participants ─────────────────────────────────────────────────
        Route::get('classes/{class}/participants', [\App\Http\Controllers\Api\ParticipantController::class, 'index'])->name('classes.participants');
        Route::get('participants/{participant}/progress', [\App\Http\Controllers\Api\ParticipantController::class, 'progress'])->name('participants.progress');

        // ── Mentee (self) ─────────────────────────────────────────────────────────
        Route::prefix('me/classes')->name('me.classes.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\MenteeApiController::class, 'index'])->name('index');
            Route::get('{class}', [\App\Http\Controllers\Api\MenteeApiController::class, 'show'])->name('show');
            Route::post('{class}/modules/{module}/attend', [\App\Http\Controllers\Api\MenteeApiController::class, 'attend'])->name('attend');
        });

        // ── Global trainings ──────────────────────────────────────────────────────
        Route::prefix('trainings')->name('trainings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\GlobalTrainingController::class, 'index'])->name('index');
            Route::get('{training}', [\App\Http\Controllers\Api\GlobalTrainingController::class, 'show'])->name('show');
            Route::get('{training}/participants', [\App\Http\Controllers\Api\GlobalTrainingController::class, 'participants'])->name('participants');
        });

        // ── Chat Assistant ──────────────────────────────────────────────────
        Route::post('chat/assistant', [ChatController::class, 'assistant'])->name('chat.assistant');
    });
});
