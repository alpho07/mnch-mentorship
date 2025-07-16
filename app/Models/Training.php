<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'program_id',
        'facility_id',
        'organizer_id',
        'location',
        'start_date',
        'end_date',
        'approach',
        'notes'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // Relationships
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function participants()
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'training_departments', 'training_id', 'department_id');
    }

    public function sessions()
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('start_date', [$start, $end]);
    }

    public function scopeByApproach($query, $approach)
    {
        return $query->where('approach', $approach);
    }
}
