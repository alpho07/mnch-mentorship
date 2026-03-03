<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreResponsesRequest extends FormRequest {

    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'section_code' => 'required|string|exists:assessment_sections,code',
            'responses' => 'required|array|min:1',
            'responses.*' => 'nullable|string|max:1000',
            'explanations' => 'nullable|array',
            'explanations.*' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array {
        return [
            'section_code.exists' => 'The specified section code does not exist.',
            'responses.required' => 'At least one response is required.',
        ];
    }
}
