<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;

class StockRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_number',
        'requesting_facility_id',
        'supplying_facility_id',
        'requested_by',
        'approved_by',
        'priority',
        'status',
        'request_date',
        'required_by_date',
        'approved_date',
        'fulfilled_date',
        'total_estimated_cost',
        'notes',
        'justification',
        'metadata',
    ];

    protected $casts = [
        'request_date' => 'datetime',
        'required_by_date' => 'datetime',
        'approved_date' => 'datetime',
        'fulfilled_date' => 'datetime',
        'total_estimated_cost' => 'decimal:2',
        'metadata' => 'array',
    ];

    const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'partially_fulfilled' => 'Partially Fulfilled',
        'fulfilled' => 'Fulfilled',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    const PRIORITIES = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
        'emergency' => 'Emergency',
    ];

    // Relationships
    public function requestingFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'requesting_facility_id');
    }

    public function supplyingFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'supplying_facility_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockRequestItem::class);
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

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeByFacility(Builder $query, int $facilityId): Builder
    {
        return $query->where('requesting_facility_id', $facilityId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['submitted', 'approved', 'partially_fulfilled']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('required_by_date', '<', now())
                    ->whereNotIn('status', ['fulfilled', 'cancelled', 'rejected']);
    }

    public function scopeUrgent(Builder $query): Builder
    {
        return $query->whereIn('priority', ['urgent', 'emergency']);
    }

    // Computed Attributes
    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? 'Unknown';
    }

    public function getPriorityNameAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? 'Unknown';
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'submitted' => 'warning',
            'approved' => 'info',
            'partially_fulfilled' => 'warning',
            'fulfilled' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'gray',
            default => 'gray'
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'low' => 'gray',
            'normal' => 'info',
            'high' => 'warning',
            'urgent' => 'danger',
            'emergency' => 'danger',
            default => 'gray'
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->required_by_date &&
               $this->required_by_date->isPast() &&
               !in_array($this->status, ['fulfilled', 'cancelled', 'rejected']);
    }

    public function getCanBeApprovedAttribute(): bool
    {
        return $this->status === 'submitted';
    }

    public function getCanBeFulfilledAttribute(): bool
    {
        return in_array($this->status, ['approved', 'partially_fulfilled']);
    }

    public function getTotalItemsAttribute(): int
    {
        return $this->items()->count();
    }

    public function getTotalQuantityRequestedAttribute(): int
    {
        return $this->items()->sum('quantity_requested');
    }

    public function getTotalQuantityApprovedAttribute(): int
    {
        return $this->items()->sum('quantity_approved');
    }

    public function getTotalQuantityFulfilledAttribute(): int
    {
        return $this->items()->sum('quantity_fulfilled');
    }

    public function getFulfillmentPercentageAttribute(): float
    {
        $approved = $this->total_quantity_approved;
        $fulfilled = $this->total_quantity_fulfilled;

        if ($approved <= 0) {
            return 0;
        }

        return round(($fulfilled / $approved) * 100, 2);
    }

    public function getIsPartiallyFulfilledAttribute(): bool
    {
        $fulfilled = $this->total_quantity_fulfilled;
        $approved = $this->total_quantity_approved;

        return $fulfilled > 0 && $fulfilled < $approved;
    }

    public function getIsFullyFulfilledAttribute(): bool
    {
        return $this->total_quantity_fulfilled >= $this->total_quantity_approved;
    }

    // Helper Methods
    public function approve(User $approver, array $approvals = []): bool
    {
        if (!$this->can_be_approved) {
            return false;
        }

        $this->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_date' => now(),
        ]);

        // Update individual item approvals
        foreach ($approvals as $itemId => $approvedQuantity) {
            $this->items()->where('id', $itemId)->update([
                'quantity_approved' => $approvedQuantity,
            ]);
        }

        return true;
    }

    public function reject(User $approver, string $reason = null): bool
    {
        if (!$this->can_be_approved) {
            return false;
        }

        $this->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'approved_date' => now(),
            'notes' => $reason,
        ]);

        return true;
    }

    public function fulfill(array $fulfillments, User $user = null): bool
    {
        if (!$this->can_be_fulfilled) {
            return false;
        }

        $totalFulfilled = 0;
        $totalApproved = $this->total_quantity_approved;

        foreach ($fulfillments as $itemId => $quantity) {
            $item = $this->items()->find($itemId);
            if ($item) {
                $item->update([
                    'quantity_fulfilled' => $item->quantity_fulfilled + $quantity,
                ]);
                $totalFulfilled += $quantity;

                // Create inventory transaction
                InventoryTransaction::create([
                    'inventory_item_id' => $item->inventory_item_id,
                    'from_location_id' => $this->supplying_facility_id ?: 1, // Main store
                    'from_location_type' => $this->supplying_facility_id ? 'facility' : 'main_store',
                    'to_location_id' => $this->requesting_facility_id,
                    'to_location_type' => 'facility',
                    'type' => 'transfer',
                    'quantity' => $quantity,
                    'reference_type' => StockRequest::class,
                    'reference_id' => $this->id,
                    'user_id' => $user?->id ?? auth()->id(),
                    'remarks' => "Stock request fulfillment - {$this->request_number}",
                ]);
            }
        }

        // Update request status
        if ($this->is_fully_fulfilled) {
            $this->update([
                'status' => 'fulfilled',
                'fulfilled_date' => now(),
            ]);
        } else {
            $this->update(['status' => 'partially_fulfilled']);
        }

        return true;
    }

    public function calculateEstimatedCost(): float
    {
        return $this->items->sum(function ($item) {
            return $item->quantity_requested * $item->inventoryItem->cost_price;
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($request) {
            if (!$request->request_number) {
                $request->request_number = $request->generateRequestNumber();
            }

            if (!$request->requested_by) {
                $request->requested_by = auth()->id();
            }

            if (!$request->request_date) {
                $request->request_date = now();
            }
        });

        static::created(function ($request) {
            $request->update([
                'total_estimated_cost' => $request->calculateEstimatedCost(),
            ]);
        });
    }

    private function generateRequestNumber(): string
    {
        $prefix = 'REQ';
        $year = date('Y');
        $month = date('m');
        $sequence = str_pad(static::whereYear('created_at', $year)->count() + 1, 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}{$month}-{$sequence}";
    }
}
