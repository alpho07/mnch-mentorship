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
        'is_central_store',
        'storage_capacity',
        'operating_hours',
        'mfl_code',
        'lat',
        'long',
    ];

    protected $casts = [
        'is_hub' => 'boolean',
        'is_central_store' => 'boolean',
        'operating_hours' => 'array',
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
        return $this->belongsTo(FacilityType::class);
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

    // Inventory Management Relationships
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockRequests(): HasMany
    {
        return $this->hasMany(StockRequest::class, 'requesting_facility_id');
    }

    public function centralStoreRequests(): HasMany
    {
        return $this->hasMany(StockRequest::class, 'central_store_id');
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_facility_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_facility_id');
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
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

    public function scopeCentralStores($query)
    {
        return $query->where('is_central_store', true);
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

    public function getTotalStockValueAttribute(): float
    {
        return $this->stockLevels()
            ->join('inventory_items', 'stock_levels.inventory_item_id', '=', 'inventory_items.id')
            ->selectRaw('SUM(stock_levels.current_stock * inventory_items.unit_price)')
            ->value('SUM(stock_levels.current_stock * inventory_items.unit_price)') ?? 0;
    }

    public function getLowStockItemsCountAttribute(): int
    {
        return $this->stockLevels()
            ->join('inventory_items', 'stock_levels.inventory_item_id', '=', 'inventory_items.id')
            ->whereColumn('stock_levels.current_stock', '<=', 'inventory_items.reorder_point')
            ->count();
    }

    public function getOutOfStockItemsCountAttribute(): int
    {
        return $this->stockLevels()
            ->where('current_stock', '<=', 0)
            ->count();
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

    // Inventory Management Methods
    public function getStockLevel(int $inventoryItemId): ?StockLevel
    {
        return $this->stockLevels()
            ->where('inventory_item_id', $inventoryItemId)
            ->first();
    }

    public function hasStock(int $inventoryItemId, int $quantity = 1): bool
    {
        $stockLevel = $this->getStockLevel($inventoryItemId);
        return $stockLevel && $stockLevel->available_stock >= $quantity;
    }

    public function getNearbyFacilities(float $radius = 50): \Illuminate\Database\Eloquent\Collection
    {
        if (!$this->lat || !$this->long) {
            return collect();
        }

        return static::withinRadius($this->lat, $this->long, $radius)
            ->where('id', '!=', $this->id)
            ->get();
    }

    public function canReceiveTransfersFrom(Facility $fromFacility): bool
    {
        // Business logic for transfer permissions
        // Same subcounty transfers are always allowed
        if ($this->subcounty_id === $fromFacility->subcounty_id) {
            return true;
        }

        // Hub to spoke transfers
        if ($fromFacility->is_hub && $this->hub_id === $fromFacility->id) {
            return true;
        }

        // Central store to any facility
        if ($fromFacility->is_central_store) {
            return true;
        }

        return false;
    }

     public function isCentralStore(): bool
    {
        return $this->is_central_store;
    }

    public function getCentralStoreForFacility(): ?Facility
    {
        if ($this->is_central_store) {
            return $this;
        }

        // Logic to find appropriate central store
        // Could be based on subcounty, county, or region
        return Facility::centralStores()
            ->where('subcounty_id', $this->subcounty_id)
            ->first() ?? 
            Facility::centralStores()
                ->whereHas('subcounty', fn($q) => $q->where('county_id', $this->subcounty->county_id))
                ->first();
    }

    public function getDistributionFacilities(): \Illuminate\Database\Eloquent\Collection
    {
        if (!$this->is_central_store) {
            return collect();
        }

        // Get facilities that this central store serves
        return Facility::where('is_central_store', false)
            ->where(function ($query) {
                $query->where('subcounty_id', $this->subcounty_id)
                      ->orWhereHas('subcounty', fn($q) => $q->where('county_id', $this->subcounty->county_id));
            })
            ->get();
    }

    public function getTotalStockAtCentralStore(): array
    {
        if (!$this->is_central_store) {
            return [];
        }

        return $this->stockLevels()
            ->with('inventoryItem')
            ->get()
            ->groupBy('inventoryItem.category.name')
            ->map(function ($items) {
                return [
                    'total_items' => $items->count(),
                    'total_quantity' => $items->sum('current_stock'),
                    'total_value' => $items->sum('stock_value'),
                    'available_quantity' => $items->sum('available_stock'),
                ];
            });
    }

    public function getPendingDistributions(): \Illuminate\Database\Eloquent\Collection
    {
        if (!$this->is_central_store) {
            return collect();
        }

        return StockRequest::where('central_store_id', $this->id)
            ->whereIn('status', ['approved', 'partially_approved'])
            ->with(['requestingFacility', 'items.inventoryItem'])
            ->get();
    }

    // Computed attributes for central stores
    public function getCentralStoreStockSummaryAttribute(): array
    {
        if (!$this->is_central_store) {
            return [];
        }

        $stockLevels = $this->stockLevels()->with('inventoryItem')->get();
        
        return [
            'total_items' => $stockLevels->count(),
            'total_quantity' => $stockLevels->sum('current_stock'),
            'total_value' => $stockLevels->sum('stock_value'),
            'available_quantity' => $stockLevels->sum('available_stock'),
            'reserved_quantity' => $stockLevels->sum('reserved_stock'),
            'low_stock_items' => $stockLevels->filter(fn($sl) => $sl->is_low_stock)->count(),
            'out_of_stock_items' => $stockLevels->where('current_stock', '<=', 0)->count(),
        ];
    }
}