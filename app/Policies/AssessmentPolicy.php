<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssessmentPolicy {

    use HandlesAuthorization;

    public function view(User $user, Assessment $assessment): bool {
        // Assessor can view their own; admins/division staff can view all
        return $assessment->assessor_id === $user->id || $user->hasAnyRole(['super_admin', 'admin', 'division']);
    }

    public function update(User $user, Assessment $assessment): bool {
        return $assessment->assessor_id === $user->id || $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function delete(User $user, Assessment $assessment): bool {
        return $assessment->assessor_id === $user->id || $user->hasRole('super_admin');
    }
}
