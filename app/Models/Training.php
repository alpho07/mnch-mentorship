<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Training extends Model
{
    use HasFactory, SoftDeletes;


    protected $fillable = [
        'title',
        'description',
        'type', // 'global_training' or 'facility_mentorship'
        'status',
        'identifier',
        'program_id', // Keep for backward compatibility
        'facility_id', // nullable for global trainings
        'organizer_id',
        'mentor_id',
        'location',
        'start_date',
        'end_date',
        'registration_deadline',
        'max_participants',
        'target_audience',
        'completion_criteria',
        'materials_needed',
        'learning_outcomes',
        'prerequisites',
        'training_approaches', // Array of approaches
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'registration_deadline' => 'datetime',
        'materials_needed' => 'array',
        'learning_outcomes' => 'array',
        'prerequisites' => 'array',
        'training_approaches' => 'array',
    ];

    // Relationships
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'training_programs', 'training_id', 'program_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'training_departments');
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'training_modules');
    }

    public function methodologies(): BelongsToMany
    {
        return $this->belongsToMany(Methodology::class, 'training_methodologies');
    }

    public function targetFacilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'training_target_facilities', 'training_id', 'facility_id');
    }

    // Helper Methods
    public function isGlobalTraining(): bool
    {
        return $this->type === 'global_training';
    }

    public function isFacilityMentorship(): bool
    {
        return $this->type === 'facility_mentorship';
    }

    public function getCompletionRateAttribute(): float
    {
        $total = $this->participants()->count();
        if ($total === 0) return 0;

        $completed = $this->participants()
            ->where('completion_status', 'completed')
            ->count();

        return round(($completed / $total) * 100, 2);
    }

    public function getAverageScoreAttribute(): float
    {
        // Simple implementation for now
        return 0;
    }

    // Scopes
    public function scopeGlobalTrainings($query)
    {
        return $query->where('type', 'global_training');
    }

    public function scopeFacilityMentorships($query)
    {
        return $query->where('type', 'facility_mentorship');
    }

    public function scopeRegistrationOpen($query)
    {
        return $query->where('status', 'registration_open')
                    ->where('registration_deadline', '>=', now());
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now());
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Analytics methods
    public function getParticipantsByFacility(): Collection
    {
        return $this->participants()
            ->with('user.facility')
            ->get()
            ->groupBy('user.facility.name')
            ->map(fn ($participants) => $participants->count());
    }

    public function getParticipantsByCadre(): Collection
    {
        return $this->participants()
            ->with('user.cadre')
            ->get()
            ->groupBy('user.cadre.name')
            ->map(fn ($participants) => $participants->count());
    }

    public function getCompletionStats(): array
    {
        $total = $this->participants()->count();
        $completed = $this->participants()->where('completion_status', 'completed')->count();
        $inProgress = $this->participants()->where('completion_status', 'in_progress')->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
        ];
    }

    // Relationship assignment methods
    public function assignPrograms(array $programIds): void
    {
        $this->programs()->sync($programIds);
    }

    public function assignModules(array $moduleIds): void
    {
        $this->modules()->sync($moduleIds);
    }

    public function assignMethodologies(array $methodologyIds): void
    {
        $this->methodologies()->sync($methodologyIds);
    } 
}
