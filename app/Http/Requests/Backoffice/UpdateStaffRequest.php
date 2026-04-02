<?php

namespace App\Http\Requests\Backoffice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('staff')?->id ?? $this->route('staff');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,' . $this->route('staff')->id],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,' . $this->route('staff')->id],
            'password' => ['nullable', 'string', 'min:4'],
            'rfid_uid' => ['nullable', 'string', 'max:100', 'unique:users,rfid_uid,' . $this->route('staff')->id],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'bus' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'is_admin' => ['required', 'boolean'],
        ];
    }
}
