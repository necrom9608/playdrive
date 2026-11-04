<?php

namespace App\Domain\Purchases;

use App\Models\PrivatePurchase;
use App\Models\Tenant;
use App\Support\Pdf\SimplePdf;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Maakt de PDF van het aankoopborderel (A4), met de handtekening als vectorlijnen
 * en eventuele foto's als bijlage.
 */
class PurchaseDocumentPdf
{
    private const LEFT = 50.0;
    private const RIGHT = 545.0;
    private const BOTTOM_LIMIT = 760.0;

    private const INK = [15, 23, 42];
    private const MUTED = [71, 85, 105];
    private const LABEL = [100, 116, 139];
    private const BORDER = [226, 232, 240];

    private SimplePdf $pdf;

    private float $y = 0;

    public function build(PrivatePurchase $purchase, Tenant $tenant): string
    {
        $this->pdf = new SimplePdf();
        $this->pdf->setTitle('Aankoopborderel ' . $purchase->number);
        $this->pdf->addPage();

        $this->header($purchase, $tenant);
        $this->parties($purchase, $tenant);
        $this->items($purchase);
        $this->payment($purchase);
        $this->declaration($purchase);
        $this->signatures($purchase);
        $this->photos($purchase);
        $this->footers($purchase);

        return $this->pdf->output();
    }

    // ------------------------------------------------------------------

    private function header(PrivatePurchase $p, Tenant $tenant): void
    {
        $pdf = $this->pdf;
        $y = 72;

        $pdf->text(self::LEFT, $y, $tenant->name ?: $tenant->display_name, 18, true, self::INK);
        $y += 16;

        $lines = array_filter([
            $tenant->company_name && $tenant->company_name !== $tenant->name ? $tenant->company_name : null,
            trim(implode(' ', array_filter([$tenant->street, $tenant->number]))),
            trim(implode(' ', array_filter([$tenant->postal_code, $tenant->city]))),
            $tenant->vat_number ? 'Ondernemingsnr. ' . $tenant->vat_number : null,
            implode(' · ', array_filter([$tenant->email, $tenant->phone])),
        ]);
        foreach ($lines as $line) {
            $pdf->text(self::LEFT, $y, $line, 9.5, false, self::MUTED);
            $y += 13;
        }

        $pdf->text(self::RIGHT, 72, 'Aankoopborderel', 20, true, self::INK, 'right');
        $pdf->text(self::RIGHT, 90, 'Nr. ' . $p->number, 10, false, self::MUTED, 'right');
        $pdf->text(self::RIGHT, 104, 'Datum ' . $p->purchase_date?->format('d/m/Y'), 10, false, self::MUTED, 'right');

        $this->y = max($y, 118) + 6;
        $pdf->line(self::LEFT, $this->y, self::RIGHT, $this->y, 1.5, self::INK);
        $this->y += 22;
    }

    private function parties(PrivatePurchase $p, Tenant $tenant): void
    {
        $gap = 18;
        $width = (self::RIGHT - self::LEFT - $gap) / 2;

        $buyer = array_values(array_filter([
            $tenant->company_name ?: $tenant->display_name,
            trim(implode(' ', array_filter([$tenant->street, $tenant->number]))),
            trim(implode(' ', array_filter([$tenant->postal_code, $tenant->city]))),
            $tenant->vat_number,
        ]));

        $seller = array_values(array_filter([
            $p->sellerName(),
            $p->seller_street,
            trim(implode(' ', array_filter([$p->seller_postal_code, $p->seller_city]))),
            $p->seller_country && mb_strtolower($p->seller_country) !== 'belgië' ? $p->seller_country : null,
            $p->seller_email,
            $p->seller_phone,
        ]));

        $height = 26 + max(count($buyer), count($seller)) * 14 + 8;

        $this->box(self::LEFT, $this->y, $width, $height, 'KOPER', $buyer);
        $this->box(self::LEFT + $width + $gap, $this->y, $width, $height, 'VERKOPER (PARTICULIER)', $seller);

        $this->y += $height + 26;
    }

    private function box(float $x, float $y, float $w, float $h, string $label, array $lines): void
    {
        $this->pdf->rect($x, $y, $w, $h, null, self::BORDER, 0.8);
        $this->pdf->text($x + 12, $y + 18, $label, 7.5, true, self::LABEL);

        $ty = $y + 34;
        foreach ($lines as $i => $line) {
            foreach ($this->pdf->wrap($line, $w - 24, 10, $i === 0) as $part) {
                $this->pdf->text($x + 12, $ty, $part, 10, $i === 0, self::INK);
                $ty += 14;
            }
        }
    }

