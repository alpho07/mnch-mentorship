<?php
// Command: php artisan make:model SerialNumber

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class SerialNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'serial_number',
        'tag_number',
        'status',
        'current_location_id',
        'current_location_type',
        'assigned_to_user_id',
        'latitude',
        'longitude',
        'last_tracked_at',
        'acquisition_date',
        'warranty_expiry_date',
        'condition',
        'notes',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'last_tracked_at' => 'datetime',
        'acquisition_date' => 'date',
        'warranty_expiry_date' => 'date',
    ];

    // Relationships
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'current_location_id');
    }

    public function trackingHistory(): HasMany
    {
        return $this->hasMany(SerialNumberTracking::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    // Query Scopes
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    public function scopeAssigned(Builder $query): Builder
    {
        return $query->where('status', 'assigned');
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->where('status', 'in_transit');
    }

    public function scopeWarrantyExpiring(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('warranty_expiry_date')
                    ->where('warranty_expiry_date', '<=', now()->addDays($days));
    }

    // Computed Attributes
    public function getCurrentLocationNameAttribute(): string
    {
        if ($this->current_location_type === 'main_store') {
            return 'Main Store';
        }
        
        return $this->facility?->name ?? 'Unknown Location';
    }

    public function getIsWarrantyExpiredAttribute(): bool
    {
        return $this->warranty_expiry_date && $this->warranty_expiry_date->isPast();
    }

    public function getWarrantyStatusAttribute(): string
    {
        if (!$this->warranty_expiry_date) {
            return 'no_warranty';
        }

        if ($this->warranty_expiry_date->isPast()) {
            return 'expired';
        }

        if ($this->warranty_expiry_date->diffInDays() <= 30) {
            return 'expiring_soon';
        }

        return 'active';
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match($this->status) {
            'available' => 'success',
            'assigned' => 'info',
            'in_transit' => 'warning',
            'damaged' => 'danger',
            'lost' => 'danger',
            'retired' => 'gray',
            default => 'gray'
        };
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

    // Helper Methods
    public function updateLocation($locationId, $locationType, $latitude = null, $longitude = null): bool
    {
        $this->current_location_id = $locationId;
        $this->current_location_type = $locationType;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->last_tracked_at = now();

        return $this->save();
    }

    public function assignToUser(User $user): bool
    {
        $this->assigned_to_user_id = $user->id;
        $this->status = 'assigned';
        
        return $this->save();
    }

    public function unassign(): bool
    {
        $this->assigned_to_user_id = null;
        $this->status = 'available';
        
        return $this->save();
    }
}