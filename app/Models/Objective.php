<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Objective extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_session_id',
        'objective_text',
        'type',
        'objective_order',
    ];

    public function session()
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function results()
    {
        return $this->hasMany(ParticipantObjectiveResult::class);
    }
}

