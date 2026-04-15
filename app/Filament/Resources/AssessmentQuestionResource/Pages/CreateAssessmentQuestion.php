<?php

// app/Filament/Resources/AssessmentQuestionResource/Pages/CreateAssessmentQuestion.php

namespace App\Filament\Resources\AssessmentQuestionResource\Pages;

use App\Filament\Resources\AssessmentQuestionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssessmentQuestion extends CreateRecord {

    protected static string $resource = AssessmentQuestionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array {
        return static::resolveConditionalLogic($data);
    }

    protected function getRedirectUrl(): string {
        return $this->getResource()::getUrl('index');
    }

    public static function resolveConditionalLogic(array $data): array {
        // If conditional_logic_parent was set and no multi-condition raw JSON, 
        // ensure conditional_logic.question_code is set properly
        if (!empty($data['conditional_logic_parent']) && empty($data['conditional_logic']['question_code'])) {
            $data['conditional_logic'] = array_merge($data['conditional_logic'] ?? [], [
                'question_code' => $data['conditional_logic_parent'],
            ]);
        }

        unset($data['conditional_logic_parent']);

        // Handle raw JSON override from the advanced textarea
        if (!empty($data['conditional_logic_raw'])) {
            $parsed = json_decode($data['conditional_logic_raw'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                $data['conditional_logic'] = $parsed;
            }
        }
        unset($data['conditional_logic_raw']);

        // Empty conditional_logic should be stored as null
        if (isset($data['conditional_logic']) && empty(array_filter($data['conditional_logic'] ?? []))) {
            $data['conditional_logic'] = null;
        }

        return $data;
    }
}
