<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_session_id',
        'training_participant_id',
        'present',
    ];

    public function session()
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function participant()
    {
        return $this->belongsTo(TrainingParticipant::class, 'training_participant_id');
    }
}
