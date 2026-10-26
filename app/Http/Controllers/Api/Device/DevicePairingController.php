<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Models\VenueDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Koppelen van een onbemand toestel (kiosk, Playscope).
 *
 *   POST /api/device/v1/pair/start  → toestel krijgt een koppelcode + poll-secret
 *   POST /api/device/v1/pair/poll   → 'pending' tot een admin de code claimt,
 *                                     daarna één keer 'paired' + token
 *
 * Het toestel toont de code op zijn scherm; de admin tikt die in de backoffice in
 * (Speelplekken → Toestellen → Toestel koppelen).
 */
class DevicePairingController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'        => ['required', 'string', 'in:' . implode(',', array_keys(VenueDevice::TYPES))],
            'hostname'    => ['nullable', 'string', 'max:100'],
            'platform'    => ['nullable', 'string', 'max:40'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ]);

        // Opruimen: verlopen, nooit geclaimde aanvragen.
        VenueDevice::query()
            ->whereNull('tenant_id')
            ->where('pairing_expires_at', '<', now())
            ->delete();

        $pollSecret = Str::random(48);
        $expiresAt = now()->addMinutes(VenueDevice::PAIRING_TTL_MINUTES);

        $device = VenueDevice::query()->create([
            'type'               => $data['type'],
            'hostname'           => $data['hostname'] ?? null,
            'platform'           => $data['platform'] ?? null,
            'app_version'        => $data['app_version'] ?? null,
            'pairing_code'       => VenueDevice::generatePairingCode(),
            'poll_secret_hash'   => VenueDevice::hashSecret($pollSecret),
            'pairing_expires_at' => $expiresAt,
            'last_ip'            => $request->ip(),
            'last_seen_at'       => now(),
        ]);

        return response()->json([
            'pairing_code'  => $device->pairing_code,
            'poll_secret'   => $pollSecret,
            'expires_at'    => $expiresAt->toIso8601String(),
            'poll_interval' => 3,
        ], 201);
    }

    public function poll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'poll_secret' => ['required', 'string', 'max:100'],
        ]);

        $device = VenueDevice::query()
            ->where('poll_secret_hash', VenueDevice::hashSecret($data['poll_secret']))
            ->first();

        if (! $device) {
            return response()->json(['status' => 'expired'], 410);
        }

        if (! $device->tenant_id) {
            if ($device->pairing_expires_at?->isPast()) {
                $device->delete();

                return response()->json(['status' => 'expired'], 410);
            }

            $device->forceFill(['last_seen_at' => now()])->save();

            return response()->json(['status' => 'pending']);
        }

        // Geclaimd: token één keer uitgeven en de koppelgegevens wissen.
        $token = 'pdd_' . Str::random(48);

        $device->forceFill([
            'token_hash'         => VenueDevice::hashSecret($token),
            'token_issued_at'    => now(),
            'poll_secret_hash'   => null,
            'pairing_code'       => null,
            'pairing_expires_at' => null,
            'last_seen_at'       => now(),
        ])->save();

        $device->load(['setup', 'tenant']);

        return response()->json([
            'status' => 'paired',
            'token'  => $token,
            'device' => $device->toPublicArray(),
            'tenant' => [
                'id'           => $device->tenant?->id,
                'display_name' => $device->tenant?->display_name,
                'logo_url'     => $device->tenant?->logo_url,
            ],
        ]);
    }
}
