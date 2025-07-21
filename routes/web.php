<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/training-heatmap', function () {
    $widget = new \App\Filament\Widgets\KenyaTrainingHeatmapWidget();
    return view('test', ['widget' => $widget]);
})->name('training.heatmap');
