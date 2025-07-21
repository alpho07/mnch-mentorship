<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_id',
        'module_id',
        'name',
        'session_time',
        'methodology_id',
    ];

    protected $with = ['module', 'methodology'];

    // Relationships
    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function methodology(): BelongsTo
    {
        return $this->belongsTo(Methodology::class);
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(Objective::class);
    }

    // Query Scopes
    public function scopeByTraining($query, int $trainingId)
    {
        return $query->where('training_id', $trainingId);
    }

    public function scopeByModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeByMethodology($query, int $methodologyId)
    {
        return $query->where('methodology_id', $methodologyId);
    }

    public function scopeWithObjectives($query)
    {
        return $query->has('objectives');
    }

    // Computed Attributes
    public function getObjectiveCountAttribute(): int
    {
        return $this->objectives()->count();
    }

    public function getSkillObjectivesCountAttribute(): int
    {
        return $this->objectives()->where('type', 'skill')->count();
    }

    public function getNonSkillObjectivesCountAttribute(): int
    {
        return $this->objectives()->where('type', 'non-skill')->count();
    }

    public function getSessionDurationAttribute(): ?string
    {
        // Parse session_time if it's in a specific format
        return $this->session_time;
    }


    public function training_session_materials()
    {
        return $this->belongsToMany(InventoryItem::class, 'session_inventories', 'training_session_id', 'inventory_item_id');
    }
}
