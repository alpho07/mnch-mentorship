<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_id',
        'user_id',
        'name',
        'cadre_id',
        'department_id',
        'mobile',
        'email',
        'is_tot',
        'outcome_id',
    ];

    protected $casts = [
        'is_tot' => 'boolean',
    ];

    protected $with = ['cadre', 'department', 'outcome'];

    // Relationships
    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cadre(): BelongsTo
    {
        return $this->belongsTo(Cadre::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function outcome(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'outcome_id');
    }

    public function objectiveResults(): HasMany
    {
        return $this->hasMany(ParticipantObjectiveResult::class, 'training_participant_id');
    }

    // Query Scopes
    public function scopeByTraining($query, int $trainingId)
    {
        return $query->where('training_id', $trainingId);
    }

    public function scopeByCadre($query, int $cadreId)
    {
        return $query->where('cadre_id', $cadreId);
    }

    public function scopeByDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeTrainersOfTrainers($query)
    {
        return $query->where('is_tot', true);
    }

    public function scopeRegularParticipants($query)
    {
        return $query->where('is_tot', false);
    }

    public function scopeWithOutcome($query)
    {
        return $query->whereNotNull('outcome_id');
    }

    public function scopeByOutcome($query, int $gradeId)
    {
        return $query->where('outcome_id', $gradeId);
    }

    // Computed Attributes
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?? $this->user?->full_name ?? 'Unknown Participant';
    }

    public function getContactInfoAttribute(): array
    {
        return [
            'email' => $this->email ?? $this->user?->email,
            'mobile' => $this->mobile ?? $this->user?->phone,
        ];
    }

    public function getIsRegisteredUserAttribute(): bool
    {
        return !is_null($this->user_id);
    }

    public function getObjectiveResultsCountAttribute(): int
    {
        return $this->objectiveResults()->count();
    }

    public function getAverageScoreAttribute(): ?float
    {
        $results = $this->objectiveResults()
            ->whereNotNull('result')
            ->pluck('result')
            ->filter(function ($result) {
                return is_numeric($result);
            });

        return $results->isEmpty() ? null : $results->avg();
    }
}