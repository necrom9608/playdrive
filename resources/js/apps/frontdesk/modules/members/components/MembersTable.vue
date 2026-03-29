<template>
    <div class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="border-b border-slate-800 px-5 py-4">
            <h3 class="text-base font-semibold text-white">Abonnees</h3>
            <p class="mt-1 text-sm text-slate-400">Overzicht van alle abonnementen.</p>
        </div>

        <div class="min-h-0 flex-1 overflow-auto">
            <table class="w-full min-w-[980px] text-sm">
                <thead class="sticky top-0 bg-slate-900 text-slate-400">
                <tr>
                    <th class="px-5 py-3 text-left font-medium">Naam</th>
                    <th class="px-5 py-3 text-left font-medium">Contact</th>
                    <th class="px-5 py-3 text-left font-medium">RFID</th>
                    <th class="px-5 py-3 text-left font-medium">Geldig tot</th>
                    <th class="px-5 py-3 text-left font-medium">Status</th>
                    <th class="px-5 py-3 text-right font-medium">Acties</th>
                </tr>
                </thead>

                <tbody>
                <tr v-if="!members.length">
                    <td colspan="6" class="px-5 py-10 text-center text-slate-500">
                        Geen abonnees gevonden.
                    </td>
                </tr>

                <tr
                    v-for="member in members"
                    :key="member.id"
                    class="border-t border-slate-800 transition hover:bg-slate-800/30"
                    :class="member.id === selectedMemberId ? 'bg-slate-800/60' : ''"
                    @click="$emit('select', member.id)"
                >
                    <td class="px-5 py-4 align-top">
                        <div class="font-semibold text-white">{{ member.full_name }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ member.username || 'Geen login' }}</div>
                    </td>
                    <td class="px-5 py-4 align-top text-slate-300">
                        <div>{{ member.email || 'Geen e-mail' }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ member.full_address || 'Geen adres' }}</div>
                    </td>
                    <td class="px-5 py-4 align-top text-slate-300">
                        {{ member.rfid_uid || 'Niet gekoppeld' }}
                    </td>
                    <td class="px-5 py-4 align-top text-slate-300">
                        <div>{{ member.membership_expires_label || 'Onbekend' }}</div>
                        <div class="mt-1 text-xs text-slate-500">
                            <template v-if="member.days_until_expiry === null">
                                Geen einddatum
                            </template>
                            <template v-else-if="member.days_until_expiry < 0">
                                {{ Math.abs(member.days_until_expiry) }} dagen vervallen
                            </template>
                            <template v-else>
                                Nog {{ member.days_until_expiry }} dagen
                            </template>
                        </div>
                    </td>
                    <td class="px-5 py-4 align-top">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                              :class="statusClass(member.status)">
                            {{ statusLabel(member.status) }}
                        </span>
                    </td>
                    <td class="px-5 py-4 align-top text-right">
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-slate-700"
                                @click.stop="$emit('edit', member)"
                            >
                                Bewerken
                            </button>
                            <button
                                type="button"
                                class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-500"
                                @click.stop="$emit('renew', member)"
                            >
                                Verlengen
                            </button>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
defineProps({
    members: {
        type: Array,
        default: () => [],
    },
    selectedMemberId: {
        type: Number,
        default: null,
    },
})

defineEmits(['select', 'edit', 'renew'])

function statusLabel(status) {
    return {
        active: 'Actief',
        expiring: 'Vervalt binnenkort',
        expired: 'Vervallen',
        inactive: 'Inactief',
    }[status] ?? status
}

function statusClass(status) {
    return {
        active: 'bg-emerald-500/15 text-emerald-300',
        expiring: 'bg-amber-500/15 text-amber-300',
        expired: 'bg-rose-500/15 text-rose-300',
        inactive: 'bg-slate-500/15 text-slate-300',
    }[status] ?? 'bg-slate-500/15 text-slate-300'
}
</script>
