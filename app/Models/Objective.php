<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Objective extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_session_id',
        'objective_text',
        'type',
        'objective_order',
    ];

    protected $casts = [
        'objective_order' => 'integer',
    ];

    // Relationships
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ParticipantObjectiveResult::class);
    }

    // Query Scopes
    public function scopeBySession($query, int $sessionId)
    {
        return $query->where('training_session_id', $sessionId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSkillBased($query)
    {
        return $query->where('type', 'skill');
    }

    public function scopeNonSkillBased($query)
    {
        return $query->where('type', 'non-skill');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('objective_order');
    }

    public function scopeWithResults($query)
    {
        return $query->has('results');
    }

    // Computed Attributes
    public function getResultsCountAttribute(): int
    {
        return $this->results()->count();
    }

    public function getAverageScoreAttribute(): ?float
    {
        $scores = $this->results()
            ->whereNotNull('result')
            ->pluck('result')
            ->filter(function ($result) {
                return is_numeric($result);
            });

        return $scores->isEmpty() ? null : $scores->avg();
    }

    public function getCompletionRateAttribute(): float
    {
        $totalParticipants = $this->session->training->participants()->count();
        $completedResults = $this->results()->whereNotNull('result')->count();

        return $totalParticipants > 0 ? ($completedResults / $totalParticipants) * 100 : 0;
    }

    public function getIsSkillBasedAttribute(): bool
    {
        return $this->type === 'skill';
    }
}