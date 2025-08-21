<?php

namespace App\Policies;

use App\Models\MonthlyReport;
use App\Models\User;

class MonthlyReportPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view reports
    }

    public function view(User $user, MonthlyReport $monthlyReport): bool
    {
        return true;
       // return $user->canAccessFacility($monthlyReport->facility_id);
    }

    public function create(User $user): bool
    {
        return true; // All authenticated users can create reports
    }

    public function update(User $user, MonthlyReport $monthlyReport): bool
    {
        return true;
       // return $user->canAccessFacility($monthlyReport->facility_id) &&
         //      $monthlyReport->canEdit();
    }

    public function delete(User $user, MonthlyReport $monthlyReport): bool
    {
        return $user->canAccessFacility($monthlyReport->facility_id) &&
               $monthlyReport->canEdit() &&
               $user->hasRole(['Super Admin', 'Division Lead']);
    }

    public function approve(User $user, MonthlyReport $monthlyReport): bool
    {
        return $user->canAccessFacility($monthlyReport->facility_id) &&
               $monthlyReport->canApprove() &&
               $user->hasRole(['Super Admin', 'Division Lead']);
    }
}
