<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentSection extends Model {

    protected $fillable = [
        'name',
        'code',
        'description',
        'icon',
        'order',
        'is_active',
    ];
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Questions in this section
     */
    public function questions(): HasMany {
        return $this->hasMany(AssessmentQuestion::class, 'assessment_section_id');
    }

    /**
     * Section scores
     */
    public function sectionScores(): HasMany {
        return $this->hasMany(AssessmentSectionScore::class, 'assessment_section_id');
    }

    /**
     * Scope to get only active sections
     */
    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only scored sections
     */
    public function scopeScored($query) {
        return $query->where('is_scored', true);
    }

    /**
     * Scope to order by order column
     */
    public function scopeOrdered($query) {
        return $query->orderBy('order');
    }
}
