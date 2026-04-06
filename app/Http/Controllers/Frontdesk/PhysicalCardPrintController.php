<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\PhysicalCard;
use App\Support\CurrentTenant;
use App\Support\PhysicalCardRenderData;
use Illuminate\Http\Request;

class PhysicalCardPrintController extends Controller
{
    public function show(Request $request, CurrentTenant $currentTenant, PhysicalCard $card)
    {
        abort_unless((int) $card->tenant_id === (int) $currentTenant->id(), 404);

        $card->load(PhysicalCardRenderData::loadRelations());
        $renderData = PhysicalCardRenderData::build($card);

        return response()
            ->view('frontdesk.print-card', [
                'card' => $card,
                'template' => $renderData['template'],
                'fields' => $renderData['fields'],
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
