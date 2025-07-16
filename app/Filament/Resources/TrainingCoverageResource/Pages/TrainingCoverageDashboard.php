<?php

namespace App\Filament\Resources\TrainingCoverageResource\Pages;

use App\Filament\Resources\TrainingCoverageResource;
use Filament\Resources\Pages\Page;

class TrainingCoverageDashboard extends Page
{
    protected static string $resource = TrainingCoverageResource::class;

    protected static string $view = 'filament.resources.training-coverage-resource.pages.training-coverage-dashboard';
}
