<?php

namespace App\Providers;

use App\Filament\Widgets\KenyaTrainingHeatmapWidget;
use App\Filament\Widgets\TrainingChartsWidget;
use App\Filament\Widgets\TrainingCoverageStatsWidget;
use App\Filament\Widgets\TrainingsByMonthChart;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Livewire\TrainingCoverageDashboard;
use App\Livewire\SimpleChartWidget;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Illuminate\Support\Facades\URL;
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


        URL::forceScheme('https');
        URL::forceRootUrl(config('app.url'));

      

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
}
