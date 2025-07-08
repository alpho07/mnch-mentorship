<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'serial_number',
        'name',
        'description',
        'category_id',
        'unit_of_measure',
        'supplier_id',
        'image_url',
        'price',
        'status',
        'current_location_id',
        'latitude',
        'longitude',
        'last_tracked_at',
        'is_borrowable'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function batches()
    {
        return $this->hasMany(ItemBatch::class);
    }

    public function stockBalances()
    {
        return $this->hasMany(StockBalance::class);
    }

    public function transactions()
    {
        return $this->hasMany(ItemTransaction::class);
    }

    public function trainingLinks()
    {
        return $this->hasMany(ItemTrainingLink::class);
    }
}
