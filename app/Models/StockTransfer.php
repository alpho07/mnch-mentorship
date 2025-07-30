<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;

class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_number',
        'from_facility_id',
        'to_facility_id',
        'initiated_by',
        'approved_by',
        'received_by',
        'status',
        'transfer_date',
        'expected_arrival_date',
        'actual_arrival_date',
        'total_items',
        'total_value',
        'transport_method',
        'tracking_number',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'transfer_date' => 'datetime',
        'expected_arrival_date' => 'datetime',
        'actual_arrival_date' => 'datetime',
        'total_items' => 'integer',
        'total_value' => 'decimal:2',
        'metadata' => 'array',
    ];

    const STATUSES = [
        'draft' => 'Draft',
        'pending' => 'Pending Approval',
        'approved' => 'Approved',
        'in_transit' => 'In Transit',
        'delivered' => 'Delivered',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ];

    const TRANSPORT_METHODS = [
        'road' => 'Road Transport',
        'air' => 'Air Transport',
        'rail' => 'Rail Transport',
        'courier' => 'Courier Service',
        'hand_delivery' => 'Hand Delivery',
        'other' => 'Other',
    ];

    // Relationships
    public function fromFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'from_facility_id');
    }

    public function toFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'to_facility_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(InventoryTransaction::class, 'reference');
    }

    // Query Scopes
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByFromFacility(Builder $query, int $facilityId): Builder
    {
        return $query->where('from_facility_id', $facilityId);
    }

    public function scopeByToFacility(Builder $query, int $facilityId): Builder
    {
        return $query->where('to_facility_id', $facilityId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'approved', 'in_transit', 'delivered']);
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->where('status', 'in_transit');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('expected_arrival_date', '<', now())
                    ->whereNotIn('status', ['received', 'cancelled']);
    }

    // Computed Attributes
    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? 'Unknown';
    }

    public function getTransportMethodNameAttribute(): string
    {
        return self::TRANSPORT_METHODS[$this->transport_method] ?? 'Unknown';
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'pending' => 'warning',
            'approved' => 'info',
            'in_transit' => 'warning',
            'delivered' => 'info',
            'received' => 'success',
            'cancelled' => 'danger',
            default => 'gray'
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->expected_arrival_date &&
               $this->expected_arrival_date->isPast() &&
               !in_array($this->status, ['received', 'cancelled']);
    }

    public function getCanBeApprovedAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getCanBeShippedAttribute(): bool
    {
        return $this->status === 'approved';
    }

    public function getCanBeReceivedAttribute(): bool
    {
        return in_array($this->status, ['in_transit', 'delivered']);
    }

    public function getDaysInTransitAttribute(): ?int
    {
        if ($this->status === 'in_transit' && $this->transfer_date) {
            return $this->transfer_date->diffInDays(now());
        }

        if ($this->actual_arrival_date && $this->transfer_date) {
            return $this->transfer_date->diffInDays($this->actual_arrival_date);
        }

        return null;
    }

    public function getTotalItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    public function getTotalQuantityAttribute(): int
    {
        return $this->items()->sum('quantity');
    }

    public function getReceivedQuantityAttribute(): int
    {
        return $this->items()->sum('quantity_received');
    }

    public function getDeliveryPerformanceAttribute(): string
    {
        if (!$this->expected_arrival_date || !$this->actual_arrival_date) {
            return 'pending';
        }

        if ($this->actual_arrival_date->lte($this->expected_arrival_date)) {
            return 'on_time';
        } else {
            return 'delayed';
        }
    }

    // Helper Methods
    public function approve(User $approver): bool
    {
        if (!$this->can_be_approved) {
            return false;
        }

        $this->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
        ]);

        return true;
    }

    public function ship(User $user = null): bool
    {
        if (!$this->can_be_shipped) {
            return false;
        }

        // Create outbound transactions for source facility
        foreach ($this->items as $item) {
            InventoryTransaction::create([
                'inventory_item_id' => $item->inventory_item_id,
                'from_location_id' => $this->from_facility_id,
                'from_location_type' => 'facility',
                'to_location_id' => $this->to_facility_id,
                'to_location_type' => 'facility',
                'type' => 'transfer',
                'quantity' => $item->quantity,
                'reference_type' => StockTransfer::class,
                'reference_id' => $this->id,
                'user_id' => $user?->id ?? auth()->id(),
                'remarks' => "Stock transfer - {$this->transfer_number}",
            ]);

            // Reduce stock at source facility
            $item->inventoryItem->adjustStock(
                $this->from_facility_id,
                -$item->quantity,
                "Transfer to {$this->toFacility->name}",
                $user
            );
        }

        $this->update([
            'status' => 'in_transit',
            'transfer_date' => now(),
        ]);

        return true;
    }

    public function receive(array $receivedQuantities, User $receiver = null): bool
    {
        if (!$this->can_be_received) {
            return false;
        }

        foreach ($receivedQuantities as $itemId => $quantity) {
            $item = $this->items()->find($itemId);
            if ($item) {
                $item->update(['quantity_received' => $quantity]);

                // Add stock at destination facility
                $item->inventoryItem->adjustStock(
                    $this->to_facility_id,
                    $quantity,
                    "Transfer from {$this->fromFacility->name}",
                    $receiver
                );
            }
        }

        $this->update([
            'status' => 'received',
            'received_by' => $receiver?->id ?? auth()->id(),
            'actual_arrival_date' => now(),
        ]);

        return true;
    }

    public function cancel(string $reason = null): bool
    {
        if (in_array($this->status, ['received', 'cancelled'])) {
            return false;
        }

        $this->update([
            'status' => 'cancelled',
            'notes' => $reason,
        ]);

        return true;
    }

    public function calculateTotalValue(): float
    {
        return $this->items->sum(function ($item) {
            return $item->quantity * $item->inventoryItem->cost_price;
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transfer) {
            if (!$transfer->transfer_number) {
                $transfer->transfer_number = $transfer->generateTransferNumber();
            }

            if (!$transfer->initiated_by) {
                $transfer->initiated_by = auth()->id();
            }
        });

        static::created(function ($transfer) {
            $transfer->update([
                'total_value' => $transfer->calculateTotalValue(),
                'total_items' => $transfer->items()->count(),
            ]);
        });
    }

    private function generateTransferNumber(): string
    {
        $prefix = 'TRF';
        $year = date('Y');
        $month = date('m');
        $sequence = str_pad(static::whereYear('created_at', $year)->count() + 1, 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}{$month}-{$sequence}";
    }
}
