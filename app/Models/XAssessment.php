<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assessment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'assessment_number',
        'facility_id',
        'assessment_type_id',
        'assessor_id',
        'assessor_name',
        'assessor_designation',
        'assessment_date',
        'scheduled_date',
        'status',
        'total_score',
        'max_score',
        'percentage',
        'grade',
        'purpose',
        'observations',
        'recommendations',
        'metadata',
        'started_at',
        'completed_at',
        'submitted_at',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'assessment_date' => 'date',
        'scheduled_date' => 'date',
        'total_score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'percentage' => 'decimal:2',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected $with = ['facility', 'assessmentType'];

    // Relationships
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function assessmentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AssessmentResponse::class);
    }

    // Scopes
    public function scopeByFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopeByType($query, int $typeId)
    {
        return $query->where('assessment_type_id', $typeId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    // Helper Methods
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['scheduled', 'in_progress']);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsSubmitted(): void
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    // Generate assessment number
    public static function generateAssessmentNumber(): string
    {
        $year = now()->year;
        $lastAssessment = static::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastAssessment 
            ? ((int) substr($lastAssessment->assessment_number, -4)) + 1 
            : 1;

        return sprintf('ASS-%d-%04d', $year, $nextNumber);
    }
}