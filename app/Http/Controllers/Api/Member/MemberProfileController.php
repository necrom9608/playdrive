<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name'   => ['sometimes', 'string', 'max:255'],
            'last_name'    => ['sometimes', 'string', 'max:255'],
            'phone'        => ['sometimes', 'nullable', 'string', 'max:30'],
            'birth_date'   => ['sometimes', 'nullable', 'date'],
            'street'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'house_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'box'          => ['sometimes', 'nullable', 'string', 'max:20'],
            'postal_code'  => ['sometimes', 'nullable', 'string', 'max:20'],
            'city'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'country'      => ['sometimes', 'nullable', 'string', 'size:2'],
        ]);

        $account = $request->user();
        $account->update($data);

        return response()->json([
            'message' => 'Profiel bijgewerkt.',
            'data'    => [
                'id'           => $account->id,
                'first_name'   => $account->first_name,
                'last_name'    => $account->last_name,
                'email'        => $account->email,
                'phone'        => $account->phone,
                'birth_date'   => $account->birth_date?->toDateString(),
                'street'       => $account->street,
                'house_number' => $account->house_number,
                'box'          => $account->box,
                'postal_code'  => $account->postal_code,
                'city'         => $account->city,
                'country'      => $account->country,
            ],
        ]);
    }
}
