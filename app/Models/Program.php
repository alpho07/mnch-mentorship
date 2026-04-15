<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
    ];

    // Relationships
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    // Query Scopes
    public function scopeByName($query, string $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }

    public function scopeWithModules($query)
    {
        return $query->has('modules');
    }

    public function scopeWithTrainings($query)
    {
        return $query->has('trainings');
    }

    public function scopeActive($query)
    {
        return $query->whereHas('trainings', function ($q) {
            $q->where('end_date', '>=', now());
        });
    }

    // Computed Attributes
    public function getModuleCountAttribute(): int
    {
        return $this->modules()->count();
    }

    public function getTrainingCountAttribute(): int
    {
        return $this->trainings()->count();
    }

    public function getActiveTrainingCountAttribute(): int
    {
        return $this->trainings()
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->count();
    }

    public function getTotalParticipantsAttribute(): int
    {
        return TrainingParticipant::whereHas('training', function ($query) {
            $query->where('program_id', $this->id);
        })->count();
    }

    public function getCompletedTrainingCountAttribute(): int
    {
        return $this->trainings()
            ->where('end_date', '<', now())
            ->count();
    }

    public function getUpcomingTrainingCountAttribute(): int
    {
        return $this->trainings()
            ->where('start_date', '>', now())
            ->count();
    }
}