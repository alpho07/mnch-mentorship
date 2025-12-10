<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MentorshipClass extends Model {

    use HasFactory,
        SoftDeletes;

    protected $fillable = [
        'training_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'created_by',
        'notes',
        'enrollment_token',
        'enrollment_link_active',
    ];
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'enrollment_link_active' => 'boolean',
    ];

    // Relationships
    public function training(): BelongsTo {
        return $this->belongsTo(Training::class);
    }

    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function classModules(): HasMany {
        return $this->hasMany(ClassModule::class, 'mentorship_class_id');
    }

    public function participants(): HasMany {
        return $this->hasMany(ClassParticipant::class, 'mentorship_class_id');
    }

    // Computed Attributes
    public function getModuleCountAttribute(): int {
        return $this->classModules()->count();
    }

    public function getSessionCountAttribute(): int {
        return ClassSession::whereHas('classModule', function ($query) {
                    $query->where('mentorship_class_id', $this->id);
                })->count();
    }

    public function getCompletedModulesCountAttribute(): int {
        return $this->classModules()->where('status', 'completed')->count();
    }

    public function getProgressPercentageAttribute(): float {
        $totalModules = $this->module_count;

        if ($totalModules === 0) {
            return 0;
        }

        $completedModules = $this->completed_modules_count;
        return round(($completedModules / $totalModules) * 100, 1);
    }

    public function getDurationDaysAttribute(): int {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }

        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    // Query Scopes
    public function scopeActive($query) {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query) {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query) {
        return $query->where('status', 'cancelled');
    }

    public function scopeByTraining($query, int $trainingId) {
        return $query->where('training_id', $trainingId);
    }

    // Helper Methods
    public function activate(): bool {
        return $this->update(['status' => 'active']);
    }

    public function complete(): bool {
        return $this->update(['status' => 'completed', 'end_date' => now()]);
    }

    public function cancel(): bool {
        return $this->update(['status' => 'cancelled']);
    }

    public function canBeDeleted(): bool {
        // Can only delete if no sessions have been conducted
        return $this->classModules()
                        ->whereHas('sessions', function ($query) {
                            $query->where('status', 'completed');
                        })
                        ->doesntExist();
    }
}
