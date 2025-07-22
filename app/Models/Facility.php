<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'uid',
        'subcounty_id',
        'facility_type_id',
        'is_hub',
        'hub_id',
        'mfl_code',
        'lat',
        'long',
    ];

    protected $casts = [
        'is_hub' => 'boolean',
        'lat' => 'decimal:7',
        'long' => 'decimal:7',
    ];

    protected $with = ['subcounty', 'facilityType'];

    // Relationships
    public function subcounty(): BelongsTo
    {
        return $this->belongsTo(Subcounty::class);
    }

    public function facilityType(): BelongsTo
    {
        return $this->belongsTo($this);
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'hub_id');
    }

    public function spokes(): HasMany
    {
        return $this->hasMany(Facility::class, 'hub_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    public function scopedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'facility_user');
    }

    // Query Scopes
    public function scopeBySubcounty($query, int $subcountyId)
    {
        return $query->where('subcounty_id', $subcountyId);
    }

    public function scopeByType($query, int $facilityTypeId)
    {
        return $query->where('facility_type_id', $facilityTypeId);
    }

    public function scopeHubs($query)
    {
        return $query->where('is_hub', true);
    }

    public function scopeSpokes($query)
    {
        return $query->where('is_hub', false)->whereNotNull('hub_id');
    }

    public function scopeStandalone($query)
    {
        return $query->where('is_hub', false)->whereNull('hub_id');
    }

    public function scopeWithinRadius($query, float $lat, float $lng, float $radius)
    {
        return $query->whereNotNull('lat')
            ->whereNotNull('long')
            ->selectRaw("*, (
                        6371 * acos(
                            cos(radians(?)) *
                            cos(radians(lat)) *
                            cos(radians(long) - radians(?)) +
                            sin(radians(?)) *
                            sin(radians(lat))
                        )
                    ) AS distance", [$lat, $lng, $lat])
            ->having('distance', '<', $radius)
            ->orderBy('distance');
    }

    // Computed Attributes
    public function getSpokeCountAttribute(): int
    {
        return $this->spokes()->count();
    }

    public function getTrainingCountAttribute(): int
    {
        return $this->trainings()->count();
    }

    public function getCoordinatesAttribute(): ?array
    {
        if ($this->lat && $this->long) {
            return [
                'latitude' => (float) $this->lat,
                'longitude' => (float) $this->long,
            ];
        }

        return null;
    }

    public function reportTemplates(): BelongsToMany
    {
        return $this->belongsToMany(ReportTemplate::class, 'facility_report_templates')
            ->withPivot(['start_date', 'end_date'])
            ->withTimestamps();
    }

    public function monthlyReports(): HasMany
    {
        return $this->hasMany(MonthlyReport::class);
    }

    public function getActiveReportTemplatesAttribute()
    {
        return $this->reportTemplates()
            ->wherePivot('start_date', '<=', now())
            ->where(function ($query) {
                $query->wherePivot('end_date', '>=', now())
                    ->orWherePivot('end_date', null);
            })
            ->where('is_active', true)
            ->get();
    }
}
