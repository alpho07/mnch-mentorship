<?php

namespace App\Services;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use Filament\Forms;

class DynamicFormBuilder {

    /**
     * Build form fields for a specific section
     */
    public static function buildForSection(int $sectionId, ?int $assessmentId = null): array {
        $questions = AssessmentQuestion::where('assessment_section_id', $sectionId)
                ->where('is_active', true)
                ->orderBy('order')
                ->get();

        if ($questions->isEmpty()) {
            return [
                        Forms\Components\Placeholder::make('no_questions')
                        ->label('')
                        ->content('No questions configured for this section yet.')
                        ->columnSpanFull(),
            ];
        }

        $fields = [];

        foreach ($questions as $question) {
            $existingResponse = null;

            if ($assessmentId) {
                $existingResponse = AssessmentQuestionResponse::where('assessment_id', $assessmentId)
                        ->where('assessment_question_id', $question->id)
                        ->first();
            }

            $field = static::buildFieldForQuestion($question, $existingResponse);

            if ($field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Build a single field for a question
     */
    protected static function buildFieldForQuestion(AssessmentQuestion $question, ?AssessmentQuestionResponse $existingResponse): mixed {
        $fieldName = "question_response_{$question->id}";

        // NBU & Paediatric questions
        if (in_array($question->question_code, ['INFRA_NBU', 'INFRA_PAED'])) {
            return static::buildUnitCapacityField($question, $fieldName, $existingResponse);
        }

        // Build the appropriate field based on question type
        $field = match ($question->question_type) {
            'yes_no' => static::buildYesNoField($question, $fieldName, $existingResponse),
            'yes_no_partial' => static::buildYesNoPartialField($question, $fieldName, $existingResponse),
            'proportion' => static::buildProportionField($question, $fieldName, $existingResponse),
            'number' => static::buildNumberField($question, $fieldName, $existingResponse),
            'text' => static::buildTextField($question, $fieldName, $existingResponse),
            'select' => static::buildSelectField($question, $fieldName, $existingResponse),
            'radio' => static::buildRadioField($question, $fieldName, $existingResponse),
            default => null,
        };

        // Apply conditional logic if exists (check both new and old field names)
        $conditions = $question->conditional_logic ?? $question->display_conditions;
        
        if ($field && $conditions) {
            // Decode if it's a JSON string
            if (is_string($conditions)) {
                $conditions = json_decode($conditions, true);
            }
            
            if (is_array($conditions)) {
                $field = static::applyConditionalLogic($field, $conditions);
            }
        }

        return $field;
    }

    /**
     * Build Yes/No field
     */
    protected static function buildYesNoField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response) {
        return static::buildYesNoPartialField($question, $fieldName, $response, ['Yes', 'No']);
    }

    /**
     * Build Yes/No/Partial field
     */
    protected static function buildYesNoPartialField(
            AssessmentQuestion $question,
            string $fieldName,
            ?AssessmentQuestionResponse $response,
            array $options = ['Yes', 'No', 'Partially']
    ) {
        $field = Forms\Components\Radio::make($fieldName)
                ->label($question->question_text)
                ->options(array_combine($options, $options))
                ->required($question->is_required)
                ->inline()
                ->live()
                ->default($response?->response_value);

        if ($question->help_text) {
            $field->helperText($question->help_text);
        }

        // Normalize display_conditions into array
        $displayConditions = $question->display_conditions;

// If null → no conditions
        if (!$displayConditions) {
            $displayConditions = [];
        }

// If JSON stored as string → decode
        if (is_string($displayConditions) && str_starts_with(trim($displayConditions), '[')) {
            $decoded = json_decode($displayConditions, true);
            if (is_array($decoded)) {
                $displayConditions = $decoded;
            }
        }

// If comma-separated list → convert CSV to array
        if (is_string($displayConditions)) {
            $displayConditions = array_map('trim', explode(',', $displayConditions));
        }

// Guarantee it's an array
        if (!is_array($displayConditions)) {
            $displayConditions = [$displayConditions];
        }

        foreach ($displayConditions as $conditions) {
            $dependentQuestion = \App\Models\AssessmentQuestion::find($conditions['question_id'] ?? null);

            if ($dependentQuestion) {
                $field->visible(function (Forms\Get $get) use ($dependentQuestion, $conditions) {
                    $dependentFieldName = "question_response_{$dependentQuestion->id}";
                    $actualValue = $get($dependentFieldName);
                    $expectedValue = $conditions['value'] ?? null;
                    $operator = $conditions['operator'] ?? 'equals';

                    return static::evaluateCondition($actualValue, $expectedValue, $operator);
                });
            }
        }
        $fields = [$field];

        /**
         * =========================================================
         * Explanation field (the section causing your crash)
         * =========================================================
         */
        $requiresExplanationOn = $question->requires_explanation_on ?? ['No', 'Partially'];

        // Normalize → ALWAYS an array
        $requiresExplanationOn = static::normalizeExplanationArray($requiresExplanationOn);

        $explanationField = Forms\Components\Textarea::make("{$fieldName}_explanation")
                ->label($question->explanation_label ?? 'Comments/Recommendations/Remarks')
                ->rows(3)
                ->placeholder('Please provide details, recommendations, or action plans...')
                ->visible(function (Forms\Get $get) use ($fieldName, $requiresExplanationOn) {
                    $value = $get($fieldName);
                    return in_array($value, $requiresExplanationOn, true);
                })
                ->default($response?->explanation);

        $fields[] = $explanationField;

        return Forms\Components\Group::make($fields)->columnSpanFull();
    }

    /**
     * Normalizes requires_explanation_on so it's ALWAYS an array
     */
    protected static function normalizeExplanationArray($value): array {
        if (!$value) {
            return ['No', 'Partially'];
        }

        // JSON?
        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // CSV
        if (is_string($value)) {
            return array_map('trim', explode(',', $value));
        }

        // Single value
        if (!is_array($value)) {
            return [$value];
        }

        return $value;
    }

    /**
     * Evaluate conditions
     */
    protected static function evaluateCondition($actualValue, $expectedValue, string $operator): bool {
        return match ($operator) {
            'equals' => $actualValue === $expectedValue,
            'not_equals' => $actualValue !== $expectedValue,
            'in' => is_array($expectedValue) && in_array($actualValue, $expectedValue),
            'not_in' => is_array($expectedValue) && !in_array($actualValue, $expectedValue),
            default => true,
        };
    }

    /**
     * Build Text field
     */
    protected static function buildTextField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response) {
        return Forms\Components\Textarea::make($fieldName)
                        ->label($question->question_text)
                        ->rows(3)
                        ->required($question->is_required)
                        ->default($response?->response_value)
                        ->helperText($question->help_text)
                        ->columnSpanFull();
    }

    /**
     * Build Number field
     */
    protected static function buildNumberField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response) {
        $field = Forms\Components\TextInput::make($fieldName)
                ->label($question->question_text)
                ->numeric()
                ->required($question->is_required)
                ->default($response?->response_value);

        if ($question->help_text) {
            $field->helperText($question->help_text);
        }

        if ($question->validation_rules) {
            if (isset($question->validation_rules['min'])) {
                $field->minValue($question->validation_rules['min']);
            }
            if (isset($question->validation_rules['max'])) {
                $field->maxValue($question->validation_rules['max']);
            }
        }

        return $field;
    }

