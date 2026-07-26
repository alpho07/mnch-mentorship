<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'    => 'required|string|max:255',
            'middle_name'   => 'nullable|string|max:255',
            'last_name'     => 'required|string|max:255',
            'email'         => 'required|email|max:255|unique:users,email',
            'phone'         => 'required|string|max:20|unique:users,phone',
            'cadre_id'      => 'required|integer|exists:main_cadres,id',
            'department_id' => 'required|integer|exists:departments,id',
            'role'          => 'required|in:mentee,facility_mentor',
            'county_id'     => 'required|integer|exists:counties,id',
            'facility_id'   => 'required|integer|exists:facilities,id',
        ];
    }
}
