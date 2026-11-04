<?php

namespace App\Domain\Purchases;

/**
 * IBAN-controle (formaat + mod-97). Geen bankdatabank: enkel of het nummer klopt.
 */
class Iban
{
    public static function normalize(?string $iban): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $iban));
    }

    public static function isValid(?string $iban): bool
    {
        $iban = self::normalize($iban);

        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
            return false;
        }

        // Belgische nummers zijn altijd 16 tekens.
        if (str_starts_with($iban, 'BE') && strlen($iban) !== 16) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        // mod 97 in stukken, zonder bcmath.
        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ($remainder . $chunk) % 97;
        }

        return $remainder === 1;
    }
}
