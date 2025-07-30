<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'location_id',
        'location_type',
        'current_stock',
        'reserved_stock',
        'projected_stock',
        'last_updated_by',
        'last_stock_take_date',
        'notes',
    ];

    protected $casts = [
        'current_stock' => 'integer',
        'reserved_stock' => 'integer',
        'projected_stock' => 'integer',
        'last_stock_take_date' => 'datetime',
    ];

    // Relationships
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'location_id');
    }

    // Computed Attributes
    public function getAvailableStockAttribute(): int
    {
        return max(0, $this->current_stock - $this->reserved_stock);
    }

    public function getLocationNameAttribute(): string
    {
        if ($this->location_type === 'main_store') {
            return 'Main Store';
        }
        
        return $this->facility?->name ?? 'Unknown Location';
    }

    public function getStockValueAttribute(): float
    {
        return $this->current_stock * $this->inventoryItem->unit_price;
    }

    // Helper Methods
    public function adjustStock(int $quantity, string $reason = null): bool
    {
        $this->current_stock += $quantity;
        $this->last_updated_by = auth()->id();
        if ($reason) {
            $this->notes = $reason;
        }
        
        return $this->save();
    }

    public function reserveStock(int $quantity): bool
    {
        if ($this->available_stock >= $quantity) {
            $this->reserved_stock += $quantity;
            return $this->save();
        }
        
        return false;
    }
}