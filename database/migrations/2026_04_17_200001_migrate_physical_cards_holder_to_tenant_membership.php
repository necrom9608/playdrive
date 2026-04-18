<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Zet physical_cards.holder_id (voor member-kaarten) om van members.id
 * naar tenant_memberships.id via de legacy_member_id brug.
 *
 * holder_type blijft 'member' — de applicatielaag weet nu dat dit
 * naar tenant_memberships wijst i.p.v. members.
 *
 * Kaarten zonder bijbehorende tenant_membership (kan niet voorkomen na
 * de vorige data-migratie, maar defensief afgehandeld) worden overgeslagen
 * en gelogd zodat ze manueel nagekeken kunnen worden.
 */
return new class extends Migration
{
    public function up(): void
    {
        $memberCards = DB::table('physical_cards')
            ->where('card_type', 'member')
            ->where('holder_type', 'member')
            ->whereNotNull('holder_id')
            ->get(['id', 'holder_id', 'tenant_id']);

        $skipped = [];

        foreach ($memberCards as $card) {
            $membership = DB::table('tenant_memberships')
                ->where('legacy_member_id', $card->holder_id)
                ->where('tenant_id', $card->tenant_id)
                ->first(['id']);

            if (! $membership) {
                $skipped[] = $card->id;
                continue;
            }

            DB::table('physical_cards')
                ->where('id', $card->id)
                ->update(['holder_id' => $membership->id]);
        }

        if (! empty($skipped)) {
            \Illuminate\Support\Facades\Log::warning(
                'physical_cards holder_id migratie: kaarten zonder tenant_membership gevonden.',
                ['card_ids' => $skipped]
            );
        }
    }

    public function down(): void
    {
        // Zet holder_id terug van tenant_membership.id naar members.id
        $memberCards = DB::table('physical_cards')
            ->where('card_type', 'member')
            ->where('holder_type', 'member')
            ->whereNotNull('holder_id')
            ->get(['id', 'holder_id', 'tenant_id']);

        foreach ($memberCards as $card) {
            $membership = DB::table('tenant_memberships')
                ->where('id', $card->holder_id)
                ->first(['legacy_member_id']);

            if (! $membership?->legacy_member_id) {
                continue;
            }

            DB::table('physical_cards')
                ->where('id', $card->id)
                ->update(['holder_id' => $membership->legacy_member_id]);
        }
    }
};
