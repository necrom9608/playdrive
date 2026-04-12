<?php

namespace App\Http\Requests\Frontdesk;

use App\Models\BadgeTemplate;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'login' => ['nullable', 'string', 'max:255', Rule::unique('members', 'login')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'membership_type' => ['nullable', 'in:adult,student'],
            'password' => ['nullable', 'string', 'min:6'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'box' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'rfid_uid' => ['nullable', 'string', 'max:100', Rule::unique('members', 'rfid_uid')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'comment' => ['nullable', 'string'],
            'membership_starts_at' => ['nullable', 'date'],
            'membership_ends_at' => ['nullable', 'date', 'after_or_equal:membership_starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'badge_template_id' => [
                'nullable',
                'integer',
                Rule::exists(BadgeTemplate::class, 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('template_type', 'member')),
            ],
        ];
    }
}
