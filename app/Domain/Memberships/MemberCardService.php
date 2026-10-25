<?php

namespace App\Domain\Memberships;

use App\Models\BadgeTemplate;
use App\Models\PhysicalCard;
use App\Models\TenantMembership;
use Illuminate\Support\Str;

/**
 * Abonnementskaarten (PhysicalCard, type member).
 *
 * - ensureCard(): zorgt dat een abonnement een kaart heeft, zodat die in
 *   Backoffice → Kaarten bij "Nog te printen" verschijnt.
 * - Het RFID-nummer van een abonnement en van zijn kaart worden gelijk
 *   gehouden (zie PhysicalCardController voor de richting kaart → abonnement).
 */
class MemberCardService
{
    /**
     * Kaart aanmaken als het abonnement er nog geen heeft (niet-geblokkeerd).
     */
    public function ensureCard(TenantMembership $membership, ?int $actorId = null): ?PhysicalCard
    {
        $existing = PhysicalCard::query()
            ->where('tenant_id', $membership->tenant_id)
            ->where('card_type', PhysicalCard::TYPE_MEMBER)
            ->where('holder_type', PhysicalCard::TYPE_MEMBER)
            ->where('holder_id', $membership->id)
            ->whereNotIn('status', [PhysicalCard::STATUS_BLOCKED, PhysicalCard::STATUS_RETIRED])
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $templateId = BadgeTemplate::query()
            ->where('tenant_id', $membership->tenant_id)
            ->where('template_type', PhysicalCard::TYPE_MEMBER)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');

        $account = $membership->account;

        return PhysicalCard::query()->create([
            'tenant_id'         => $membership->tenant_id,
            'card_type'         => PhysicalCard::TYPE_MEMBER,
            'badge_template_id' => $templateId,
            'holder_type'       => PhysicalCard::TYPE_MEMBER,
            'holder_id'         => $membership->id,
            'label'             => self::label($membership),
            'rfid_uid'          => $membership->rfid_uid
                ?: sprintf('TMP-MEMBER-%d-%d-%s', $membership->tenant_id, $membership->id, Str::upper(Str::random(8))),
            'status'            => PhysicalCard::STATUS_IN_CIRCULATION,
            'issued_at'         => now()->startOfDay(),
            'created_by'        => $actorId,
            'updated_by'        => $actorId,
        ]);
    }

    /** Eenvormig label voor abonnementskaarten. */
    public static function label(TenantMembership $membership): string
    {
        $name = trim(($membership->account?->first_name ?? '') . ' ' . ($membership->account?->last_name ?? ''));

        return sprintf('LID #%d - %s', $membership->id, $name !== '' ? $name : 'Onbekend');
    }

    public static function isTemporaryUid(?string $uid): bool
    {
        return $uid === null || $uid === '' || str_starts_with($uid, 'TMP-');
    }
}
