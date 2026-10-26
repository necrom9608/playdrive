<?php

namespace App\Domain\Arcade;

use App\Domain\Memberships\MemberCardService;
use App\Models\ArcadeCard;
use App\Models\ArcadePlayer;
use App\Models\PhysicalCard;
use App\Models\TenantMembership;

/**
 * Koppelt Asphyxia-spelers automatisch aan een Playdrive-account via de lidkaart.
 *
 * De frontdesk leest de UID van een kaart via PC/SC (FF CA 00 00 00) als hex.
 * cardbridge op de kast leest dezelfde UID en maakt er het kaart-id van dat de game
 * naar Asphyxia stuurt:
 *   - FeliCa (8 bytes IDm): ongewijzigd
 *   - MIFARE/NTAG (4/7/10 bytes): E0 04 + eerste 6 bytes, extra bytes erin ge-XOR'd
 * Die omzetting moet exact gelijk blijven aan cardbridge/src/main.c (handle_card).
 */
class ArcadeCardMatcher
{
    /** Mogelijke Asphyxia-kaart-id's voor een UID zoals Playdrive hem bewaart. */
    public static function cardIdsForUid(?string $uid): array
    {
        if (MemberCardService::isTemporaryUid($uid)) {
            return [];
        }
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) $uid));
        if ($hex === '' || strlen($hex) % 2 !== 0) {
            return [];
        }
        $bytes = array_map('hexdec', str_split($hex, 2));
        $len = count($bytes);
        if ($len < 4 || $len > 10) {
            return [];
        }

        if ($len === 8) {
            return [$hex]; // FeliCa IDm
        }

        $id = [0xE0, 0x04, 0, 0, 0, 0, 0, 0];
        for ($i = 0; $i < $len && $i < 6; $i++) {
            $id[2 + $i] = $bytes[$i];
        }
        for ($i = 6; $i < $len; $i++) {
            $id[2 + ($i % 6)] ^= $bytes[$i];
        }

        return [strtoupper(implode('', array_map(fn ($b) => sprintf('%02X', $b), $id)))];
    }

    /**
     * Kaart-id (Asphyxia) → account_id, voor alle lidkaarten van een tenant.
     *
     * @return array<string,int>
     */
    public static function accountMap(int $tenantId): array
    {
        $map = [];

        TenantMembership::query()
            ->without('account')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('rfid_uid')
            ->where('rfid_uid', 'not like', 'TMP-%')
            ->get(['account_id', 'rfid_uid'])
            ->each(function ($m) use (&$map) {
                foreach (self::cardIdsForUid($m->rfid_uid) as $cid) {
                    $map[$cid] = (int) $m->account_id;
                }
            });

        PhysicalCard::query()
            ->where('tenant_id', $tenantId)
            ->where('card_type', PhysicalCard::TYPE_MEMBER)
            ->where('holder_type', PhysicalCard::TYPE_MEMBER)
            ->whereNotIn('status', [PhysicalCard::STATUS_BLOCKED, PhysicalCard::STATUS_RETIRED])
            ->whereNotNull('rfid_uid')
            ->where('rfid_uid', 'not like', 'TMP-%')
            ->with('memberHolder:id,account_id')
            ->get(['id', 'holder_id', 'rfid_uid'])
            ->each(function ($card) use (&$map) {
                $accountId = $card->memberHolder?->account_id;
                if (! $accountId) {
                    return;
                }
                foreach (self::cardIdsForUid($card->rfid_uid) as $cid) {
                    $map[$cid] ??= (int) $accountId;
                }
            });

        return $map;
    }

    /**
     * Niet-gekoppelde spelers van een tenant koppelen als een van hun kaarten een lidkaart is.
     * Spelers die zelf ontkoppelden (linked_via = unlinked) blijven met rust.
     */
    public static function autoLink(int $tenantId): int
    {
        $playerIds = ArcadePlayer::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('account_id')
            ->where(fn ($q) => $q->whereNull('linked_via')->orWhere('linked_via', '!=', ArcadePlayer::LINK_UNLINKED))
            ->pluck('id');

        if ($playerIds->isEmpty()) {
            return 0;
        }

        $cards = ArcadeCard::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('arcade_player_id', $playerIds)
            ->get(['arcade_player_id', 'cid']);

        if ($cards->isEmpty()) {
            return 0;
        }

        $map = self::accountMap($tenantId);
        $linked = 0;

        foreach ($cards as $card) {
            $accountId = $map[strtoupper($card->cid)] ?? null;
            if (! $accountId) {
                continue;
            }
            $linked += ArcadePlayer::query()
                ->whereKey($card->arcade_player_id)
                ->whereNull('account_id')
                ->update([
                    'account_id' => $accountId,
                    'linked_via' => ArcadePlayer::LINK_CARD,
                    'linked_at'  => now(),
                ]);
        }

        return $linked;
    }
}
