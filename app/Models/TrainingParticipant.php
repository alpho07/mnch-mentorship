<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_id',
        'user_id',
        'name',
        'cadre_id',
        'department_id',
        'mobile',
        'email',
        'is_tot',
        'outcome_id',
    ];

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cadre()
    {
        return $this->belongsTo(Cadre::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function outcome()
    {
        return $this->belongsTo(Grade::class);
    }

    public function sessionAttendances()
    {
        return $this->hasMany(SessionAttendance::class);
    }

    public function objectiveResults()
    {
        return $this->hasMany(ParticipantObjectiveResult::class);
    }
}
