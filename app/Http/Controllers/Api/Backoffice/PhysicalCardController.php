<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Api\Frontdesk\PhysicalCardController as FrontdeskPhysicalCardController;
use App\Models\PhysicalCard;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PhysicalCardController extends FrontdeskPhysicalCardController
{
    public function markPrinted(Request $request, CurrentTenant $currentTenant, PhysicalCard $card): JsonResponse
    {
        abort_unless((int) $card->tenant_id === (int) $currentTenant->id(), 404);

        $card->forceFill([
            'printed_at' => Carbon::now(),
            'updated_by' => $this->frontdeskUserId($request),
        ])->save();

        $card->load($this->detailRelations());

        return response()->json([
            'data' => [
                'card' => $this->transformCard($card),
                'print_url' => url('/backoffice/cards/' . $card->id . '/print'),
            ],
        ]);
    }

    protected function transformCard(PhysicalCard $card): array
    {
        $data = parent::transformCard($card);
        $data['print_url'] = url('/backoffice/cards/' . $card->id . '/print');

        return $data;
    }

    protected function frontdeskUserId(Request $request): ?int
    {
        return $request->attributes->get('backoffice_user')?->id;
    }
}
