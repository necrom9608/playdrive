<?php

namespace App\Http\Requests\Frontdesk;

use App\Support\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $member = $this->route('member');
        $memberId = is_object($member) ? $member->id : $member;

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('members', 'username')->ignore($memberId)->where(fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id()))],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:6'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'bus' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'rfid_uid' => ['nullable', 'string', 'max:100', Rule::unique('members', 'rfid_uid')->ignore($memberId)->where(fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id()))],
            'comment' => ['nullable', 'string'],
            'membership_started_at' => ['nullable', 'date'],
            'membership_expires_at' => ['nullable', 'date', 'after_or_equal:membership_started_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
