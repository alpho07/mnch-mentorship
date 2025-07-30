<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'serial_number_id',
        'batch_id',
        'location_id',
        'location_type',
        'from_location_id',
        'from_location_type',
        'to_location_id',
        'to_location_type',
        'type',
        'quantity',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'user_id',
        'approved_by',
        'remarks',
        'transaction_date',
        'latitude',
        'longitude',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'transaction_date' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'metadata' => 'array',
    ];

    const TRANSACTION_TYPES = [
        'in' => 'Stock In',
        'out' => 'Stock Out',
        'transfer' => 'Transfer',
        'adjustment' => 'Adjustment',
        'request' => 'Request',
        'issue' => 'Issue',
        'return' => 'Return',
        'damage' => 'Damage',
        'loss' => 'Loss',
        'disposal' => 'Disposal',
    ];

    // Relationships
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(SerialNumber::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'location_id');
    }

    public function fromFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'from_location_id');
    }

    public function toFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'to_location_id');
    }

    // Polymorphic relationship for reference
    public function reference()
    {
        return $this->morphTo();
    }

    // Query Scopes
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByLocation(Builder $query, int $locationId, string $locationType = 'facility'): Builder
    {
        return $query->where('location_id', $locationId)
                    ->where('location_type', $locationType);
    }

    public function scopeByDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('transaction_date', [$from, $to]);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeStockIn(Builder $query): Builder
    {
        return $query->whereIn('type', ['in', 'transfer', 'return']);
    }

    public function scopeStockOut(Builder $query): Builder
    {
        return $query->whereIn('type', ['out', 'transfer', 'issue', 'damage', 'loss']);
    }

    public function scopeTransfers(Builder $query): Builder
    {
        return $query->where('type', 'transfer');
    }

    public function scopeAdjustments(Builder $query): Builder
    {
        return $query->where('type', 'adjustment');
    }

    // Computed Attributes
    public function getTransactionTypeNameAttribute(): string
    {
        return self::TRANSACTION_TYPES[$this->type] ?? 'Unknown';
    }

    public function getLocationNameAttribute(): string
    {
        if ($this->location_type === 'main_store') {
            return 'Main Store';
        }

        return $this->facility?->name ?? 'Unknown Location';
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

    public function getIsTransferAttribute(): bool
    {
        return $this->type === 'transfer' && $this->from_location_id && $this->to_location_id;
    }

    public function getTransactionDescriptionAttribute(): string
    {
        $description = $this->transaction_type_name;

        if ($this->is_transfer) {
            $description .= " from {$this->from_location_name} to {$this->to_location_name}";
        } else {
            $description .= " at {$this->location_name}";
        }

        return $description;
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

    public function getStatusColorAttribute(): string
    {
        return match($this->type) {
            'in', 'return' => 'success',
            'out', 'issue' => 'info',
            'transfer' => 'warning',
            'damage', 'loss' => 'danger',
            'adjustment' => 'gray',
            default => 'gray'
        };
    }

    // Helper Methods
    public function isStockIncrease(): bool
    {
        return in_array($this->type, ['in', 'return']) ||
               ($this->type === 'transfer' && $this->to_location_id);
    }

    public function isStockDecrease(): bool
    {
        return in_array($this->type, ['out', 'issue', 'damage', 'loss', 'disposal']) ||
               ($this->type === 'transfer' && $this->from_location_id);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (!$transaction->user_id) {
                $transaction->user_id = auth()->id();
            }

            if (!$transaction->transaction_date) {
                $transaction->transaction_date = now();
            }

            if (!$transaction->total_cost && $transaction->unit_cost && $transaction->quantity) {
                $transaction->total_cost = $transaction->unit_cost * $transaction->quantity;
            }
        });
    }
}
