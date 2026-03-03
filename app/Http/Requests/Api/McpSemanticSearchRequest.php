<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class McpSemanticSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:1000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