    private function items(PrivatePurchase $p): void
    {
        $colQty = 380.0;
        $colPrice = 460.0;
        $descWidth = 300.0;

        $this->itemsHeader($colQty, $colPrice);

        foreach ($p->items ?? [] as $item) {
            $lines = $this->pdf->wrap((string) ($item['description'] ?? ''), $descWidth, 10);
            $rowHeight = count($lines) * 13 + 12;

            if ($this->y + $rowHeight > self::BOTTOM_LIMIT) {
                $this->newPage();
                $this->itemsHeader($colQty, $colPrice);
            }

            $ty = $this->y + 15;
            foreach ($lines as $line) {
                $this->pdf->text(self::LEFT, $ty, $line, 10, false, self::INK);
                $ty += 13;
            }

            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);

            $this->pdf->text($colQty, $this->y + 15, $this->qty($qty), 10, false, self::INK, 'right');
            $this->pdf->text($colPrice, $this->y + 15, PrivatePurchase::money($price), 10, false, self::INK, 'right');
            $this->pdf->text(self::RIGHT, $this->y + 15, PrivatePurchase::money(round($qty * $price, 2)), 10, false, self::INK, 'right');

            $this->y += $rowHeight;
            $this->pdf->line(self::LEFT, $this->y, self::RIGHT, $this->y, 0.6, self::BORDER);
        }

