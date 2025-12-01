<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AssessmentSection extends Model {

    protected $fillable = [
        'code',
        'name',
        'description',
        'section_type',
        'is_scored',
        'order',
        'icon',
        'is_active',
    ];
    protected $casts = [
        'is_scored' => 'boolean',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    protected static function boot() {
        parent::boot();

        // Auto-generate code from name
        static::creating(function ($section) {
            if (empty($section->code)) {
                $section->code = Str::slug($section->name, '_');
            }
        });
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function questions(): HasMany {
        return $this->hasMany(AssessmentQuestion::class);
    }

    public function scores(): HasMany {
        return $this->hasMany(AssessmentSectionScore::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query) {
        return $query->orderBy('order');
    }

    public function scopeScored($query) {
        return $query->where('is_scored', true);
    }

    public function scopeDynamic($query) {
        return $query->where('section_type', 'dynamic_questions');
    }

    public function scopeStructured($query) {
        return $query->where('section_type', 'structured_data');
    }

    public function scopeCommodityMatrix($query) {
        return $query->where('section_type', 'commodity_matrix');
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get count of active questions
     */
    public function getActiveQuestionsCount(): int {
        return $this->questions()->where('is_active', true)->count();
    }

    /**
     * Get count of scored questions
     */
    public function getScoredQuestionsCount(): int {
        return $this->questions()
                        ->where('is_active', true)
                        ->where('is_scored', true)
                        ->count();
    }

    /**
     * Get progress for specific assessment
     */
    public function getProgressForAssessment(int $assessmentId): array {
        $totalQuestions = $this->getActiveQuestionsCount();
        $answeredQuestions = $this->questions()
                ->whereHas('responses', function ($query) use ($assessmentId) {
                    $query->where('assessment_id', $assessmentId);
                })
                ->count();

        return [
            'total' => $totalQuestions,
            'answered' => $answeredQuestions,
            'percentage' => $totalQuestions > 0 ? round(($answeredQuestions / $totalQuestions) * 100, 2) : 0,
            'status' => $answeredQuestions === 0 ? 'not_started' :
            ($answeredQuestions < $totalQuestions ? 'in_progress' : 'completed'),
        ];
    }
}
