<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantObjectiveResult extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'objective_id',
        'training_participant_id',
        'grade_id',
        'result',
        'comments',
    ];

    protected $with = ['grade'];

    // Relationships
    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }
    
    public function participant(): BelongsTo
    {
        return $this->belongsTo(TrainingParticipant::class, 'training_participant_id');
    }
    
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    // Query Scopes
    public function scopeByObjective($query, int $objectiveId)
    {
        return $query->where('objective_id', $objectiveId);
    }

    public function scopeByParticipant($query, int $participantId)
    {
        return $query->where('training_participant_id', $participantId);
    }

    public function scopeByGrade($query, int $gradeId)
    {
        return $query->where('grade_id', $gradeId);
    }

    public function scopeWithComments($query)
    {
        return $query->whereNotNull('comments')->where('comments', '!=', '');
    }

    public function scopeByResultRange($query, $min, $max)
    {
        return $query->whereRaw('CAST(result AS DECIMAL(5,2)) BETWEEN ? AND ?', [$min, $max]);
    }

    public function scopePassed($query)
    {
        // Assuming 'passed' grades or numeric results >= 50
        return $query->where(function ($q) {
            $q->whereHas('grade', function ($gradeQuery) {
                $gradeQuery->whereIn('name', ['Pass', 'Passed', 'Competent']);
            })->orWhereRaw('CAST(result AS DECIMAL(5,2)) >= 50');
        });
    }

    public function scopeFailed($query)
    {
        return $query->where(function ($q) {
            $q->whereHas('grade', function ($gradeQuery) {
                $gradeQuery->whereIn('name', ['Fail', 'Failed', 'Not Competent']);
            })->orWhereRaw('CAST(result AS DECIMAL(5,2)) < 50');
        });
    }

    // Computed Attributes
    public function getNumericResultAttribute(): ?float
    {
        return is_numeric($this->result) ? (float) $this->result : null;
    }

    public function getIsPassedAttribute(): bool
    {
        // Check if grade indicates pass or numeric result >= 50
        if ($this->grade) {
            $passGrades = ['Pass', 'Passed', 'Competent', 'Satisfactory'];
            if (in_array($this->grade->name, $passGrades)) {
                return true;
            }
        }

        return $this->numeric_result >= 50;
    }

    public function getHasCommentsAttribute(): bool
    {
        return !empty($this->comments);
    }

    public function getResultDisplayAttribute(): string
    {
        if ($this->grade) {
            return $this->grade->name . ($this->result ? " ({$this->result})" : '');
        }

        return $this->result ?? 'No Result';
    }
}