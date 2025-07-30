<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class InventoryItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'sku',
        'barcode',
        'category_id',
        'supplier_id',
        'unit_of_measure',
        'unit_price',
        'cost_price',
        'minimum_stock_level',
        'maximum_stock_level',
        'reorder_point',
        'reorder_quantity',
        'is_trackable',
        'is_serialized',
        'requires_batch_tracking',
        'shelf_life_days',
        'weight',
        'dimensions',
        'storage_requirements',
        'status',
        'image_path',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'minimum_stock_level' => 'integer',
        'maximum_stock_level' => 'integer',
        'reorder_point' => 'integer',
        'reorder_quantity' => 'integer',
        'is_trackable' => 'boolean',
        'is_serialized' => 'boolean',
        'requires_batch_tracking' => 'boolean',
        'shelf_life_days' => 'integer',
        'weight' => 'decimal:3',
        'dimensions' => 'array',
        'storage_requirements' => 'array',
    ];

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ItemBatch::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function stockRequests(): HasMany
    {
        return $this->hasMany(StockRequest::class);
    }

    public function stockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class);
    }

    public function trainingLinks(): HasMany
    {
        return $this->hasMany(ItemTrainingLink::class);
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'facility_inventory_items')
            ->withPivot(['minimum_level', 'maximum_level', 'current_stock'])
            ->withTimestamps();
    }

    // Query Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeTrackable(Builder $query): Builder
    {
        return $query->where('is_trackable', true);
    }

    public function scopeSerialized(Builder $query): Builder
    {
        return $query->where('is_serialized', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereHas('stockLevels', function ($q) {
            $q->whereRaw('current_stock <= minimum_stock_level');
        });
    }

    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeBySupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }

    // Computed Attributes
    public function getTotalStockAttribute(): int
    {
        return $this->stockLevels()->sum('current_stock');
    }

    public function getTotalValueAttribute(): float
    {
        return $this->total_stock * $this->unit_price;
    }

    public function getAvailableStockAttribute(): int
    {
        return $this->stockLevels()->sum('current_stock') - $this->stockLevels()->sum('reserved_stock');
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->total_stock <= $this->reorder_point;
    }

    public function getStockStatusAttribute(): string
    {
        $totalStock = $this->total_stock;

        if ($totalStock <= 0) {
            return 'out_of_stock';
        } elseif ($totalStock <= $this->reorder_point) {
            return 'low_stock';
        } elseif ($totalStock >= $this->maximum_stock_level) {
            return 'overstock';
        }

        return 'normal';
    }

    public function getStockStatusColorAttribute(): string
    {
        return match($this->stock_status) {
            'out_of_stock' => 'danger',
            'low_stock' => 'warning',
            'overstock' => 'info',
            'normal' => 'success',
            default => 'gray'
        };
    }

    public function getSerializedCountAttribute(): int
    {
        return $this->serialNumbers()->count();
    }

    public function getAvailableSerializedItemsAttribute(): int
    {
        return $this->serialNumbers()->where('status', 'available')->count();
    }

    // Helper Methods
    public function getStockAtLocation(int $locationId, string $locationType = 'facility'): int
    {
        return $this->stockLevels()
            ->where('location_id', $locationId)
            ->where('location_type', $locationType)
            ->sum('current_stock');
    }

    public function hasStock(): bool
    {
        return $this->total_stock > 0;
    }

    public function canFulfillQuantity(int $quantity, int $locationId = null): bool
    {
        if ($locationId) {
            return $this->getStockAtLocation($locationId) >= $quantity;
        }

        return $this->available_stock >= $quantity;
    }

    public function requiresReorder(): bool
    {
        return $this->total_stock <= $this->reorder_point;
    }

    public function suggestedReorderQuantity(): int
    {
        $deficit = $this->maximum_stock_level - $this->total_stock;
        return max($this->reorder_quantity, $deficit);
    }

    // Stock Management Methods
    public function adjustStock(int $locationId, int $quantity, string $reason, User $user = null): bool
    {
        $stockLevel = $this->stockLevels()
            ->where('location_id', $locationId)
            ->first();

        if (!$stockLevel) {
            $stockLevel = $this->stockLevels()->create([
                'location_id' => $locationId,
                'location_type' => 'facility',
                'current_stock' => 0,
                'reserved_stock' => 0,
            ]);
        }

        $newStock = $stockLevel->current_stock + $quantity;

        if ($newStock < 0) {
            return false;
        }

        $stockLevel->update([
            'current_stock' => $newStock,
            'last_updated_by' => $user?->id ?? auth()->id(),
        ]);

        // Record transaction
        $this->transactions()->create([
            'location_id' => $locationId,
            'location_type' => 'facility',
            'type' => $quantity > 0 ? 'in' : 'out',
            'quantity' => abs($quantity),
            'user_id' => $user?->id ?? auth()->id(),
            'remarks' => $reason,
            'transaction_date' => now(),
        ]);

        return true;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (!$item->sku) {
                $item->sku = $item->generateSku();
            }
        });
    }

    private function generateSku(): string
    {
        $prefix = $this->category?->code ?? 'GEN';
        $number = str_pad(static::count() + 1, 4, '0', STR_PAD_LEFT);
        return $prefix . '-' . $number;
    }
}
