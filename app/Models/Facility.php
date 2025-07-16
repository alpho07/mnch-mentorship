<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    protected $fillable = ['name', 'subcounty_id', 'facility_type_id', 'is_hub', 'hub_id', 'mfl_code', 'lat', 'long'];

    public function subcounty()
    {
        return $this->belongsTo(Subcounty::class);
    }

    public function facilityType()
    {
        return $this->belongsTo(FacilityType::class);
    }

    public function spokes()
    {
        return $this->hasMany(Facility::class, 'hub_id');
    }

    public function hub()
    {
        return $this->belongsTo(Facility::class, 'hub_id');
    }

    public function trainings()
    {
        return $this->hasMany(Training::class);
    }
}
