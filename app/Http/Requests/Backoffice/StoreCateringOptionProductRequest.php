<?php

namespace App\Http\Requests\Backoffice;

use Illuminate\Foundation\Http\FormRequest;

class StoreCateringOptionProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'applies_to_children' => ['required', 'boolean'],
            'applies_to_adults' => ['required', 'boolean'],
            'quantity_per_person' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
