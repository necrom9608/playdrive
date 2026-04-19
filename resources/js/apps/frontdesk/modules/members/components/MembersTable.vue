<template>
    <div class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="min-h-0 flex-1 overflow-auto">
            <table class="w-full min-w-[1200px] text-sm">
                <thead class="sticky top-0 bg-slate-900 text-slate-400">
                <tr>
                    <th class="px-5 py-3 text-left font-medium">ID</th>
                    <th class="px-5 py-3 text-left font-medium">Naam</th>
                    <th class="px-5 py-3 text-left font-medium">Geldig van</th>
                    <th class="px-5 py-3 text-left font-medium">Geldig tot</th>
                    <th class="px-5 py-3 text-left font-medium">Laatste bezoek</th>
                    <th class="px-5 py-3 text-left font-medium">Speeldagen</th>
                    <th class="px-5 py-3 text-left font-medium">Speeluren</th>
                    <th class="px-5 py-3 text-left font-medium">Resterend</th>
                    <th class="px-5 py-3 text-left font-medium">Status</th>
                </tr>
                </thead>

                <tbody>
                <tr v-if="!members.length">
                    <td colspan="9" class="px-5 py-10 text-center text-slate-500">Geen leden gevonden.</td>
                </tr>

                <tr
                    v-for="member in members"
                    :key="member.id"
                    class="cursor-pointer border-t border-slate-800 transition hover:bg-slate-800/30"
                    :class="[
                        member.id === selectedMemberId ? 'bg-slate-800/60' : '',
                        member.is_new ? 'border-l-2 border-l-cyan-500' : '',
                    ]"
                    @click="$emit('select', member.id)"
                >
                    <td class="px-5 py-4 align-top">
                        <div class="inline-flex rounded-xl border border-slate-700 bg-slate-800/80 px-3 py-1.5 font-semibold text-white">
                            #{{ member.id }}
                        </div>
                    </td>

                    <td class="px-5 py-4 align-top">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-white">{{ member.full_name }}</span>
                            <span v-if="member.is_new" class="inline-flex items-center rounded-full bg-cyan-500/15 border border-cyan-400/30 px-2 py-0.5 text-xs font-semibold text-cyan-300 animate-pulse">Nieuw</span>
                        </div>
                        <div class="mt-1 text-xs text-slate-500">{{ member.email || 'Geen e-mail' }}</div>
                    </td>

                    <td class="px-5 py-4 align-top text-slate-300">
                        {{ member.membership_started_label || '—' }}
                    </td>

                    <td class="px-5 py-4 align-top text-slate-300">
                        {{ member.membership_expires_label || '—' }}
                    </td>

                    <td class="px-5 py-4 align-top text-slate-300">
                        {{ member.last_played_label }}
                    </td>

                    <td class="px-5 py-4 align-top text-slate-300">
                        {{ member.play_days || 0 }}
                    </td>

                    <td class="px-5 py-4 align-top text-slate-300">
                        {{ member.play_hours_label || '0u 00m' }}
                    </td>

                    <td class="px-5 py-4 align-top">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="daysClass(member)">
                            {{ daysLabel(member) }}
                        </span>
                    </td>

                    <td class="px-5 py-4 align-top">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(member.status)">
                            {{ statusLabel(member.status) }}
                        </span>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-800 px-5 py-4">
            <slot name="actions" />
        </div>
    </div>
</template>

<script setup>
defineProps({
    members: { type: Array, default: () => [] },
    selectedMemberId: { type: Number, default: null },
})

defineEmits(['select'])

function statusLabel(status) {
    return {
        none:     'Geen abonnement',
        active:   'Actief',
        expiring: 'Vervalt binnenkort',
        expired:  'Vervallen',
        inactive: 'Inactief',
    }[status] ?? 'Onbekend'
}

function statusClass(status) {
    return {
        none:     'bg-cyan-500/15 text-cyan-300',
        active:   'bg-emerald-500/15 text-emerald-300',
        expiring: 'bg-amber-500/15 text-amber-300',
        expired:  'bg-rose-500/15 text-rose-300',
        inactive: 'bg-slate-500/15 text-slate-300',
    }[status] ?? 'bg-slate-500/15 text-slate-300'
}

function daysLabel(member) {
    if (member.status === 'none') return 'Geen abonnement'
    if (member.days_until_expiry === null || member.days_until_expiry === undefined) return '—'
    if (member.days_until_expiry < 0) return `${Math.abs(member.days_until_expiry)} dag(en) verlopen`
    if (member.days_until_expiry === 0) return 'Vervalt vandaag'
    return `${member.days_until_expiry} dag(en)`
}

function daysClass(member) {
    if (member.status === 'none') return 'bg-cyan-500/15 text-cyan-300'
    if (member.days_until_expiry === null || member.days_until_expiry === undefined) return 'bg-slate-500/15 text-slate-300'
    if (member.days_until_expiry < 0) return 'bg-rose-500/15 text-rose-300'
    if (member.days_until_expiry <= 30) return 'bg-amber-500/15 text-amber-300'
    return 'bg-emerald-500/15 text-emerald-300'
}
</script>
