<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentQuestionResponse extends Model {

    protected $fillable = [
        'assessment_id',
        'assessment_question_id',
        'response_value',
        'explanation',
        'metadata',
        'score',
    ];
    protected $casts = [
        'metadata' => 'array',
        'score' => 'decimal:2',
    ];

    protected static function boot() {
        parent::boot();

        // Auto-calculate score before saving
        static::saving(function ($response) {
            $question = $response->question;

            if ($question && $question->is_scored) {
                $response->score = $question->calculateScore($response->response_value);
            }

            // For proportion questions, calculate proportion
            if ($question && $question->question_type === 'proportion' && $response->metadata) {
                $sampleSize = $response->metadata['sample_size'] ?? 0;
                $positiveCount = $response->metadata['positive_count'] ?? 0;

                if ($sampleSize > 0) {
                    $proportion = ($positiveCount / $sampleSize) * 100;
                    $response->metadata = array_merge($response->metadata, [
                        'calculated_proportion' => round($proportion, 2),
                    ]);
                }
            }
        });

        // Trigger scoring recalculation after save
        static::saved(function ($response) {
            $question = $response->question;

            if ($question && $question->section && $question->section->is_scored) {
                // Dispatch job or call service to recalculate section score
                app(\App\Services\DynamicScoringService::class)
                        ->recalculateSectionScore($response->assessment_id, $question->assessment_section_id);
            }
        });
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function assessment(): BelongsTo {
        return $this->belongsTo(Assessment::class);
    }

    public function question(): BelongsTo {
        return $this->belongsTo(AssessmentQuestion::class, 'assessment_question_id');
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get response as boolean
     */
    public function getBooleanValue(): ?bool {
        return match (strtolower($this->response_value)) {
            'yes', '1', 'true' => true,
            'no', '0', 'false' => false,
            default => null,
        };
    }

    /**
     * Get response as numeric
     */
    public function getNumericValue(): ?float {
        return is_numeric($this->response_value) ? (float) $this->response_value : null;
    }

    /**
     * Check if has explanation
     */
    public function hasExplanation(): bool {
        return !empty($this->explanation);
    }

    /**
     * Get proportion data
     */
    public function getProportionData(): ?array {
        if ($this->question->question_type !== 'proportion' || !$this->metadata) {
            return null;
        }

        return [
            'sample_size' => $this->metadata['sample_size'] ?? 0,
            'positive_count' => $this->metadata['positive_count'] ?? 0,
            'proportion' => $this->metadata['calculated_proportion'] ?? 0,
        ];
    }
}
