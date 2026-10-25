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
                <tr v-if="!orderedMembers.length">
                    <td colspan="9" class="px-5 py-10 text-center text-slate-500">Geen leden gevonden.</td>
                </tr>

                <tr
                    v-for="row in orderedMembers"
                    :key="row.member.id"
                    class="cursor-pointer border-t border-slate-800 transition hover:bg-slate-800/30"
                    :class="[
                        row.member.id === selectedMemberId ? 'bg-slate-800/60' : (row.nested ? 'bg-slate-950/40' : ''),
                        row.member.is_new ? 'border-l-2 border-l-cyan-500' : '',
                    ]"
                    @click="$emit('select', row.member.id)"
                >
                    <td class="px-5 py-4 align-top">
                        <div v-if="row.nested" class="flex items-center gap-2 pl-3 text-slate-500">
                            <span>{{ row.last ? '└' : '├' }}</span>
                            <span class="text-xs">#{{ row.member.id }}</span>
                        </div>
                        <div v-else class="inline-flex rounded-xl border border-slate-700 bg-slate-800/80 px-3 py-1.5 font-semibold text-white">
                            #{{ row.member.id }}
                        </div>
                    </td>

                    <td class="px-5 py-4 align-top">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-white">{{ row.member.full_name }}</span>
                            <span
                                v-if="row.member.is_family_head"
                                class="inline-flex items-center gap-1 rounded-full bg-violet-500/15 px-2 py-0.5 text-xs font-semibold text-violet-300"
                            >
                                <UserGroupIcon class="h-3.5 w-3.5" />
                                Gezin · {{ (row.member.family_member_count ?? 0) + 1 }} personen
                            </span>
                            <span
                                v-else-if="row.member.family_membership_id && !row.nested"
                                class="inline-flex items-center rounded-full bg-violet-500/10 px-2 py-0.5 text-xs font-medium text-violet-300"
                            >
                                via {{ row.member.family_name || 'gezin' }}
                            </span>
                            <span
                                v-if="row.member.family_membership_id && row.member.membership_type === 'student'"
                                class="inline-flex items-center rounded-full bg-sky-500/15 px-2 py-0.5 text-[11px] font-medium text-sky-300"
                            >
                                kind/student
                            </span>
                            <span v-if="row.member.is_new" class="inline-flex items-center rounded-full bg-cyan-500/15 border border-cyan-400/30 px-2 py-0.5 text-xs font-semibold text-cyan-300 animate-pulse">Nieuw</span>
                        </div>
                        <div class="mt-1 text-xs text-slate-500">{{ displayEmail(row.member.email) }}</div>
                    </td>

                    <td class="px-5 py-4 align-top text-slate-300">
                        {{ row.member.membership_started_label || '—' }}
                    </td>

                    <td class="px-5 py-4 align-top text-slate-300">
                        {{ row.member.membership_expires_label || '—' }}
                    </td>

                    <td class="px-5 py-4 align-top text-slate-300">
                        {{ row.member.last_played_label }}
                    </td>

                    <td class="px-5 py-4 align-top text-slate-300">
                        {{ row.member.play_days || 0 }}
                    </td>

                    <td class="px-5 py-4 align-top text-slate-300">
                        {{ row.member.play_hours_label || '0u 00m' }}
                    </td>

                    <td class="px-5 py-4 align-top">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="daysClass(row.member)">
                            {{ daysLabel(row.member) }}
                        </span>
                    </td>

                    <td class="px-5 py-4 align-top">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(row.member.status)">
                            {{ statusLabel(row.member.status) }}
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
import { UserGroupIcon } from '@heroicons/vue/24/outline'
import { computed } from 'vue'

const props = defineProps({
    members: { type: Array, default: () => [] },
    selectedMemberId: { type: Number, default: null },
})

/**
 * Gezinsleden direct onder hun hoofdabonnement tonen.
 * Gezinsleden waarvan het hoofd niet in de (gefilterde) lijst staat,
 * worden gewoon op hun eigen plek getoond met „via gezin”.
 */
const orderedMembers = computed(() => {
    const ids = new Set(props.members.map(m => m.id))
    const byFamily = new Map()

    props.members.forEach((m) => {
        if (m.family_membership_id && ids.has(m.family_membership_id)) {
            if (!byFamily.has(m.family_membership_id)) byFamily.set(m.family_membership_id, [])
            byFamily.get(m.family_membership_id).push(m)
        }
    })

    const rows = []

    props.members.forEach((m) => {
        if (m.family_membership_id && ids.has(m.family_membership_id)) return

        rows.push({ member: m, nested: false, last: false })

        const children = byFamily.get(m.id) ?? []
        children.forEach((child, index) => {
            rows.push({ member: child, nested: true, last: index === children.length - 1 })
        })
    })

    return rows
})

function displayEmail(email) {
    if (!email || email.includes('@migrated.local') || email.includes('@playdrive.local')) return 'Geen e-mail'
    return email
}

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
