<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['sometimes', 'string', 'max:255', 'min:2'],
            'business_type' => ['sometimes', 'string', 'max:100', 'min:2'],
            'description'   => ['sometimes', 'string', 'min:20', 'max:1000'],
            'title'         => ['sometimes', 'string', 'max:255'],
            'tagline'       => ['sometimes', 'string', 'max:255'],
            'about_section' => ['sometimes', 'string'],
            'services'      => ['sometimes', 'array', 'min:1'],
            'services.*'    => ['string', 'max:255'],
        ];
    }
}
