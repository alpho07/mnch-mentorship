<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassParticipant extends Model {

    use HasFactory;

    protected $fillable = [
        'mentorship_class_id',
        'user_id',
        'status',
        'enrolled_at',
        'completed_at',
        'dropped_at',
        'drop_reason',
    ];
    protected $casts = [
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'dropped_at' => 'datetime',
    ];

    // Relationships
    public function mentorshipClass(): BelongsTo {
        return $this->belongsTo(MentorshipClass::class, 'mentorship_class_id');
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function sessionAttendance(): HasMany {
        return $this->hasMany(SessionAttendance::class, 'class_participant_id');
    }

    public function moduleProgress(): HasMany {
        return $this->hasMany(MenteeModuleProgress::class, 'class_participant_id');
    }

    public function assessmentResults(): HasMany {
        return $this->hasMany(ModuleAssessmentResult::class, 'class_participant_id');
    }

    // Computed Attributes
    public function getAttendanceRateAttribute(): float {
        $totalSessions = $this->mentorshipClass
                ->classModules()
                ->withCount('sessions')
                ->get()
                ->sum('sessions_count');

        if ($totalSessions === 0) {
            return 0;
        }

        $attendedSessions = $this->sessionAttendance()
                ->where('status', 'present')
                ->count();

        return round(($attendedSessions / $totalSessions) * 100, 1);
    }

    public function getSessionsAttendedAttribute(): int {
        return $this->sessionAttendance()->where('status', 'present')->count();
    }

    public function getTotalSessionsAttribute(): int {
        return $this->mentorshipClass
                        ->classModules()
                        ->withCount('sessions')
                        ->get()
                        ->sum('sessions_count');
    }

    public function getIsActiveAttribute(): bool {
        return in_array($this->status, ['enrolled', 'active']);
    }

    // Query Scopes
    public function scopeActive($query) {
        return $query->whereIn('status', ['enrolled', 'active']);
    }

    public function scopeCompleted($query) {
        return $query->where('status', 'completed');
    }

    public function scopeDropped($query) {
        return $query->where('status', 'dropped');
    }

    public function scopeByClass($query, int $classId) {
        return $query->where('mentorship_class_id', $classId);
    }

    // Helper Methods
    public function markActive(): bool {
        return $this->update(['status' => 'active']);
    }

    public function markCompleted(): bool {
        return $this->update([
                    'status' => 'completed',
                    'completed_at' => now(),
        ]);
    }

    public function drop(string $reason = null): bool {
        return $this->update([
                    'status' => 'dropped',
                    'dropped_at' => now(),
                    'drop_reason' => $reason,
        ]);
    }

    public function hasAttendedSession(int $sessionId): bool {
        return $this->sessionAttendance()
                        ->where('session_id', $sessionId)
                        ->where('status', 'present')
                        ->exists();
    }
}
