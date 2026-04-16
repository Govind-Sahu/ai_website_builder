<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateWebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255', 'min:2'],
            'business_type' => ['required', 'string', 'max:100', 'min:2'],
            'description'   => ['required', 'string', 'min:20', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'business_name.required' => 'Business name is required.',
            'business_name.min'      => 'Business name must be at least 2 characters.',
            'business_name.max'      => 'Business name must not exceed 255 characters.',
            'business_type.required' => 'Business type is required.',
            'business_type.min'      => 'Business type must be at least 2 characters.',
            'description.required'   => 'Business description is required.',
            'description.min'        => 'Description must be at least 20 characters.',
            'description.max'        => 'Description must not exceed 1000 characters.',
        ];
    }
}
