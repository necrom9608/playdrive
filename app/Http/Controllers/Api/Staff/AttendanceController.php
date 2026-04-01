<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffCheckin;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function toggle(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $user = $request->attributes->get('staff_user');
        $tenantId = (int) $currentTenant->id();

        $openSession = StaffCheckin::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->first();

        if ($openSession) {
            $openSession->update([
                'checked_out_at' => now(),
                'checked_out_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'action' => 'check_out',
                'message' => 'Je bent succesvol uitgecheckt.',
            ]);
        }

        StaffCheckin::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'rfid_uid' => $user->rfid_uid,
            'checked_in_at' => now(),
            'checked_in_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'action' => 'check_in',
            'message' => 'Je bent succesvol ingecheckt.',
        ], 201);
    }
}
