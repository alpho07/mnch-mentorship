<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable {

    use HasFactory,
        Notifiable,
        HasRoles,
        SoftDeletes;

    protected $fillable = [
        'facility_id',
        'department_id',
        'cadre_id',
        'role',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'id_number',
        'phone',
        'status',
        'password',
        'email_verified_at',
    ];
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Computed Attributes
    public function getFullNameAttribute(): string {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function getFullName1Attribute(): string {
        return $this->full_name;
    }

    // Relationships
    public function facility(): BelongsTo {
        return $this->belongsTo(Facility::class);
    }

    public function department(): BelongsTo {
        return $this->belongsTo(Department::class);
    }

    public function cadre(): BelongsTo {
        return $this->belongsTo(Cadre::class);
    }

    public function organizedTrainings(): HasMany {
        return $this->hasMany(Training::class, 'organizer_id');
    }

    public function trainingParticipations(): HasMany {
        return $this->hasMany(TrainingParticipant::class);
    }

    // Many-to-many relationships for user access scoping
    public function counties(): BelongsToMany {
        return $this->belongsToMany(County::class, 'county_user');
    }

    public function subcounties(): BelongsToMany {
        return $this->belongsToMany(Subcounty::class, 'subcounty_user');
    }

    public function facilities(): BelongsToMany {
        return $this->belongsToMany(Facility::class, 'facility_user');
    }

    // Authorization Helper Methods
    public function isAboveSite(): bool {
        return $this->hasRole(['Super Admin', 'Division Lead', 'National Mentor Lead']);
    }

    public function scopedCountyIds() {
        return $this->isAboveSite() ? County::pluck('id') : $this->counties()->pluck('id');
    }

    public function scopedSubcountyIds() {
        return $this->isAboveSite() ? Subcounty::pluck('id') : $this->subcounties()->pluck('id');
    }

    public function scopedFacilityIds() {
        return $this->isAboveSite() ? Facility::pluck('facilities.id') : $this->facilities()->select('facilities.id')->pluck('facilities.id');
    }

    public function canAccessFacility(int $facilityId): bool {
        return true; //$this->isAboveSite() || $this->scopedFacilityIds()->contains($facilityId);
    }

    // Query Scopes
    public function scopeByRole($query, string $role) {
        return $query->where('role', $role);
    }

    public function scopeByStatus($query, string $status) {
        return $query->where('status', $status);
    }

    public function scopeByFacility($query, int $facilityId) {
        return $query->where('facility_id', $facilityId);
    }

    // Author relationship
    public function authoredResources(): HasMany {
        return $this->hasMany(Resource::class, 'author_id');
    }

    // Comments
    public function resourceComments(): HasMany {
        return $this->hasMany(ResourceComment::class);
    }

    // Interactions (likes, bookmarks, etc.)
    public function resourceInteractions(): HasMany {
        return $this->hasMany(ResourceInteraction::class);
    }

    // Access groups
    public function accessGroups(): BelongsToMany {
        return $this->belongsToMany(AccessGroup::class, 'access_group_users');
    }

    // Resource views
    public function resourceViews(): HasMany {
        return $this->hasMany(ResourceView::class);
    }

    // Resource downloads
    public function resourceDownloads(): HasMany {
        return $this->hasMany(ResourceDownload::class);
    }

    // Helper methods for User model
    public function hasLikedResource(Resource $resource): bool {
        return $this->resourceInteractions()
                        ->where('resource_id', $resource->id)
                        ->where('type', 'like')
                        ->exists();
    }

    public function hasDislikedResource(Resource $resource): bool {
        return $this->resourceInteractions()
                        ->where('resource_id', $resource->id)
                        ->where('type', 'dislike')
                        ->exists();
    }

    public function hasBookmarkedResource(Resource $resource): bool {
        return $this->resourceInteractions()
                        ->where('resource_id', $resource->id)
                        ->where('type', 'bookmark')
                        ->exists();
    }

    public function toggleResourceInteraction(Resource $resource, string $type): bool {
        $existing = $this->resourceInteractions()
                ->where('resource_id', $resource->id)
                ->where('type', $type)
                ->first();

        if ($existing) {
            $existing->delete();
            return false;
        }

        $this->resourceInteractions()->create([
            'resource_id' => $resource->id,
            'type' => $type,
            'ip_address' => request()->ip(),
        ]);

        return true;
    }

    // Check if user can access a resource
    public function canAccessResource(Resource $resource): bool {
        return $resource->canUserAccess($this);
    }

    // New relationships for mentee tracking
    public function statusLogs(): HasMany {
        return $this->hasMany(MenteeStatusLog::class)->orderBy('effective_date', 'desc');
    }

    /* public function assessmentResults(): HasMany {
      return $this->hasMany(MenteeAssessmentResult::class, 'participant_id');
      } */

    public function assessmentResults(): \Illuminate\Database\Eloquent\Relations\HasManyThrough {
        return $this->hasManyThrough(
                        MenteeAssessmentResult::class,
                        TrainingParticipant::class,
                        'user_id', // FK on training_participants
                        'participant_id', // FK on mentee_assessment_results  
                        'id', // Local key on users
                        'id'             // Local key on training_participants
                );
    }

// Computed Attributes for Mentee Profile
    public function getCurrentStatusAttribute(): string {
        $latestLog = $this->statusLogs()->first();
        return $latestLog?->new_status ?? MenteeStatusLog::STATUS_ACTIVE;
    }

    public function getCurrentStatusLogAttribute(): ?MenteeStatusLog {
        return $this->statusLogs()->first();
    }

    public function getOverallTrainingScoreAttribute(): ?float {
        return $this->trainingParticipations()
                        ->whereHas('objectiveResults')
                        ->with('objectiveResults')
                        ->get()
                        ->map(function ($participation) {
                            return $participation->objectiveResults->avg('score');
                        })
                        ->filter()
                        ->avg();
    }

    public function getTrainingCompletionRateAttribute(): float {
        $totalTrainings = $this->trainingParticipations()->count();
        if ($totalTrainings === 0)
            return 0;

        $completedTrainings = $this->trainingParticipations()
                ->where('completion_status', 'completed')
                ->count();

        return round(($completedTrainings / $totalTrainings) * 100, 1);
    }

    public function getTrainingHistorySummaryAttribute(): array {
        $participations = $this->trainingParticipations()
                ->with(['training', 'objectiveResults'])
                ->get();

        return [
            'total_trainings' => $participations->count(),
            'completed' => $participations->where('completion_status', 'completed')->count(),
            'passed' => $participations->filter(function ($p) {
                $avgScore = $p->objectiveResults->avg('score');
                return $avgScore && $avgScore >= 70;
            })->count(),
            'average_score' => $this->overall_training_score,
            'latest_training' => $participations->sortByDesc('registration_date')->first()?->training?->title,
        ];
    }

    public function getIsActiveAttribute(): bool {
        return in_array($this->current_status, [
            MenteeStatusLog::STATUS_ACTIVE,
            MenteeStatusLog::STATUS_STUDY_LEAVE
        ]);
    }

    public function getPerformanceTrendAttribute(): string {
        $recentScores = $this->trainingParticipations()
                ->with('objectiveResults')
                ->latest('completion_date')
                ->limit(3)
                ->get()
                ->map(fn($p) => $p->objectiveResults->avg('score'))
                ->filter()
                ->values();

        if ($recentScores->count() < 2)
            return 'Insufficient Data';

        $firstScore = $recentScores->last();
        $latestScore = $recentScores->first();
        $improvement = $latestScore - $firstScore;

        if ($improvement > 5)
            return 'Improving';
        if ($improvement < -5)
            return 'Declining';
        return 'Stable';
    }

// Methods for mentee management
    public function updateStatus(
            string $newStatus,
            string $reason = null,
            string $notes = null,
            $effectiveDate = null
    ): MenteeStatusLog {
        $currentStatus = $this->current_status;

        $statusLog = $this->statusLogs()->create([
            'previous_status' => $currentStatus,
            'new_status' => $newStatus,
            'effective_date' => $effectiveDate ?? now(),
            'reason' => $reason,
            'notes' => $notes,
            'changed_by' => auth()->id(),
            'facility_id' => $this->facility_id,
        ]);

        // Update user status field if exists
        if ($this->hasAttribute('status')) {
            $this->update(['status' => $newStatus]);
        }

        return $statusLog;
    }

    public function getMentorshipTrainings() {
        return $this->trainingParticipations()
                        ->whereHas('training', function ($query) {
                            $query->where('type', 'facility_mentorship');
                        })
                        ->with(['training', 'objectiveResults.assessmentCategory']);
    }

    public function getAttritionRisk(): string {
        $riskFactors = 0;

        // Performance decline
        if ($this->performance_trend === 'Declining')
            $riskFactors++;

        // Low completion rate
        if ($this->training_completion_rate < 70)
            $riskFactors++;

        // Recent poor performance
        if ($this->overall_training_score && $this->overall_training_score < 60)
            $riskFactors++;

        // No recent training activity
        $lastTraining = $this->trainingParticipations()->latest('registration_date')->first();
        if (!$lastTraining || $lastTraining->registration_date < now()->subMonths(6)) {
            $riskFactors++;
        }

        return match ($riskFactors) {
            0, 1 => 'Low',
            2 => 'Medium',
            default => 'High',
        };
    }

// Scopes for mentee queries
    public function scopeActiveMentees($query) {
        return $query->whereHas('statusLogs', function ($q) {
                    $q->whereIn('new_status', [
                        MenteeStatusLog::STATUS_ACTIVE,
                        MenteeStatusLog::STATUS_STUDY_LEAVE
                    ])->latest('effective_date')->limit(1);
                })->orWhereDoesntHave('statusLogs');
    }

    public function scopeByCurrentStatus($query, string $status) {
        return $query->whereHas('statusLogs', function ($q) use ($status) {
                    $q->where('new_status', $status)
                            ->latest('effective_date')
                            ->limit(1);
                });
    }

    public function scopeHighPerformers($query) {
        return $query->whereHas('trainingParticipations.objectiveResults', function ($q) {
                    $q->havingRaw('AVG(score) >= 85');
                });
    }

    public function scopeAtRisk($query) {
        return $query->whereHas('trainingParticipations', function ($q) {
                    $q->where('completion_status', '!=', 'completed')
                            ->orWhereHas('objectiveResults', function ($qq) {
                                $qq->havingRaw('AVG(score) < 60');
                            });
                });
    }

    public function getMentorshipAssessmentResults() {
        return $this->hasManyThrough(
                        MenteeAssessmentResult::class,
                        TrainingParticipant::class,
                        'user_id',
                        'participant_id',
                        'id',
                        'id'
                )->whereHas('participant.training', function ($query) {
                    $query->where('type', 'facility_mentorship');
                });
    }

    // Get overall mentorship performance
    public function getMentorshipPerformance(): array {
        $participations = $this->trainingParticipations()
                ->whereHas('training', function ($query) {
                    $query->where('type', 'facility_mentorship');
                })
                ->with(['training', 'assessmentResults'])
                ->get();

        $totalTrainings = $participations->count();
        $completedTrainings = 0;
        $passedTrainings = 0;
        $totalScore = 0;
        $assessedTrainings = 0;

        foreach ($participations as $participation) {
            if ($participation->completion_status === 'completed') {
                $completedTrainings++;
            }

            $calculation = $participation->training->calculateOverallScore($participation);

            if ($calculation['all_assessed']) {
                $assessedTrainings++;
                $totalScore += $calculation['score'];

                if ($calculation['status'] === 'PASSED') {
                    $passedTrainings++;
                }
            }
        }

        return [
            'total_trainings' => $totalTrainings,
            'completed_trainings' => $completedTrainings,
            'passed_trainings' => $passedTrainings,
            'assessed_trainings' => $assessedTrainings,
            'completion_rate' => $totalTrainings > 0 ? round(($completedTrainings / $totalTrainings) * 100, 1) : 0,
            'pass_rate' => $assessedTrainings > 0 ? round(($passedTrainings / $assessedTrainings) * 100, 1) : 0,
            'average_score' => $assessedTrainings > 0 ? round($totalScore / $assessedTrainings, 1) : 0,
        ];
    }
}
