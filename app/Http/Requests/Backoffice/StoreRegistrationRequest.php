<?php

namespace App\Http\Requests\Backoffice;

use App\Models\Registration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:255'],
            'email'            => ['nullable', 'email', 'max:255'],
            'postal_code'      => ['nullable', 'string', 'max:10'],
            'municipality'     => ['nullable', 'string', 'max:255'],

            'event_type_id'    => ['nullable', 'exists:event_types,id'],
            'event_date'       => ['nullable', 'date'],
            'event_time'       => ['nullable', 'date_format:H:i'],
            'stay_option_id'   => ['nullable', 'exists:stay_options,id'],
            'catering_option_id' => ['nullable', 'exists:catering_options,id'],

            'participants_children'   => ['required', 'integer', 'min:0'],
            'participants_adults'     => ['required', 'integer', 'min:0'],
            'participants_supervisors' => ['required', 'integer', 'min:0'],

            'comment'          => ['nullable', 'string'],
            'stats'            => ['nullable', 'array'],
            'stats.already_visited'       => ['nullable', 'boolean'],
            'stats.recommended_by_friend' => ['nullable', 'boolean'],
            'stats.internet'              => ['nullable', 'boolean'],
            'stats.social_media'          => ['nullable', 'boolean'],
            'stats.facade'                => ['nullable', 'boolean'],
            'stats.ai'                    => ['nullable', 'boolean'],

            'status' => [
                'required',
                'string',
                Rule::in(array_keys(Registration::statusOptions())),
            ],

            'invoice_requested'   => ['required', 'boolean'],
            'invoice_company_name' => ['nullable', 'string', 'max:255'],
            'invoice_vat_number'  => ['nullable', 'string', 'max:255'],
            'invoice_email'       => ['nullable', 'email', 'max:255'],
            'invoice_address'     => ['nullable', 'string', 'max:255'],
            'invoice_postal_code' => ['nullable', 'string', 'max:10'],
            'invoice_city'        => ['nullable', 'string', 'max:255'],

            'outside_opening_hours' => ['required', 'boolean'],
            'is_member'             => ['sometimes', 'boolean'],

            // Nieuwe primaire koppeling: account_id
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],

            // Backward compat: member_id wordt nog geaccepteerd maar account_id heeft voorkeur
            'member_id'  => ['nullable', 'integer', 'exists:members,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'invoice_requested'      => filter_var($this->input('invoice_requested', false), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            'outside_opening_hours'  => filter_var($this->input('outside_opening_hours', false), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            'participants_children'  => (int) $this->input('participants_children', 0),
            'participants_adults'    => (int) $this->input('participants_adults', 0),
            'participants_supervisors' => (int) $this->input('participants_supervisors', 0),
            'is_member'              => filter_var($this->input('is_member', false), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
        ]);
    }
}
