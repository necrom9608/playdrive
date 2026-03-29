<template>
    <div class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="border-b border-slate-800 px-5 py-4">
            <h3 class="text-base font-semibold text-white">Details</h3>
            <p class="mt-1 text-sm text-slate-400">Snel acties uitvoeren op een abonnement.</p>
        </div>

        <div v-if="member" class="min-h-0 flex-1 overflow-auto p-5">
            <div class="space-y-5">
                <div>
                    <div class="text-xl font-semibold text-white">{{ member.full_name }}</div>
                    <div class="mt-1 text-sm text-slate-400">{{ member.username || 'Geen login ingesteld' }}</div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <article class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-500">E-mail</div>
                        <div class="mt-2 text-sm text-slate-200">{{ member.email || 'Niet ingevuld' }}</div>
                    </article>
                    <article class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-500">RFID</div>
                        <div class="mt-2 text-sm text-slate-200">{{ member.rfid_uid || 'Niet gekoppeld' }}</div>
                    </article>
                    <article class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-500">Geldig van</div>
                        <div class="mt-2 text-sm text-slate-200">{{ member.membership_started_label || 'Onbekend' }}</div>
                    </article>
                    <article class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-500">Geldig tot</div>
                        <div class="mt-2 text-sm text-slate-200">{{ member.membership_expires_label || 'Onbekend' }}</div>
                    </article>
                </div>

                <article class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Adres</div>
                    <div class="mt-2 text-sm text-slate-200">{{ member.full_address || 'Niet ingevuld' }}</div>
                </article>

                <article class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Opmerking</div>
                    <div class="mt-2 whitespace-pre-line text-sm text-slate-200">{{ member.comment || 'Geen commentaar' }}</div>
                </article>

                <div class="grid gap-3">
                    <button
                        type="button"
                        class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500"
                        @click="$emit('edit', member)"
                    >
                        Abonnee bewerken
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500"
                        @click="$emit('renew', member)"
                    >
                        Abonnement 1 jaar verlengen
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
                        @click="$emit('send-email', { member, type: 'confirmation' })"
                    >
                        Bevestigingsmail sturen
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
                        @click="$emit('send-email', { member, type: 'expiring' })"
                    >
                        Mail bijna vervallen sturen
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
                        @click="$emit('send-email', { member, type: 'expired' })"
                    >
                        Mail vervallen sturen
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="flex flex-1 items-center justify-center p-8 text-center text-sm text-slate-500">
            Selecteer een abonnee om details te bekijken.
        </div>
    </div>
</template>

<script setup>
defineProps({
    member: {
        type: Object,
        default: null,
    },
})

defineEmits(['edit', 'renew', 'send-email'])
</script>
