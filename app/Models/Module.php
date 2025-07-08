<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'program_id',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function topics()
    {
        return $this->hasMany(Topic::class);
    }

    public function trainingLinks()
    {
        return $this->hasMany(ItemTrainingLink::class);
    }
}
