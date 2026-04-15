<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentQuestion1 extends Model {

    protected $fillable = [
        'assessment_section_id',
        'question_code',
        'question_text',
        'help_text',
        'question_type',
        'options',
        'is_required',
        'validation_rules',
        'display_conditions',
        'requires_explanation_on',
        'explanation_label',
        'skip_logic',
        'scoring_map',
        'is_scored',
        'order',
        'group',
        'is_active',
    ];
    protected $casts = [
        'options' => 'array',
        'validation_rules' => 'array',
        'display_conditions' => 'array',
        'requires_explanation_on' => 'array',
        'skip_logic' => 'array',
        'scoring_map' => 'array',
        'is_required' => 'boolean',
        'is_scored' => 'boolean',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function section(): BelongsTo {
        return $this->belongsTo(AssessmentSection::class, 'assessment_section_id');
    }
    
      public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query) {
        return $query->orderBy('order');
    }


    public function responses(): HasMany {
        return $this->hasMany(AssessmentQuestionResponse::class);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Calculate score from response value
     */
    public function calculateScore($responseValue): ?float {
        if (!$this->is_scored || !$this->scoring_map) {
            return null;
        }

        return $this->scoring_map[$responseValue] ?? 0;
    }

    /**
     * Check if explanation is required for given response
     */
    public function shouldRequireExplanation($responseValue): bool {
        if (!$this->requires_explanation_on) {
            return false;
        }

        return in_array($responseValue, $this->requires_explanation_on);
    }

    /**
     * Get skip logic for response value
     */
    public function shouldSkip($responseValue): ?array {
        if (!$this->skip_logic) {
            return null;
        }

        if (isset($this->skip_logic['if_response']) && $this->skip_logic['if_response'] === $responseValue) {
            return [
                'skip_to' => $this->skip_logic['skip_to'] ?? null,
                'hide_questions' => $this->skip_logic['hide_questions'] ?? [],
            ];
        }

        return null;
    }

    /**
     * Check if question should be displayed based on other responses
     */
    public function shouldDisplay(array $allResponses): bool {
        if (!$this->display_conditions) {
            return true;
        }

        $questionCode = $this->display_conditions['question_code'] ?? null;
        $operator = $this->display_conditions['operator'] ?? 'equals';
        $value = $this->display_conditions['value'] ?? null;

        if (!$questionCode || !isset($allResponses[$questionCode])) {
            return false;
        }

        $actualValue = $allResponses[$questionCode];

        return match ($operator) {
            'equals' => $actualValue === $value,
            'not_equals' => $actualValue !== $value,
            'in' => is_array($value) && in_array($actualValue, $value),
            'not_in' => is_array($value) && !in_array($actualValue, $value),
            default => true,
        };
    }

    /**
     * Get response for specific assessment
     */
    public function getResponseForAssessment(int $assessmentId): ?AssessmentQuestionResponse {
        return $this->responses()
                        ->where('assessment_id', $assessmentId)
                        ->first();
    }
}
