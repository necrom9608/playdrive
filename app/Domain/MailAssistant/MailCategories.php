<?php

namespace App\Domain\MailAssistant;

/**
 * Vaste categorieën van de mailassistent.
 * "locked" = altijd door een medewerker (wordt nooit automatisch verstuurd, ook later niet).
 */
final class MailCategories
{
    public const ALL = [
        'opening_hours' => ['label' => 'Openingsuren', 'hint' => 'Open op een dag, feestdagen, vakanties', 'locked' => false],
        'prices' => ['label' => 'Prijzen & formules', 'hint' => 'Wat kost …, wat zit erin', 'locked' => false],
        'birthday' => ['label' => 'Verjaardagsfeest', 'hint' => 'Feestjes voor kinderen', 'locked' => false],
        'groups' => ['label' => 'Groepen & teambuilding', 'hint' => 'Bedrijven, scholen, verenigingen', 'locked' => false],
        'booking' => ['label' => 'Reservatie & beschikbaarheid', 'hint' => 'Plaats op een datum, reservatie wijzigen', 'locked' => false],
        'vouchers' => ['label' => 'Vouchers', 'hint' => 'Saldo, geldigheid, kopen', 'locked' => false],
        'membership' => ['label' => 'Lidmaatschap', 'hint' => 'Lidkaart, verlengen, account', 'locked' => false],
        'other' => ['label' => 'Overige vragen', 'hint' => 'Niet in een categorie', 'locked' => false],
        'complaint' => ['label' => 'Klacht', 'hint' => 'Altijd door een medewerker', 'locked' => true],
        'cancellation' => ['label' => 'Annulering', 'hint' => 'Altijd door een medewerker', 'locked' => true],
        'refund' => ['label' => 'Terugbetaling', 'hint' => 'Altijd door een medewerker', 'locked' => true],
    ];

    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    public static function exists(?string $key): bool
    {
        return $key !== null && isset(self::ALL[$key]);
    }

    public static function label(?string $key): ?string
    {
        return $key ? (self::ALL[$key]['label'] ?? $key) : null;
    }

    public static function isLocked(?string $key): bool
    {
        return $key !== null && (self::ALL[$key]['locked'] ?? false);
    }

    public static function forFrontend(): array
    {
        return collect(self::ALL)
            ->map(fn (array $c, string $key) => ['key' => $key] + $c)
            ->values()
            ->all();
    }
}
