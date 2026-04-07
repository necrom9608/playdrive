<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\PhysicalCard;
use App\Support\CurrentTenant;
use App\Support\PhysicalCardRenderData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PhysicalCardPrintController extends Controller
{
    public function show(Request $request, CurrentTenant $currentTenant, PhysicalCard $card)
    {
        abort_unless((int) $card->tenant_id === (int) $currentTenant->id(), 404);

        $card->load(PhysicalCardRenderData::loadRelations());
        $renderData = PhysicalCardRenderData::build($card);
        $previewImageUrl = null;

        if (! blank($card->render_image_path) && Storage::disk('public')->exists($card->render_image_path)) {
            $previewImageUrl = Storage::disk('public')->url($card->render_image_path);
        }

        abort_unless($previewImageUrl, 404, 'Voor deze kaart is nog geen PNG render beschikbaar.');

        return response()
            ->view('frontdesk.print-card', [
                'card' => $card,
                'template' => $renderData['template'],
                'fields' => $renderData['fields'],
                'previewImageUrl' => $previewImageUrl,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
