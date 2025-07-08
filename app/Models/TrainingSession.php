<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_id',
        'module_id',
        'name',
        'session_time',
        'methodology_id',
    ];

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function methodology()
    {
        return $this->belongsTo(Methodology::class);
    }

    public function attendances()
    {
        return $this->hasMany(SessionAttendance::class);
    }

    public function objectives()
    {
        return $this->hasMany(Objective::class);
    }

    public function training_session_materials()
    {
        return $this->belongsToMany(InventoryItem::class, 'session_inventories', 'training_session_id', 'inventory_item_id');
    }
}
