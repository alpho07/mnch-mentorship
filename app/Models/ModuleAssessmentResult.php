<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleAssessmentResult extends Model {

    protected $fillable = [
        'module_assessment_id',
        'class_participant_id',
        'mentee_progress_id',
        'score',
        'status',
        'feedback',
        'assessed_by',
        'assessed_at',
        'answers_data',
    ];
    protected $casts = [
        'score' => 'float',
        'assessed_at' => 'datetime',
        'answers_data' => 'array',
    ];

    // Relationships
    public function moduleAssessment(): BelongsTo {
        return $this->belongsTo(ModuleAssessment::class);
    }

    public function classParticipant(): BelongsTo {
        return $this->belongsTo(ClassParticipant::class);
    }

    public function menteeProgress(): BelongsTo {
        return $this->belongsTo(MenteeModuleProgress::class, 'mentee_progress_id');
    }

    public function assessor(): BelongsTo {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    // Computed Attributes
    public function getHasPassedAttribute(): bool {
        return $this->status === 'passed';
    }

    public function getPercentageAttribute(): float {
        $maxScore = $this->moduleAssessment->max_score ?? 100;
        return ($this->score / $maxScore) * 100;
    }

    // Query Scopes
    public function scopePassed($query) {
        return $query->where('status', 'passed');
    }

    public function scopeFailed($query) {
        return $query->where('status', 'failed');
    }

    public function scopeByMentee($query, int $userId) {
        return $query->whereHas('classParticipant', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
    }
}