    /**
     * Build select field
     */
    protected static function buildSelectField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response) {
        return Forms\Components\Select::make($fieldName)
                        ->label($question->question_text)
                        ->options(array_combine($question->options ?? [], $question->options ?? []))
                        ->required($question->is_required)
                        ->searchable()
                        ->default($response?->response_value)
                        ->helperText($question->help_text);
    }

    /**
     * Build radio field
     */
    protected static function buildRadioField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response) {
        return Forms\Components\Radio::make($fieldName)
                        ->label($question->question_text)
                        ->options(array_combine($question->options ?? [], $question->options ?? []))
                        ->required($question->is_required)
                        ->default($response?->response_value)
                        ->helperText($question->help_text);
    }

    /**
     * Build Proportion field
     */
    protected static function buildProportionField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response) {
        $metadata = $response?->metadata ?? [];
        $sampleSize = $question->validation_rules['sample_size'] ?? 10;

        return Forms\Components\Group::make([
                            Forms\Components\Placeholder::make("{$fieldName}_label")
                            ->label('')
                            ->content($question->question_text)
                            ->columnSpanFull(),
                    Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make("{$fieldName}_sample_size")
                                ->label('Sample Size')
                                ->numeric()
                                ->default($metadata['sample_size'] ?? $sampleSize)
                                ->disabled()
                                ->dehydrated(false)
                                ->hint("(Fixed at {$sampleSize})"),
                                Forms\Components\TextInput::make("{$fieldName}_positive_count")
                                ->label('Positive Count')
                                ->numeric()
                                ->required()
                                ->default($metadata['positive_count'] ?? 0)
                                ->live()
                                ->minValue(0)
                                ->maxValue($sampleSize)
                                ->afterStateUpdated(function (Forms\Set $set, $state) use ($fieldName, $sampleSize) {
                                    if (is_numeric($state) && $state >= 0 && $state <= $sampleSize) {
                                        $proportion = ($state / $sampleSize) * 100;
                                        $set("{$fieldName}_proportion", number_format($proportion, 1));
                                    }
                                }),
                                Forms\Components\TextInput::make("{$fieldName}_proportion")
                                ->label('Proportion (%)')
                                ->default($metadata['calculated_proportion'] ?? 0)
                                ->disabled()
                                ->dehydrated(false)
                                ->suffix('%'),
                    ]),
                ])->columnSpanFull();
    }

    /**
     * Build NBU/Paediatric Unit Capacity Field
     */
    protected static function buildUnitCapacityField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response) {
        $metadata = $response?->metadata ?? [];
        $isNBU = $question->question_code === 'INFRA_NBU';

        $fields = [
                    Forms\Components\Radio::make($fieldName)
                    ->label($question->question_text)
                    ->options(['Yes' => 'Yes', 'No' => 'No'])
                    ->required()
                    ->inline()
                    ->live()
                    ->default($response?->response_value),
        ];

        if ($isNBU) {
            $fields[] = Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make("{$fieldName}_nicu_beds")
                        ->label('NICU Beds')
                        ->numeric()
                        ->default($metadata['nicu_beds'] ?? 0)
                        ->visible(fn(Forms\Get $get) => $get($fieldName) === 'Yes'),
                        Forms\Components\TextInput::make("{$fieldName}_general_cots")
                        ->label('General Cots')
                        ->numeric()
                        ->default($metadata['general_cots'] ?? 0)
                        ->visible(fn(Forms\Get $get) => $get($fieldName) === 'Yes'),
                        Forms\Components\TextInput::make("{$fieldName}_kmc_beds")
                        ->label('KMC Beds')
                        ->numeric()
                        ->default($metadata['kmc_beds'] ?? 0)
                        ->visible(fn(Forms\Get $get) => $get($fieldName) === 'Yes'),
            ]);
        } else {
            $fields[] = Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make("{$fieldName}_general_beds")
                        ->label('General Beds')
                        ->numeric()
                        ->default($metadata['general_beds'] ?? 0)
                        ->visible(fn(Forms\Get $get) => $get($fieldName) === 'Yes'),
                        Forms\Components\TextInput::make("{$fieldName}_picu_beds")
                        ->label('PICU Beds')
                        ->numeric()
                        ->default($metadata['picu_beds'] ?? 0)
                        ->visible(fn(Forms\Get $get) => $get($fieldName) === 'Yes'),
            ]);
        }

        return Forms\Components\Group::make($fields)->columnSpanFull();
    }

    /**
     * Save responses
     */
    public static function saveResponses(int $assessmentId, int $sectionId, array $data): void {
        $questions = AssessmentQuestion::where('assessment_section_id', $sectionId)
                ->where('is_active', true)
                ->get();

        foreach ($questions as $question) {
            $fieldName = "question_response_{$question->id}";

            // For proportion fields, check for _positive_count instead of main field
            if ($question->question_type === 'proportion') {
                if (!array_key_exists("{$fieldName}_positive_count", $data)) {
                    continue;
                }
                $responseValue = null; // Will be set in proportion block below
            } else {
                if (!array_key_exists($fieldName, $data)) {
                    continue;
                }
                $responseValue = $data[$fieldName];
            }

            $explanation = $data["{$fieldName}_explanation"] ?? null;
            $metadata = null;

            // Proportion
            if ($question->question_type === 'proportion') {
                $positiveCount = $data["{$fieldName}_positive_count"] ?? 0;
                $sampleSize = $question->validation_rules['sample_size'] ?? 10;

                $proportion = $sampleSize > 0 ? ($positiveCount / $sampleSize) * 100 : 0;

                $metadata = [
                    'sample_size' => $sampleSize,
                    'positive_count' => $positiveCount,
                    'calculated_proportion' => round($proportion, 2),
                ];

                $responseValue = $positiveCount;
            }

            // NBU/Paediatric metadata
            if (in_array($question->question_code, ['INFRA_NBU', 'INFRA_PAED'])) {
                if ($responseValue === 'Yes') {
                    if ($question->question_code === 'INFRA_NBU') {
                        $metadata = [
                            'nicu_beds' => (int) ($data["{$fieldName}_nicu_beds"] ?? 0),
                            'general_cots' => (int) ($data["{$fieldName}_general_cots"] ?? 0),
                            'kmc_beds' => (int) ($data["{$fieldName}_kmc_beds"] ?? 0),
                        ];
                    } else {
                        $metadata = [
                            'general_beds' => (int) ($data["{$fieldName}_general_beds"] ?? 0),
                            'picu_beds' => (int) ($data["{$fieldName}_picu_beds"] ?? 0),
                        ];
                    }
                }
            }

            // Score
            $score = null;
            if ($question->is_scored && $question->scoring_map) {
                $score = $question->scoring_map[$responseValue] ?? 0;
            }

            AssessmentQuestionResponse::updateOrCreate(
                    [
                        'assessment_id' => $assessmentId,
                        'assessment_question_id' => $question->id,
                    ],
                    [
                        'response_value' => $responseValue,
                        'explanation' => $explanation,
                        'metadata' => $metadata,
                        'score' => $score,
                    ]
            );
        }

        app(\App\Services\DynamicScoringService::class)->recalculateSectionScore($assessmentId, $sectionId);
    }

    /**
     * Apply conditional logic to show/hide field based on another question's answer
     * Handles both display_conditions and conditional_logic formats
     */
    protected static function applyConditionalLogic($field, array $conditionalLogic) {
        // Handle new format: conditional_logic with show_if
        if (isset($conditionalLogic['show_if'])) {
            $showIf = $conditionalLogic['show_if'];
            $dependentQuestionCode = $showIf['question_code'] ?? null;
            $requiredValue = $showIf['value'] ?? null;

            if (!$dependentQuestionCode || !$requiredValue) {
                return $field;
            }

            $dependentQuestion = AssessmentQuestion::where('question_code', $dependentQuestionCode)->first();
            
            if (!$dependentQuestion) {
                return $field;
            }

            $dependentFieldName = "question_response_{$dependentQuestion->id}";

            return $field->visible(function (Forms\Get $get) use ($dependentFieldName, $requiredValue) {
                return $get($dependentFieldName) === $requiredValue;
            });
        }

        // Handle old format: display_conditions with question_code, operator, value
        if (isset($conditionalLogic['question_code'])) {
            $dependentQuestionCode = $conditionalLogic['question_code'];
            $requiredValue = $conditionalLogic['value'] ?? null;
            $operator = $conditionalLogic['operator'] ?? 'equals';

            if (!$dependentQuestionCode || !$requiredValue) {
                return $field;
            }

            $dependentQuestion = AssessmentQuestion::where('question_code', $dependentQuestionCode)->first();
            
            if (!$dependentQuestion) {
                return $field;
            }

            $dependentFieldName = "question_response_{$dependentQuestion->id}";

            return $field->visible(function (Forms\Get $get) use ($dependentFieldName, $requiredValue, $operator) {
                $currentValue = $get($dependentFieldName);
                
                return match($operator) {
                    'equals' => $currentValue === $requiredValue,
                    'not_equals' => $currentValue !== $requiredValue,
                    default => $currentValue === $requiredValue,
                };
            });
        }

        return $field;
    }
}