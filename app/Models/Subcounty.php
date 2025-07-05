<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subcounty extends Model
{
    protected $fillable = ['name', 'county_id'];


    public function county()
    {
        return $this->belongsTo(County::class);
    }

    public function facilities()
    {
        return $this->hasMany(Facility::class);
    }
}
