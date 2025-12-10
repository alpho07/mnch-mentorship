<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassModule extends Model {

    use HasFactory;

    protected $fillable = [
        'mentorship_class_id',
        'program_module_id',
        'status',
        'order_sequence',
        'started_at',
        'completed_at',
        'notes',
    ];
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'order_sequence' => 'integer',
    ];

    // Relationships
    public function mentorshipClass(): BelongsTo {
        return $this->belongsTo(MentorshipClass::class, 'mentorship_class_id');
    }

    public function programModule(): BelongsTo {
        return $this->belongsTo(ProgramModule::class, 'program_module_id');
    }

    public function sessions(): HasMany {
        return $this->hasMany(ClassSession::class, 'class_module_id');
    }

    public function assessments(): HasMany {
        return $this->hasMany(ModuleAssessment::class, 'class_module_id');
    }

    public function menteeProgress(): HasMany {
        return $this->hasMany(MenteeModuleProgress::class, 'class_module_id');
    }

    // Computed Attributes
    public function getNameAttribute(): string {
        return $this->programModule?->name ?? 'Module';
    }

    public function getDescriptionAttribute(): ?string {
        return $this->programModule?->description;
    }

    public function getSessionCountAttribute(): int {
        return $this->sessions()->count();
    }

    public function getCompletedSessionsCountAttribute(): int {
        return $this->sessions()->where('status', 'completed')->count();
    }

    public function getProgressPercentageAttribute(): float {
        $totalSessions = $this->session_count;

        if ($totalSessions === 0) {
            return 0;
        }

        $completedSessions = $this->completed_sessions_count;
        return round(($completedSessions / $totalSessions) * 100, 1);
    }

    // Query Scopes
    public function scopeNotStarted($query) {
        return $query->where('status', 'not_started');
    }

    public function scopeInProgress($query) {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query) {
        return $query->where('status', 'completed');
    }

    public function scopeByClass($query, int $classId) {
        return $query->where('mentorship_class_id', $classId);
    }

    public function scopeOrdered($query) {
        return $query->orderBy('order_sequence');
    }

    // Helper Methods
    public function start(): bool {
        return $this->update([
                    'status' => 'in_progress',
                    'started_at' => $this->started_at ?? now(),
        ]);
    }

    public function complete(): bool {
        return $this->update([
                    'status' => 'completed',
                    'completed_at' => now(),
        ]);
    }

    public function isComplete(): bool {
        return $this->status === 'completed';
    }
}
