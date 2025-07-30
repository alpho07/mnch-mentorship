<?php

use Illuminate\Support\Facades\Route;
   use League\Csv\Writer;
   


Route::get('/', function () {
    $widget = new \App\Filament\Widgets\KenyaTrainingHeatmapWidget();
    return view('dashboard', ['widget' => $widget]);
})->name('training.heatmap');


Route::get('/training/{training}/participants/template', function ($trainingId) {
    // Import required classes
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
