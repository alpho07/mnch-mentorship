<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParticipantObjectiveResult extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'objective_id',
        'training_participant_id',
        'result',
        'grade_id',
        'comments',
    ];
    
    public function objective()
    {
        return $this->belongsTo(Objective::class);
    }
    
    public function participant()
    {
        return $this->belongsTo(TrainingParticipant::class, 'training_participant_id');
    }
    
    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }
}