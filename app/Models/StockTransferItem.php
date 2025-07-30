<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'inventory_item_id',
        'quantity',
        'quantity_received',
        'unit_cost',
        'total_cost',
        'batch_id',
        'serial_numbers',
        'condition_notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'quantity_received' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'serial_numbers' => 'array',
    ];

    // Relationships
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'batch_id');
    }

    // Computed Attributes
    public function getQuantityPendingAttribute(): int
    {
        return $this->quantity - $this->quantity_received;
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->quantity_received >= $this->quantity;
    }

    public function getIsPartiallyReceivedAttribute(): bool
    {
        return $this->quantity_received > 0 && $this->quantity_received < $this->quantity;
    }

    public function getReceiptPercentageAttribute(): float
    {
        if ($this->quantity <= 0) {
            return 0;
        }

        return round(($this->quantity_received / $this->quantity) * 100, 2);
    }

    public function getStatusAttribute(): string
    {
        if ($this->quantity_received == 0) {
            return 'pending';
        } elseif ($this->is_fully_received) {
            return 'received';
        } else {
            return 'partially_received';
        }
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'partially_received' => 'info',
            'received' => 'success',
            default => 'gray'
        };
    }

    public function getVarianceQuantityAttribute(): int
    {
        return $this->quantity_received - $this->quantity;
    }

    public function getHasVarianceAttribute(): bool
    {
        return $this->variance_quantity !== 0;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (!$item->unit_cost && $item->inventoryItem) {
                $item->unit_cost = $item->inventoryItem->cost_price;
            }

            if ($item->unit_cost && $item->quantity) {
                $item->total_cost = $item->unit_cost * $item->quantity;
            }
        });

        static::updating(function ($item) {
            if ($item->isDirty('quantity_received') && $item->unit_cost) {
                $item->total_cost = $item->unit_cost * $item->quantity_received;
            }
        });
    }
}