        $this->ensureSpace(40);
        $this->y += 22;
        $this->pdf->text($colPrice, $this->y, 'Totaal aankoopprijs', 10, true, self::INK, 'right');
        $this->pdf->text(self::RIGHT, $this->y, PrivatePurchase::money($p->total_amount), 13, true, self::INK, 'right');
        $this->y += 30;
    }

    private function itemsHeader(float $colQty, float $colPrice): void
    {
        $this->pdf->text(self::LEFT, $this->y, 'OMSCHRIJVING', 7.5, true, self::LABEL);
        $this->pdf->text($colQty, $this->y, 'AANTAL', 7.5, true, self::LABEL, 'right');
        $this->pdf->text($colPrice, $this->y, 'PRIJS', 7.5, true, self::LABEL, 'right');
        $this->pdf->text(self::RIGHT, $this->y, 'TOTAAL', 7.5, true, self::LABEL, 'right');
        $this->y += 7;
        $this->pdf->line(self::LEFT, $this->y, self::RIGHT, $this->y, 1, self::INK);
    }

    private function payment(PrivatePurchase $p): void
    {
        $this->ensureSpace(60);
        $top = $this->y;

        $this->pdf->text(self::LEFT, $top, 'BETALING', 7.5, true, self::LABEL);
        $y = $top + 15;

        if ($p->payment_method === PrivatePurchase::PAYMENT_CASH) {
            $this->pdf->text(self::LEFT, $y, 'Contant', 10, false, self::INK);
            $y += 14;
        } else {
            $this->pdf->text(self::LEFT, $y, 'Per overschrijving naar ' . (PrivatePurchase::formatIban($p->seller_iban) ?? '—'), 10, false, self::INK);
            $y += 14;
            if ($p->seller_iban_holder) {
                $this->pdf->text(self::LEFT, $y, 'op naam van ' . $p->seller_iban_holder, 10, false, self::INK);
                $y += 14;
            }
        }

        $photoCount = count($p->photos ?? []);
        if ($photoCount > 0) {
            $x = self::LEFT + 320;
            $this->pdf->text($x, $top, 'BIJLAGEN', 7.5, true, self::LABEL);
            $this->pdf->text($x, $top + 15, $photoCount . ' foto' . ($photoCount === 1 ? '' : "'s") . ' van de goederen', 10, false, self::INK);
        }

        $this->y = $y + 16;
    }

    private function declaration(PrivatePurchase $p): void
    {
        $text = 'De verkoper verklaart eigenaar te zijn van bovenstaande goederen, dat deze vrij zijn van lasten, beslag of '
            . 'eigendomsvoorbehoud, dat hij verkoopt als particulier buiten elke beroeps- of handelsactiviteit, en dat hij '
            . 'akkoord gaat met de verkoop aan de vermelde prijs.';

        $lines = $this->pdf->wrap($text, self::RIGHT - self::LEFT - 28, 9.5);
        $height = 30 + count($lines) * 13 + 6;

        $this->ensureSpace($height + 10);

        $this->pdf->rect(self::LEFT, $this->y, self::RIGHT - self::LEFT, $height, [248, 250, 252]);
        $this->pdf->text(self::LEFT + 14, $this->y + 18, 'VERKLARING VERKOPER', 7.5, true, self::LABEL);

        $ty = $this->y + 34;
        foreach ($lines as $line) {
            $this->pdf->text(self::LEFT + 14, $ty, $line, 9.5, false, [30, 41, 59]);
            $ty += 13;
        }

        $this->y += $height + 24;
    }

    private function signatures(PrivatePurchase $p): void
    {
        $boxHeight = 90.0;
        $this->ensureSpace($boxHeight + 60);

        $gap = 24;
        $width = (self::RIGHT - self::LEFT - $gap) / 2;
        $tz = PrivatePurchase::TIMEZONE;
        $signedAt = $p->signed_at?->copy()->setTimezone($tz);

        // Verkoper
        $x = self::LEFT;
        $this->pdf->text($x, $this->y, 'HANDTEKENING VERKOPER', 7.5, true, self::LABEL);
        $this->drawSignature($p->signature ?? [], $x, $this->y + 8, $width, $boxHeight - 8);
        $this->pdf->line($x, $this->y + $boxHeight + 4, $x + $width, $this->y + $boxHeight + 4, 0.8, self::INK);
        $this->pdf->text($x, $this->y + $boxHeight + 18, implode(' · ', array_filter([
            $p->sellerName(),
            trim(($p->sign_place ? $p->sign_place . ', ' : '') . ($signedAt?->format('d/m/Y H:i') ?? '')),
        ])), 9, false, self::MUTED);

        // Koper
        $x = self::LEFT + $width + $gap;
        $this->pdf->text($x, $this->y, 'VOOR DE KOPER', 7.5, true, self::LABEL);
        $this->pdf->text($x, $this->y + $boxHeight - 8, 'Opgemaakt en goedgekeurd in Playdrive', 10, false, self::MUTED);
        $this->pdf->line($x, $this->y + $boxHeight + 4, $x + $width, $this->y + $boxHeight + 4, 0.8, self::INK);
        $this->pdf->text($x, $this->y + $boxHeight + 18, implode(' · ', array_filter([
            $p->creator?->name,
            $p->created_at?->copy()->setTimezone($tz)->format('d/m/Y H:i'),
        ])), 9, false, self::MUTED);

        $this->y += $boxHeight + 40;
    }

    /**
     * Teken de strepen van de handtekening, geschaald binnen het vak met behoud van verhouding.
     */
    private function drawSignature(array $signature, float $x, float $y, float $w, float $h): void
    {
        $strokes = $signature['strokes'] ?? [];
        if ($strokes === []) {
            return;
        }

        // Bounding box van de getekende punten, zodat een kleine handtekening het vak vult.
        $minX = $minY = PHP_FLOAT_MAX;
        $maxX = $maxY = -PHP_FLOAT_MAX;
        foreach ($strokes as $stroke) {
            foreach ($stroke as $point) {
                $minX = min($minX, (float) $point[0]);
                $maxX = max($maxX, (float) $point[0]);
                $minY = min($minY, (float) $point[1]);
                $maxY = max($maxY, (float) $point[1]);
            }
        }

        $srcW = max($maxX - $minX, 1);
        $srcH = max($maxY - $minY, 1);
        $pad = 6;
        $scale = min(($w - 2 * $pad) / $srcW, ($h - 2 * $pad) / $srcH);
        // Niet absurd uitvergroten (bv. een korte streep).
        $canvasW = (float) ($signature['w'] ?? $srcW);
        $scale = min($scale, ($w / max($canvasW, 1)) * 2.2);

        $offsetX = $x + ($w - $srcW * $scale) / 2;
        $offsetY = $y + ($h - $srcH * $scale) / 2;

        foreach ($strokes as $stroke) {
            $points = array_map(fn ($pt) => [
                $offsetX + ((float) $pt[0] - $minX) * $scale,
                $offsetY + ((float) $pt[1] - $minY) * $scale,
            ], $stroke);
            $this->pdf->polyline($points, 1.4, self::INK);
        }
    }

    private function photos(PrivatePurchase $p): void
    {
        $photos = $p->photos ?? [];
        if ($photos === [] || ! function_exists('imagecreatefromstring')) {
            return;
        }

        $maxW = self::RIGHT - self::LEFT;
        $maxH = 320.0;
        $slot = 0;

        foreach ($photos as $i => $photo) {
            $jpeg = $this->jpegFromStorage($photo['path'] ?? '');
            if (! $jpeg) {
                continue;
            }

            if ($slot % 2 === 0) {
                $this->newPage();
                $this->pdf->text(self::LEFT, $this->y, $slot === 0 ? "Bijlage: foto's van de goederen" : "Bijlage: foto's (vervolg)", 13, true, self::INK);
                $this->y += 22;
            }

            [$data, $pw, $ph] = $jpeg;
            $scale = min($maxW / $pw, $maxH / $ph);
            $w = $pw * $scale;
            $h = $ph * $scale;

            $this->pdf->jpeg($data, $pw, $ph, self::LEFT + ($maxW - $w) / 2, $this->y, $w, $h);
            $this->y += $h + 10;
            $this->pdf->text(self::LEFT, $this->y + 4, 'Foto ' . ($i + 1), 8.5, false, self::LABEL);
            $this->y += 22;
            $slot++;
        }
    }

    /** @return array{0: string, 1: int, 2: int}|null */
    private function jpegFromStorage(string $path): ?array
    {
        try {
            if ($path === '' || ! Storage::disk('local')->exists($path)) {
                return null;
            }

            $image = @imagecreatefromstring(Storage::disk('local')->get($path));
            if (! $image) {
                return null;
            }

            $w = imagesx($image);
            $h = imagesy($image);
            $max = 1400;
            $ratio = min(1, $max / max($w, $h));
            $nw = max(1, (int) round($w * $ratio));
            $nh = max(1, (int) round($h * $ratio));

            $canvas = imagecreatetruecolor($nw, $nh);
            imagefilledrectangle($canvas, 0, 0, $nw, $nh, imagecolorallocate($canvas, 255, 255, 255));
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);

            ob_start();
            imagejpeg($canvas, null, 82);
            $data = (string) ob_get_clean();
            imagedestroy($canvas);
            imagedestroy($image);

            return $data !== '' ? [$data, $nw, $nh] : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function footers(PrivatePurchase $p): void
    {
        $count = $this->pdf->pageCount();
        $tz = PrivatePurchase::TIMEZONE;
        $signedAt = $p->signed_at?->copy()->setTimezone($tz);

        $via = match ($p->sent_via) {
            'email' => 'link per e-mail verstuurd naar ' . $p->invite_email,
            'qr' => 'via QR-code aan de balie',
            default => null,
        };

        $line1 = implode(' · ', array_filter([
            'Elektronisch ondertekend via Playdrive',
            $signedAt ? $signedAt->format('d/m/Y H:i:s') . ' (' . $signedAt->format('T') . ')' : null,
            $p->signed_ip ? 'IP ' . $p->signed_ip : null,
            $via,
        ]));
        $hash = $p->document_hash ? trim(chunk_split(substr($p->document_hash, 0, 32), 4, ' ')) : null;
        $line2 = $hash ? 'Controlecode (SHA-256): ' . $hash : null;

        for ($i = 0; $i < $count; $i++) {
            $this->pdf->setPage($i);
            $top = self::BOTTOM_LIMIT + 32;
            $this->pdf->line(self::LEFT, $top, self::RIGHT, $top, 0.6, self::BORDER);

            $ty = $top + 13;
            foreach ($this->pdf->wrap($line1, self::RIGHT - self::LEFT - 70, 7.5) as $line) {
                $this->pdf->text(self::LEFT, $ty, $line, 7.5, false, self::LABEL);
                $ty += 10;
            }
            if ($line2) {
                $this->pdf->text(self::LEFT, $ty, $line2, 7.5, false, self::LABEL);
            }

            $this->pdf->text(self::RIGHT, $top + 13, $p->number . ' · ' . ($i + 1) . '/' . $count, 7.5, false, self::LABEL, 'right');
        }
    }

    // ------------------------------------------------------------------

    private function ensureSpace(float $height): void
    {
        if ($this->y + $height > self::BOTTOM_LIMIT) {
            $this->newPage();
        }
    }

    private function newPage(): void
    {
        $this->pdf->addPage();
        $this->y = 60;
    }

    private function qty(float $qty): string
    {
        return floor($qty) == $qty ? (string) (int) $qty : number_format($qty, 2, ',', '.');
    }
}
