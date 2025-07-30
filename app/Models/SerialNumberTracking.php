<?php
// Command: php artisan make:model SerialNumberTracking

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerialNumberTracking extends Model
{
    use HasFactory;

    protected $fillable = [
        'serial_number_id',
        'action',
        'from_location_id',
        'from_location_type',
        'to_location_id',
        'to_location_type',
        'from_user_id',
        'to_user_id',
        'tracked_by',
        'latitude',
        'longitude',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'metadata' => 'array',
    ];

    // Relationships
    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(SerialNumber::class);
    }

    public function fromFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'from_location_id');
    }

    public function toFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'to_location_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function trackedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tracked_by');
    }

    // Computed Attributes
    public function getActionDescriptionAttribute(): string
    {
        return match($this->action) {
            'created' => 'Item created and registered',
            'moved' => "Moved from {$this->from_location_name} to {$this->to_location_name}",
            'assigned' => "Assigned to {$this->toUser?->full_name}",
            'unassigned' => "Unassigned from {$this->fromUser?->full_name}",
            'damaged' => 'Marked as damaged',
            'repaired' => 'Repaired and returned to service',
            'retired' => 'Retired from service',
            default => 'Unknown action'
        };
    }

    public function getFromLocationNameAttribute(): string
    {
        if ($this->from_location_type === 'main_store') {
            return 'Main Store';
        }
        
        return $this->fromFacility?->name ?? 'Unknown Location';
    }

    public function getToLocationNameAttribute(): string
    {
        if ($this->to_location_type === 'main_store') {
            return 'Main Store';
        }
        
        return $this->toFacility?->name ?? 'Unknown Location';
    }

    public function getCoordinatesAttribute(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
            ];
        }

        return null;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tracking) {
            if (!$tracking->tracked_by) {
                $tracking->tracked_by = auth()->id();
            }
        });
    }
}