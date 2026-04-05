<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\PhysicalCard;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;

class PhysicalCardPrintController extends Controller
{
    public function show(Request $request, CurrentTenant $currentTenant, PhysicalCard $card)
    {
        abort_unless((int) $card->tenant_id === (int) $currentTenant->id(), 404);

        $card->load([
            'voucherTemplate:id,name,product_id,badge_template_id',
            'voucherTemplate.product:id,name,description,price_excl_vat,vat_rate',
            'voucherTemplate.badgeTemplate:id,name,config_json',
        ]);

        $badgeTemplate = $card->voucherTemplate?->badgeTemplate;
        abort_if(! $badgeTemplate, 422, 'Voor dit kaarttype is nog geen printtemplate gekoppeld.');

        $config = $badgeTemplate->config_json ?? [];
        $template = [
            'width' => (int) ($config['width'] ?? 1016),
            'height' => (int) ($config['height'] ?? 638),
            'backgroundColor' => $config['backgroundColor'] ?? '#111827',
            'backgroundImageUrl' => $config['backgroundImageUrl'] ?? '',
            'backgroundSize' => $config['backgroundSize'] ?? 'cover',
            'backgroundPosition' => $config['backgroundPosition'] ?? 'center',
            'elements' => collect($config['elements'] ?? [])->sortBy('zIndex')->values()->all(),
        ];

        $product = $card->voucherTemplate?->product;
        $data = [
            'title' => $card->voucherTemplate?->name ?: ($product?->name ?: 'Cadeaubon'),
            'voucher_code' => $card->internal_reference ?: $card->label ?: ('CARD-' . $card->id),
            'voucher_value' => $product ? $this->money($product->price_incl_vat) : 'Nog te bepalen',
            'valid_until' => 'Te activeren aan de balie',
            'description' => $product?->description ?: 'Herbruikbare fysieke voucherkaart.',
            'terms' => 'Herbruikbare kaart. De effectieve bon wordt geactiveerd bij verkoop.',
            'badge_number' => $card->internal_reference ?: ('K-' . str_pad((string) $card->id, 5, '0', STR_PAD_LEFT)),
            'rfid_uid' => $card->rfid_uid,
            'physical_card_label' => $card->label ?: 'Fysieke kaart',
            'template_name' => $badgeTemplate->name,
            'product_name' => $product?->name,
        ];

        return response()
            ->view('frontdesk.print-card', [
                'card' => $card,
                'template' => $template,
                'fields' => $data,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    private function money(float|int|null $value): string
    {
        return '€ ' . number_format((float) ($value ?? 0), 2, ',', '.');
    }
}
