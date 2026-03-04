<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource {

    public function toArray($request): array {
        return [
            'id' => $this->id,
            'facility_id' => $this->facility_id,
            'facility_name' => $this->facility?->name,
            'mfl_code' => $this->facility?->mfl_code,
            'county' => $this->facility?->subcounty?->county?->name,
            'subcounty' => $this->facility?->subcounty?->name,
            'assessment_type' => $this->assessment_type,
            'assessment_date' => $this->assessment_date instanceof \Carbon\Carbon ? $this->assessment_date->toDateString() : $this->assessment_date,
            'assessor_name' => $this->assessor_name,
            'assessor_contact' => $this->assessor_contact,
            'status' => $this->status,
            'section_progress' => $this->section_progress ?? [],
            'overall_score' => $this->overall_score,
            'overall_percentage' => $this->overall_percentage,
            'overall_grade' => $this->overall_grade,
            'completed_at' => $this->completed_at instanceof \Carbon\Carbon ? $this->completed_at->toDateString() : $this->completed_at,
            'created_at' => $this->created_at?->toIso8601String(),
            'section_scores' => $this->whenLoaded('sectionScores', fn() =>
                    $this->sectionScores->mapWithKeys(fn($s) => [
                        $s->section->code => [
                            'percentage' => $s->percentage,
                            'grade' => $s->grade,
                            'answered_questions' => $s->answered_questions,
                            'total_questions' => $s->total_questions,
                            'skipped_questions' => $s->skipped_questions,
                        ],
                            ])
            ),
        ];
    }
}
