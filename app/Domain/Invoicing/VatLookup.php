<?php

namespace App\Domain\Invoicing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Bedrijfsgegevens ophalen via VIES (btw-register van de EU).
 *
 *   GET https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{land}/vat/{nummer}
 *   → {isValid, userError, name, address: "Straat 1\n3000 Leuven", vatNumber}
 *
 * Geldige antwoorden worden 1 dag bewaard; VIES is soms traag of tijdelijk weg.
 */
class VatLookup
{
    private const URL = 'https://ec.europa.eu/taxation_customs/vies/rest-api/ms/%s/vat/%s';

    /** @throws RuntimeException met een melding voor de frontdesk */
    public static function lookup(string $vat): array
    {
        $vat = OrderInvoiceService::normalizeVat($vat);
        if (! $vat || ! preg_match('/^([A-Z]{2})([0-9A-Z]{2,13})$/', $vat, $m)) {
            throw new RuntimeException('Dat is geen geldig btw-nummer (bv. BE0123.456.789).');
        }
        [$country, $number] = [$m[1], $m[2]];
        $country = $country === 'GR' ? 'EL' : $country;

        return Cache::remember("vies:{$country}{$number}", now()->addDay(), function () use ($country, $number, $vat) {
            try {
                $response = Http::timeout(12)->connectTimeout(5)->acceptJson()->get(sprintf(self::URL, $country, $number));
            } catch (Throwable) {
                throw new RuntimeException('VIES is niet bereikbaar. Probeer het straks opnieuw of vul de gegevens zelf in.');
            }

            $data = $response->json();
            if (! is_array($data)) {
                throw new RuntimeException('VIES gaf geen geldig antwoord. Vul de gegevens zelf in.');
            }

            $status = (string) ($data['userError'] ?? '');
            if (! ($data['isValid'] ?? false)) {
                throw new RuntimeException(match ($status) {
                    'INVALID', 'INVALID_INPUT'          => 'Dit btw-nummer is niet geldig volgens VIES.',
                    'MS_UNAVAILABLE', 'SERVICE_UNAVAILABLE', 'TIMEOUT', 'MS_MAX_CONCURRENT_REQ', 'GLOBAL_MAX_CONCURRENT_REQ'
                                                        => 'Het btw-register van dit land antwoordt nu niet. Probeer het straks opnieuw.',
                    default                             => 'Btw-nummer niet gevonden in VIES.',
                });
            }

            return ['vat_number' => $vat, ...self::parse((string) ($data['name'] ?? ''), (string) ($data['address'] ?? ''))];
        });
    }

    /** "NV Voorbeeld" + "Brouwerijplein 1\n3000 Leuven" → naam, straat, postcode, gemeente */
    public static function parse(string $name, string $address): array
    {
        $clean = fn (string $v) => trim(preg_replace('/\s+/', ' ', $v)) ?: null;
        $name = $clean(str_replace('---', '', $name));

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', str_replace('---', '', $address)) ?: []), 'strlen'));
        $street = $postal = $city = null;

        if ($lines) {
            $last = array_pop($lines);
            if (preg_match('/^(?:[A-Z]{1,2}-)?(\d{4,5})\s+(.+)$/u', $last, $m)) {
                [$postal, $city] = [$m[1], $m[2]];
                $street = $clean(implode(', ', $lines));
            } elseif (preg_match('/^(.*\S)\s+(\d{4})\s+(\D.+)$/u', $last, $m)) {
                // Alles op één regel: "Straat 1 3000 Leuven"
                [$street, $postal, $city] = [$m[1], $m[2], $m[3]];
            } else {
                $street = $clean(implode(', ', [...$lines, $last]));
            }
        }

        return [
            'company_name' => $name,
            'address'      => $street ? $clean($street) : null,
            'postal_code'  => $postal,
            'city'         => $city ? mb_convert_case($clean($city), MB_CASE_TITLE) : null,
        ];
    }
}
