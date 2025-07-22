<?php

namespace App\Providers;

use App\Models\MonthlyReport;
use App\Policies\MonthlyReportPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        MonthlyReport::class => MonthlyReportPolicy::class,
        // Add other model policies here...
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
