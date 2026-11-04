<?php

namespace App\Http\Controllers\Website;

use App\Domain\Purchases\Iban;
use App\Domain\Purchases\PrivatePurchaseService;
use App\Http\Controllers\Controller;
use App\Models\PrivatePurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Publieke pagina waar de verkoper (particulier) het aankoopborderel invult en ondertekent: /borderel/{token}.
 */
class PurchaseSignController extends Controller
{
    public function __construct(
        private readonly PrivatePurchaseService $service,
    ) {
    }

    public function show(string $token)
    {
        $purchase = $this->findByToken($token);

        if (! $purchase || $purchase->status === PrivatePurchase::STATUS_CANCELLED || $purchase->status === PrivatePurchase::STATUS_DRAFT) {
            return response()->view('purchase.sign', ['state' => 'invalid', 'purchase' => null, 'tenant' => $purchase?->tenant], 404);
        }

        $tenant = $purchase->tenant;

        if ($purchase->isSigned()) {
            return view('purchase.sign', ['state' => 'signed', 'purchase' => $purchase, 'tenant' => $tenant, 'token' => $token]);
        }

        if (! $purchase->linkIsActive()) {
            return response()->view('purchase.sign', ['state' => 'expired', 'purchase' => $purchase, 'tenant' => $tenant], 410);
        }

        if (! $purchase->opened_at) {
            $purchase->forceFill(['opened_at' => now()])->save();
        }

        return view('purchase.sign', [
            'state' => 'open',
            'purchase' => $purchase,
            'tenant' => $tenant,
            'token' => $token,
            'declarations' => PrivatePurchase::DECLARATIONS,
        ]);
    }

    public function submit(Request $request, string $token): JsonResponse
    {
        $purchase = $this->findByToken($token);

        if (! $purchase || ! $purchase->linkIsActive()) {
            return response()->json(['message' => $purchase?->isSigned()
                ? 'Dit borderel is al ondertekend.'
                : 'Deze link is niet meer geldig. Vraag een nieuwe link aan.'], 410);
        }

        $transfer = $purchase->payment_method === PrivatePurchase::PAYMENT_TRANSFER;

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'street' => ['required', 'string', 'max:190'],
            'postal_code' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'iban' => [$transfer ? 'required' : 'nullable', 'string', 'max:40'],
            'iban_holder' => [$transfer ? 'required' : 'nullable', 'string', 'max:190'],
            'accept_owner' => ['accepted'],
            'accept_private' => ['accepted'],
            'accept_price' => ['accepted'],
            'signature' => ['required', 'array'],
            'signature.w' => ['required', 'numeric', 'between:50,4000'],
            'signature.h' => ['required', 'numeric', 'between:50,4000'],
            'signature.strokes' => ['required', 'array', 'min:1', 'max:300'],
        ], [
            'first_name.required' => 'Vul je voornaam in.',
            'last_name.required' => 'Vul je achternaam in.',
            'street.required' => 'Vul je straat en nummer in.',
            'postal_code.required' => 'Vul je postcode in.',
            'city.required' => 'Vul je gemeente in.',
            'email.required' => 'Vul je e-mailadres in.',
            'email.email' => 'Dit e-mailadres is ongeldig.',
            'iban.required' => 'Vul je rekeningnummer in.',
            'iban_holder.required' => 'Vul in op wiens naam de rekening staat.',
            'accept_owner.accepted' => 'Bevestig alle verklaringen.',
            'accept_private.accepted' => 'Bevestig alle verklaringen.',
            'accept_price.accepted' => 'Bevestig alle verklaringen.',
            'signature.required' => 'Zet je handtekening.',
            'signature.strokes.required' => 'Zet je handtekening.',
        ]);

        $strokes = null;
        $validator->after(function ($v) use ($request, $transfer, &$strokes) {
            if ($transfer && $request->filled('iban') && ! Iban::isValid($request->input('iban'))) {
                $v->errors()->add('iban', 'Dit rekeningnummer klopt niet. Controleer het even.');
            }

            $strokes = $this->normalizeStrokes($request->input('signature.strokes'));
            if ($strokes === null) {
                $v->errors()->add('signature', 'Je handtekening is te kort. Teken opnieuw.');
            }
        });

        $data = $validator->validate();

        try {
            $signed = $this->service->sign(
                $purchase,
                $data,
                ['w' => round((float) $data['signature']['w'], 1), 'h' => round((float) $data['signature']['h'], 1), 'strokes' => $strokes],
                $request->ip(),
                $request->userAgent(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 410);
        } catch (Throwable $e) {
            Log::error('[PURCHASE] Ondertekenen mislukt', ['purchase_id' => $purchase->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Er ging iets mis bij het ondertekenen. Probeer opnieuw.'], 500);
        }

        return response()->json([
            'message' => 'Ondertekend.',
            'data' => [
                'first_name' => $signed->seller_first_name,
                'email' => $signed->mail_seller ? $signed->seller_email : null,
                'pdf_url' => url('/borderel/' . $token . '/pdf'),
                'signed_at' => $signed->signed_at?->copy()->setTimezone(PrivatePurchase::TIMEZONE)->format('d/m/Y H:i'),
            ],
        ]);
    }

    public function pdf(string $token): Response
    {
        $purchase = $this->findByToken($token);
        abort_unless($purchase && $purchase->isSigned(), 404);

        try {
            $binary = $this->service->pdfBinary($purchase);
        } catch (Throwable) {
            abort(404);
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->service->pdfFilename($purchase) . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function photo(string $token, int $index): Response
    {
        $purchase = $this->findByToken($token);
        abort_unless($purchase && $purchase->status !== PrivatePurchase::STATUS_CANCELLED, 404);

        $path = ($purchase->photos ?? [])[$index]['path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, max-age=3600']);
    }

    // ------------------------------------------------------------------

    private function findByToken(string $token): ?PrivatePurchase
    {
        if (! preg_match('/^[A-Za-z0-9]{32,64}$/', $token)) {
            return null;
        }

        $purchase = PrivatePurchase::query()->with(['tenant', 'creator:id,name'])->where('token', $token)->first();

        return ($purchase && $purchase->tenant?->is_active) ? $purchase : null;
    }

    /**
     * Strepen als [[[x, y], ...], ...] met getallen; afgerond op 1 decimaal.
     * Null als het geen bruikbare handtekening is.
     */
    private function normalizeStrokes(mixed $strokes): ?array
    {
        if (! is_array($strokes)) {
            return null;
        }

        $clean = [];
        $total = 0;
        $minX = $minY = PHP_FLOAT_MAX;
        $maxX = $maxY = -PHP_FLOAT_MAX;

        foreach ($strokes as $stroke) {
            if (! is_array($stroke)) {
                continue;
            }
            $points = [];
            foreach ($stroke as $point) {
                if (! is_array($point) || count($point) < 2 || ! is_numeric($point[0]) || ! is_numeric($point[1])) {
                    continue;
                }
                $x = round((float) $point[0], 1);
                $y = round((float) $point[1], 1);
                if ($x < -50 || $y < -50 || $x > 5000 || $y > 5000) {
                    continue;
                }
                $points[] = [$x, $y];
                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
            if ($points !== []) {
                $clean[] = $points;
                $total += count($points);
            }
            if ($total > 20000) {
                return null;
            }
        }

        // Minstens wat inkt: genoeg punten en niet één stip.
        if ($total < 8 || ($maxX - $minX) < 15) {
            return null;
        }

        return $clean;
    }
}
