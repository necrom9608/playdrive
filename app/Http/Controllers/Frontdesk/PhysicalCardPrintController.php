<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\PhysicalCard;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;

class PhysicalCardPrintController extends Controller
{
    private const PRINT_WIDTH = 1011;
    private const PRINT_HEIGHT = 638;

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

        $config = is_array($badgeTemplate->config_json) ? $badgeTemplate->config_json : [];
        $sourceWidth = max(1, (int) ($config['width'] ?? self::PRINT_WIDTH));
        $sourceHeight = max(1, (int) ($config['height'] ?? self::PRINT_HEIGHT));
        $scaleX = self::PRINT_WIDTH / $sourceWidth;
        $scaleY = self::PRINT_HEIGHT / $sourceHeight;

        $product = $card->voucherTemplate?->product;
        $fields = [
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

        $elements = collect($config['elements'] ?? [])
            ->sortBy('zIndex')
            ->values()
            ->map(function (array $element) use ($fields, $scaleX, $scaleY) {
                $type = (string) ($element['type'] ?? 'text');
                $source = (string) ($element['source'] ?? '');
                $fieldValue = $source !== '' ? ($fields[$source] ?? null) : null;
                $displayText = $type === 'field'
                    ? ($fieldValue ?: '{{ ' . ($source ?: 'veld') . ' }}')
                    : (($element['text'] ?? '') !== '' ? (string) $element['text'] : (string) ($element['label'] ?? ''));

                $fit = $element['fit'] ?? ($type === 'logo' ? 'contain' : 'cover');

                return [
                    'type' => $type,
                    'x' => $this->scale($element['x'] ?? 0, $scaleX),
                    'y' => $this->scale($element['y'] ?? 0, $scaleY),
                    'width' => max(1, $this->scale($element['width'] ?? 1, $scaleX)),
                    'height' => max(1, $this->scale($element['height'] ?? 1, $scaleY)),
                    'borderRadius' => $this->scale($element['borderRadius'] ?? 0, min($scaleX, $scaleY)),
                    'opacity' => max(0, min(1, (float) ($element['opacity'] ?? 1))),
                    'zIndex' => (int) ($element['zIndex'] ?? 1),
                    'backgroundColor' => $element['backgroundColor'] ?? 'transparent',
                    'color' => $element['color'] ?? '#ffffff',
                    'fontSize' => max(8, $this->scale($element['fontSize'] ?? 32, min($scaleX, $scaleY))),
                    'fontWeight' => (int) ($element['fontWeight'] ?? 700),
                    'textAlign' => $element['textAlign'] ?? 'left',
                    'imageUrl' => $element['imageUrl'] ?? '',
                    'fit' => $fit,
                    'displayText' => $displayText,
                    'label' => (string) ($element['label'] ?? ''),
                ];
            })
            ->all();

        $template = [
            'width' => self::PRINT_WIDTH,
            'height' => self::PRINT_HEIGHT,
            'backgroundColor' => $config['backgroundColor'] ?? '#ffffff',
            'backgroundImageUrl' => $config['backgroundImageUrl'] ?? '',
            'backgroundSize' => $config['backgroundSize'] ?? 'cover',
            'backgroundPosition' => $config['backgroundPosition'] ?? 'center',
            'elements' => $elements,
        ];

        return response()
            ->view('frontdesk.print-card', [
                'card' => $card,
                'template' => $template,
                'fields' => $fields,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    private function scale(mixed $value, float $factor): int
    {
        return (int) round(((float) $value) * $factor);
    }

    private function money(float|int|null $value): string
    {
        return '€ ' . number_format((float) ($value ?? 0), 2, ',', '.');
    }
}
