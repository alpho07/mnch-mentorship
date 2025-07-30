<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_request_id',
        'inventory_item_id',
        'quantity_requested',
        'quantity_approved',
        'quantity_fulfilled',
        'unit_cost',
        'total_cost',
        'urgency_level',
        'justification',
        'notes',
    ];

    protected $casts = [
        'quantity_requested' => 'integer',
        'quantity_approved' => 'integer',
        'quantity_fulfilled' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    // Relationships
    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    // Computed Attributes
    public function getQuantityPendingAttribute(): int
    {
        return $this->quantity_approved - $this->quantity_fulfilled;
    }

    public function getIsFullyFulfilledAttribute(): bool
    {
        return $this->quantity_fulfilled >= $this->quantity_approved;
    }

    public function getIsPartiallyFulfilledAttribute(): bool
    {
        return $this->quantity_fulfilled > 0 && $this->quantity_fulfilled < $this->quantity_approved;
    }

    public function getFulfillmentPercentageAttribute(): float
    {
        if ($this->quantity_approved <= 0) {
            return 0;
        }

        return round(($this->quantity_fulfilled / $this->quantity_approved) * 100, 2);
    }

    public function getStatusAttribute(): string
    {
        if ($this->quantity_approved == 0) {
            return 'pending_approval';
        } elseif ($this->quantity_fulfilled == 0) {
            return 'approved';
        } elseif ($this->is_fully_fulfilled) {
            return 'fulfilled';
        } else {
            return 'partially_fulfilled';
        }
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending_approval' => 'warning',
            'approved' => 'info',
            'partially_fulfilled' => 'warning',
            'fulfilled' => 'success',
            default => 'gray'
        };
    }

    public function getEstimatedCostAttribute(): float
    {
        return $this->quantity_requested * $this->inventoryItem->cost_price;
    }

    public function getApprovedCostAttribute(): float
    {
        return $this->quantity_approved * $this->inventoryItem->cost_price;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (!$item->unit_cost && $item->inventoryItem) {
                $item->unit_cost = $item->inventoryItem->cost_price;
            }

            if ($item->unit_cost && $item->quantity_requested) {
                $item->total_cost = $item->unit_cost * $item->quantity_requested;
            }
        });

        static::updating(function ($item) {
            if ($item->isDirty('quantity_approved') && $item->unit_cost) {
                $item->total_cost = $item->unit_cost * $item->quantity_approved;
            }
        });
    }
}
