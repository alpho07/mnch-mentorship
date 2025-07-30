<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrainingParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_id',
        'user_id',
        'registration_date',
        'attendance_status',
        'completion_status',
        'outcome_id',
        'completion_date',
        'certificate_issued',
        'notes'
    ];

    protected $casts = [
        'registration_date' => 'datetime',
        'completion_date' => 'datetime',
        'certificate_issued' => 'boolean',
    ];

    // Relationships
    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outcome(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'outcome_id');
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('completion_status', 'completed');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('attendance_status', ['registered', 'attending']);
    }

    public function scopeInProgress($query)
    {
        return $query->where('completion_status', 'in_progress');
    }

    public function objectiveResults(): HasMany
    {
        return $this->hasMany(ParticipantObjectiveResult::class, 'participant_id');
    }

    // Computed Attributes
    public function getIsCompletedAttribute(): bool
    {
        return $this->completion_status === 'completed';
    }

    public function getFullNameAttribute(): string
    {
        return $this->user?->full_name ?? 'Unknown User';
    }

    public function getFacilityNameAttribute(): string
    {
        return $this->user?->facility?->name ?? 'Unknown Facility';
    }

    public function getOverallScoreAttribute(): ?float
    {
        $results = $this->objectiveResults;
        if ($results->isEmpty()) {
            return null;
        }

        return $results->avg('score');
    }

    public function getOverallGradeAttribute(): string
    {
        $averageScore = $this->getOverallScoreAttribute();

        if ($averageScore === null) {
            return 'Not Assessed';
        }

        if ($averageScore >= 90) return 'Excellent';
        if ($averageScore >= 80) return 'Very Good';
        if ($averageScore >= 70) return 'Good';
        if ($averageScore >= 60) return 'Fair';
        return 'Needs Improvement';
    }

    public function getAssessmentProgressAttribute(): array
    {
        $training = $this->training;
        if (!$training) {
            return ['assessed' => 0, 'total' => 0, 'percentage' => 0];
        }

        $totalObjectives = Objective::where('training_id', $training->id)->count();
        $assessedObjectives = $this->objectiveResults()->count();

        return [
            'assessed' => $assessedObjectives,
            'total' => $totalObjectives,
            'percentage' => $totalObjectives > 0 ? round(($assessedObjectives / $totalObjectives) * 100, 1) : 0
        ];
    }
}

