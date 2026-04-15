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

        // Apply conditional logic if exists
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

        $fields = [$field];

        // Explanation field
        $requiresExplanationOn = $question->requires_explanation_on ?? ['No', 'Partially'];
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
                ->integer()
                ->required($question->is_required)
                ->default($response?->response_value)
                ->minValue(0);

        if ($question->help_text) {
            $field->helperText($question->help_text);
        }

        if ($question->validation_rules) {
            $rules = is_string($question->validation_rules) 
                ? json_decode($question->validation_rules, true) 
                : $question->validation_rules;
                
            if (isset($rules['min'])) {
                $field->minValue($rules['min']);
            }
            if (isset($rules['max'])) {
                $field->maxValue($rules['max'])
                     ->helperText("Maximum value: {$rules['max']}");
            }
        }

        return $field;
    }

    /**
     * Build select field
     */
    protected static function buildSelectField(AssessmentQuestion $question, string $fieldName, ?AssessmentQuestionResponse $response) {
        $options = $question->options;
        if (is_string($options)) {
            $options = json_decode($options, true) ?? [];
        }
        
        $optionsArray = is_array($options) ? array_combine($options, $options) : [];
        
        return Forms\Components\Select::make($fieldName)
                        ->label($question->question_text)
                        ->options($optionsArray)
                        ->required($question->is_required)
                        ->searchable()
                        ->default($response?->response_value)
                        ->helperText($question->help_text)
                        ->live();
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

            // For proportion fields
            if ($question->question_type === 'proportion') {
                if (!array_key_exists("{$fieldName}_positive_count", $data)) {
                    continue;
                }
                $responseValue = null;
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
     * Apply conditional logic with support for OR and AND operators
     * CRITICAL: Fields are HIDDEN by default, only shown when conditions explicitly match
     */
    protected static function applyConditionalLogic($field, array $conditionalLogic) {
        // Handle OR operator (show if ANY condition is true)
        if (isset($conditionalLogic['operator']) && $conditionalLogic['operator'] === 'or') {
            $orConditions = $conditionalLogic['conditions'] ?? [];
            
            return $field->visible(function (Forms\Get $get) use ($orConditions) {
                // Check each condition - if ANY is true, show the field
                foreach ($orConditions as $condition) {
                    $parentCode = $condition['question_code'] ?? null;
                    $expectedValue = $condition['value'] ?? null;
                    $operator = $condition['operator'] ?? 'equals';
                    
                    if (!$parentCode) continue;
                    
                    $parentQuestion = AssessmentQuestion::where('question_code', $parentCode)->first();
                    if (!$parentQuestion) continue;
                    
                    $parentFieldName = "question_response_{$parentQuestion->id}";
                    $actualValue = $get($parentFieldName);
                    
                    // If this condition matches, show the field
                    if (static::evaluateCondition($actualValue, $expectedValue, $operator)) {
                        return true;
                    }
                }
                
                // None matched, hide the field
                return false;
            });
        }

        // Handle AND operator (show only if ALL conditions are true)
        if (isset($conditionalLogic['operator']) && $conditionalLogic['operator'] === 'and') {
            $andConditions = $conditionalLogic['conditions'] ?? [];
            
            return $field->visible(function (Forms\Get $get) use ($andConditions) {
                // Check ALL conditions - they ALL must be true
                foreach ($andConditions as $condition) {
                    $parentCode = $condition['question_code'] ?? null;
                    $expectedValue = $condition['value'] ?? null;
                    $operator = $condition['operator'] ?? 'equals';
                    
                    if (!$parentCode) return false;
                    
                    $parentQuestion = AssessmentQuestion::where('question_code', $parentCode)->first();
                    if (!$parentQuestion) return false;
                    
                    $parentFieldName = "question_response_{$parentQuestion->id}";
                    $actualValue = $get($parentFieldName);
                    
                    // If ANY condition fails, hide the field
                    if (!static::evaluateCondition($actualValue, $expectedValue, $operator)) {
                        return false;
                    }
                }
                
                // All conditions matched, show the field
                return true;
            });
        }

        // Handle single condition (legacy format with question_code at root)
        if (isset($conditionalLogic['question_code'])) {
            $dependentQuestionCode = $conditionalLogic['question_code'];
            $requiredValue = $conditionalLogic['value'] ?? null;
            $operator = $conditionalLogic['operator'] ?? 'equals';

            if (!$dependentQuestionCode) {
                return $field;
            }

            $dependentQuestion = AssessmentQuestion::where('question_code', $dependentQuestionCode)->first();
            
            if (!$dependentQuestion) {
                return $field;
            }

            $dependentFieldName = "question_response_{$dependentQuestion->id}";

            return $field->visible(function (Forms\Get $get) use ($dependentFieldName, $requiredValue, $operator) {
                $currentValue = $get($dependentFieldName);
                
                // CRITICAL: If no value yet, hide the field
                if ($currentValue === null || $currentValue === '') {
                    return false;
                }
                
                return static::evaluateCondition($currentValue, $requiredValue, $operator);
            });
        }

        // Handle legacy show_if format
        if (isset($conditionalLogic['show_if'])) {
            $showIf = $conditionalLogic['show_if'];
            $dependentQuestionCode = $showIf['question_code'] ?? null;
            $requiredValue = $showIf['value'] ?? null;

            if (!$dependentQuestionCode) {
                return $field;
            }

            $dependentQuestion = AssessmentQuestion::where('question_code', $dependentQuestionCode)->first();
            
            if (!$dependentQuestion) {
                return $field;
            }

            $dependentFieldName = "question_response_{$dependentQuestion->id}";

            return $field->visible(function (Forms\Get $get) use ($dependentFieldName, $requiredValue) {
                $currentValue = $get($dependentFieldName);
                
                if ($currentValue === null || $currentValue === '') {
                    return false;
                }
                
                return $currentValue === $requiredValue;
            });
        }

        return $field;
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
            'greater_than' => is_numeric($actualValue) && is_numeric($expectedValue) && $actualValue > $expectedValue,
            'less_than' => is_numeric($actualValue) && is_numeric($expectedValue) && $actualValue < $expectedValue,
            default => false, // CRITICAL: Default to false (hide) for safety
        };
    }
}