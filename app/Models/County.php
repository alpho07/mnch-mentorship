<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class County extends Model
{
    protected $fillable = ['name'];


    public function subcounties()
    {
        return $this->hasMany(Subcounty::class);
    }
}
