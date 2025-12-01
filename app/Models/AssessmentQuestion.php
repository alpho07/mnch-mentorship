<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentQuestion extends Model {

    use HasFactory;

    protected $fillable = [
        'section_id',
        'question_code',
        'question_text',
        'response_type',
        'matrix_locations',
        'options',
        'is_required',
        'requires_explanation',
        'explanation_label',
        'scoring_map',
        'include_in_scoring',
        'skip_logic',
        'order',
        'is_active',
        'help_text',
        'metadata',
    ];
    protected $casts = [
        'matrix_locations' => 'array',
        'options' => 'array',
        'is_required' => 'boolean',
        'requires_explanation' => 'boolean',
        'scoring_map' => 'array',
        'include_in_scoring' => 'boolean',
        'skip_logic' => 'array',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function section(): BelongsTo {
        return $this->belongsTo(AssessmentSection::class, 'section_id');
    }

    public function responses(): HasMany {
        return $this->hasMany(AssessmentResponse::class, 'question_id');
    }

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query) {
        return $query->orderBy('order');
    }

    public function scopeScored($query) {
        return $query->where('is_scored', true);
    }

    public function isMatrixQuestion(): bool {
        return $this->response_type === 'matrix';
    }
    
    public function calculateScore($responseValue): ?float {
        if (!$this->is_scored || !$this->scoring_map) {
            return null;
        }

        return $this->scoring_map[$responseValue] ?? 0;
    }
}
