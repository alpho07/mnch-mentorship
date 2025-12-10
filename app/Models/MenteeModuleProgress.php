<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenteeModuleProgress extends Model {

    protected $table = 'mentee_module_progress';
    protected $fillable = [
        'class_participant_id',
        'class_module_id',
        'status',
        'started_at',
        'completed_at',
        'exempted_at',
        'completed_in_previous_class',
        'attendance_percentage',
        'assessment_score',
        'assessment_status',
        'notes',
    ];
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'exempted_at' => 'datetime',
        'completed_in_previous_class' => 'boolean',
        'attendance_percentage' => 'float',
        'assessment_score' => 'float',
    ];

    // Relationships
    public function classParticipant(): BelongsTo {
        return $this->belongsTo(ClassParticipant::class);
    }

    public function classModule(): BelongsTo {
        return $this->belongsTo(ClassModule::class);
    }

    public function assessments(): HasMany {
        return $this->hasMany(ModuleAssessment::class, 'mentee_progress_id');
    }

    // Computed Attributes
    public function getIsExemptedAttribute(): bool {
        return $this->status === 'exempted' || $this->completed_in_previous_class;
    }

    public function getIsCompletedAttribute(): bool {
        return in_array($this->status, ['completed', 'exempted']);
    }

    public function getRequiresAssessmentAttribute(): bool {
        return $this->status === 'in_progress' &&
                $this->classModule->requires_assessment;
    }

    public function getHasPassedAssessmentAttribute(): bool {
        return $this->assessment_status === 'passed';
    }

    // Status Methods
    public function markStarted(): bool {
        if ($this->is_exempted) {
            return false;
        }

        return $this->update([
                    'status' => 'in_progress',
                    'started_at' => $this->started_at ?? now(),
        ]);
    }

    public function markCompleted(
            ?float $attendancePercentage = null,
            ?float $assessmentScore = null,
            ?string $assessmentStatus = null
    ): bool {
        if ($this->is_exempted) {
            return false;
        }

        return $this->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'attendance_percentage' => $attendancePercentage ?? $this->attendance_percentage,
                    'assessment_score' => $assessmentScore ?? $this->assessment_score,
                    'assessment_status' => $assessmentStatus ?? $this->assessment_status,
        ]);
    }

    public function recordAssessment(float $score, string $status): bool {
        return $this->update([
                    'assessment_score' => $score,
                    'assessment_status' => $status,
        ]);
    }

    // Query Scopes
    public function scopeExempted($query) {
        return $query->where(function ($q) {
                    $q->where('status', 'exempted')
                            ->orWhere('completed_in_previous_class', true);
                });
    }

    public function scopeNotExempted($query) {
        return $query->where('status', '!=', 'exempted')
                        ->where('completed_in_previous_class', false);
    }

    public function scopeInProgress($query) {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query) {
        return $query->whereIn('status', ['completed', 'exempted']);
    }

    public function scopePending($query) {
        return $query->where('status', 'not_started')
                        ->where('completed_in_previous_class', false);
    }
}
