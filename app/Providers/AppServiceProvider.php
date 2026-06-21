<?php

namespace App\Providers;

use App\Filament\Widgets\KenyaTrainingHeatmapWidget;
use App\Filament\Widgets\TrainingChartsWidget;
use App\Filament\Widgets\TrainingCoverageStatsWidget;
use App\Filament\Widgets\TrainingsByMonthChart;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Livewire\TrainingCoverageDashboard;
use App\Livewire\SimpleChartWidget;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Illuminate\Support\Facades\URL;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use App\Filament\Widgets\MentorStatsWidget;
use App\Filament\Widgets\MenteeDashboard;

class AppServiceProvider extends ServiceProvider {

    /**
     * Register any application services.
     */
    public function register(): void {
        $this->app->singleton(\App\Services\AssessmentScoringService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {

        // Register our upload bypass before Livewire's ServiceProvider has a
        // chance to register its own signed-URL–gated route.
        Route::post('/livewire/upload-file', [\App\Http\Controllers\LivewireUploadController::class, 'handle'])
            ->middleware('web')
            ->name('livewire.upload-file');

        $this->configureRateLimiters();

       // URL::forceScheme('https');
       // URL::forceRootUrl(config('app.url'));

      

        \App\Models\MonthlyReport::observe(\App\Observers\MonthlyReportObserver::class);

        FilamentAsset::register([
            Js::make('kenya-heatmap', asset('local.js')),
            Css::make('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'),
            Js::make('leaflet-core', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'),
            Js::make('leaflet-heat', 'https://unpkg.com/leaflet.heat/dist/leaflet-heat.js'),
            Js::make('leaflet-turf', 'https://unpkg.com/@turf/turf@6.5.0/turf.min.js'),
        ]);

        Livewire::component('training-coverage-stats-widget', TrainingCoverageStatsWidget::class);
        Livewire::component('training-charts-widget', TrainingChartsWidget::class);
        Livewire::component('kenya-training-heatmap-widget', KenyaTrainingHeatmapWidget::class);
        //Livewire::component('mentor-stats-widget', MentorStatsWidget::class);
//
//        FilamentView::registerRenderHook(
//                PanelsRenderHook::BODY_END,
//                fn(): string => Blade::render('@vite(\'resources/css/filament/admin/theme.css\')'),
//        );
    }

    private function configureRateLimiters(): void {
        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for(\App\Models\User::class . '::search', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('downloads', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('previews', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('interactions', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('comments', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('guest-comments', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('comment-updates', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perHour(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(200)->by($request->user()?->id ?: $request->ip());
        });
    }
}
