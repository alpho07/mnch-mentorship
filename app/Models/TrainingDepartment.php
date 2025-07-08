<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingDepartment extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_id',
        'department_id',
    ];

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
