<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    public function modules()
    {
        return $this->hasMany(Module::class);
    }

    public function trainingLinks()
    {
        return $this->hasMany(ItemTrainingLink::class);
    }
}
