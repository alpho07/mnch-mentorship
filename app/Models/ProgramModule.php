<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramModule extends Model {

    use HasFactory,
        SoftDeletes;

    protected $fillable = [
        'program_id',
        'name',
        'description',
        'order_sequence',
        'duration_weeks',
        'objectives',
        'content',
        'is_active',
    ];
    protected $casts = [
        'order_sequence' => 'integer',
        'duration_weeks' => 'integer',
        'objectives' => 'array',
        'content' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function program(): BelongsTo {
        return $this->belongsTo(Program::class);
    }

    public function classModules(): HasMany {
        return $this->hasMany(ClassModule::class, 'program_module_id');
    }

    public function moduleSessions(): HasMany {
        return $this->hasMany(\App\Models\ModuleSession::class, 'program_module_id');
    }

    // Query Scopes
    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeByProgram($query, int $programId) {
        return $query->where('program_id', $programId);
    }

    public function scopeOrdered($query) {
        return $query->orderBy('order_sequence');
    }

    // Computed Attributes
    public function getUsageCountAttribute(): int {
        return $this->classModules()->count();
    }

    // Helper Methods
    public function activate(): bool {
        return $this->update(['is_active' => true]);
    }

    public function deactivate(): bool {
        return $this->update(['is_active' => false]);
    }

    public function isActive(): bool {
        return $this->is_active === true;
    }

    public function canBeDeleted(): bool {
        // Can't delete if being used in any class modules
        return $this->classModules()->doesntExist();
    }
}
