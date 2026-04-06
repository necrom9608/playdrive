<?php

namespace App\Support;

use App\Models\BadgeTemplate;
use App\Models\PhysicalCard;

class PhysicalCardRenderData
{
    public const PRINT_WIDTH = 1016;
    public const PRINT_HEIGHT = 638;

    public static function build(PhysicalCard $card): array
    {
        [$badgeTemplate, $fields] = self::resolveTemplateAndFields($card);

        if (! $badgeTemplate) {
            abort(422, 'Voor dit kaarttype is nog geen printtemplate gekoppeld.');
        }

        $config = is_array($badgeTemplate->config_json) ? $badgeTemplate->config_json : [];
        $sourceWidth = max(1, (int) ($config['width'] ?? self::PRINT_WIDTH));
        $sourceHeight = max(1, (int) ($config['height'] ?? self::PRINT_HEIGHT));
        $scaleX = self::PRINT_WIDTH / $sourceWidth;
        $scaleY = self::PRINT_HEIGHT / $sourceHeight;

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
                    'x' => self::scale($element['x'] ?? 0, $scaleX),
                    'y' => self::scale($element['y'] ?? 0, $scaleY),
                    'width' => max(1, self::scale($element['width'] ?? 1, $scaleX)),
                    'height' => max(1, self::scale($element['height'] ?? 1, $scaleY)),
                    'borderRadius' => self::scale($element['borderRadius'] ?? 0, min($scaleX, $scaleY)),
                    'opacity' => max(0, min(1, (float) ($element['opacity'] ?? 1))),
                    'zIndex' => (int) ($element['zIndex'] ?? 1),
                    'backgroundColor' => $element['backgroundColor'] ?? 'transparent',
                    'color' => $element['color'] ?? '#ffffff',
                    'fontSize' => max(8, self::scale($element['fontSize'] ?? 32, min($scaleX, $scaleY))),
                    'fontWeight' => (int) ($element['fontWeight'] ?? 700),
                    'textAlign' => $element['textAlign'] ?? 'left',
                    'imageUrl' => $element['imageUrl'] ?? '',
                    'fit' => $fit,
                    'displayText' => $displayText,
                    'label' => (string) ($element['label'] ?? ''),
                    'source' => $source,
                    'text' => (string) ($element['text'] ?? ''),
                ];
            })
            ->all();

        return [
            'template' => [
                'width' => self::PRINT_WIDTH,
                'height' => self::PRINT_HEIGHT,
                'backgroundColor' => $config['backgroundColor'] ?? '#ffffff',
                'backgroundImageUrl' => $config['backgroundImageUrl'] ?? '',
                'backgroundSize' => $config['backgroundSize'] ?? 'cover',
                'backgroundPosition' => $config['backgroundPosition'] ?? 'center',
                'elements' => $elements,
            ],
            'fields' => $fields,
            'badge_template_name' => $badgeTemplate->name,
        ];
    }

    public static function loadRelations(): array
    {
        return [
            'voucherTemplate:id,name,product_id,badge_template_id',
            'voucherTemplate.product:id,name,description,price_excl_vat,vat_rate',
            'voucherTemplate.badgeTemplate:id,name,template_type,config_json',
            'badgeTemplate:id,name,template_type,config_json',
            'staffHolder:id,name,username,email,rfid_uid',
            'memberHolder:id,first_name,last_name,login,email,rfid_uid',
        ];
    }

    private static function resolveTemplateAndFields(PhysicalCard $card): array
    {
        $type = $card->card_type ?: PhysicalCard::TYPE_VOUCHER;

        if ($type === PhysicalCard::TYPE_STAFF) {
            $staff = $card->staffHolder;
            $fields = [
                'full_name' => $staff?->name ?: ($card->label ?: 'Staff badge'),
                'first_name' => self::firstName($staff?->name),
                'last_name' => self::lastName($staff?->name),
                'role' => $staff?->is_admin ? 'Admin' : 'Medewerker',
                'badge_number' => $card->internal_reference ?: ('S-' . str_pad((string) $card->id, 5, '0', STR_PAD_LEFT)),
                'rfid_uid' => $card->rfid_uid,
            ];

            return [$card->badgeTemplate, $fields];
        }

        if ($type === PhysicalCard::TYPE_MEMBER) {
            $member = $card->memberHolder;
            $fullName = trim(implode(' ', array_filter([$member?->first_name, $member?->last_name])));
            $fields = [
                'full_name' => $fullName ?: ($card->label ?: 'Member badge'),
                'first_name' => $member?->first_name ?: self::firstName($fullName),
                'last_name' => $member?->last_name ?: self::lastName($fullName),
                'membership_type' => 'Member',
                'badge_number' => $card->internal_reference ?: ('M-' . str_pad((string) $card->id, 5, '0', STR_PAD_LEFT)),
                'valid_until' => '—',
            ];

            return [$card->badgeTemplate, $fields];
        }

        $badgeTemplate = $card->voucherTemplate?->badgeTemplate;
        $product = $card->voucherTemplate?->product;
        $fields = [
            'title' => $card->voucherTemplate?->name ?: ($product?->name ?: 'Cadeaubon'),
            'voucher_code' => $card->internal_reference ?: $card->label ?: ('CARD-' . $card->id),
            'voucher_value' => $product ? self::money($product->price_incl_vat) : 'Nog te bepalen',
            'valid_until' => 'Te activeren aan de balie',
            'description' => $product?->description ?: 'Herbruikbare fysieke voucherkaart.',
            'terms' => 'Herbruikbare kaart. De effectieve bon wordt geactiveerd bij verkoop.',
            'badge_number' => $card->internal_reference ?: ('K-' . str_pad((string) $card->id, 5, '0', STR_PAD_LEFT)),
            'rfid_uid' => $card->rfid_uid,
            'physical_card_label' => $card->label ?: 'Fysieke kaart',
            'template_name' => $badgeTemplate?->name,
            'product_name' => $product?->name,
        ];

        return [$badgeTemplate, $fields];
    }

    private static function scale(mixed $value, float $factor): int
    {
        return (int) round(((float) $value) * $factor);
    }

    private static function money(float|int|null $value): string
    {
        return '€ ' . number_format((float) ($value ?? 0), 2, ',', '.');
    }

    private static function firstName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        return $parts[0] ?? '';
    }

    private static function lastName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        array_shift($parts);

        return trim(implode(' ', $parts));
    }
}
