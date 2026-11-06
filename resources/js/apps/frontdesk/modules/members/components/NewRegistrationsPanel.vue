<template>
    <div v-if="registrations.length > 0"
        class="rounded-3xl border border-cyan-500/20 bg-cyan-500/5 p-5 shadow-xl"
    >
        <!-- Header -->
        <div class="mb-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-2xl border border-cyan-400/30 bg-cyan-500/15">
                    <UserPlusIcon class="h-4 w-4 text-cyan-300" />
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-white">
                        Nieuwe leden
                        <span class="ml-2 inline-flex items-center rounded-full bg-cyan-500/20 border border-cyan-400/30 px-2 py-0.5 text-xs font-semibold text-cyan-300">
                            {{ registrations.length }}
                        </span>
                    </h2>
                    <p class="text-xs text-slate-400">Laatste 7 dagen, nog geen abonnement · scan de QR op de gsm van de klant in het zoekveld</p>
                </div>
            </div>
        </div>

        <!-- Lijst -->
        <div class="space-y-2">
            <div
                v-for="reg in registrations"
                :key="reg.membership_id"
                class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border px-4 py-3"
                :class="reg.is_new ? 'border-cyan-400/30 bg-cyan-500/8' : 'border-white/8 bg-white/4'"
            >
                <div class="flex items-center gap-3 min-w-0">
                    <!-- Avatar -->
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-cyan-400/20 bg-cyan-500/10 text-xs font-bold text-cyan-300">
                        {{ initials(reg.full_name) }}
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-white truncate">{{ reg.full_name }}</p>
                            <span
                                v-if="reg.is_new"
                                class="inline-flex shrink-0 items-center rounded-full bg-cyan-500/15 border border-cyan-400/30 px-2 py-0.5 text-xs font-semibold text-cyan-300"
                            >Nieuw</span>
                            <span
                                v-if="reg.existing_account"
                                class="inline-flex shrink-0 items-center rounded-full bg-violet-500/10 border border-violet-400/30 px-2 py-0.5 text-xs font-semibold text-violet-200"
                            >Bestaand Playdrive-account</span>
                            <span
                                v-else-if="!reg.email_verified"
                                class="inline-flex shrink-0 items-center rounded-full bg-amber-500/10 border border-amber-400/30 px-2 py-0.5 text-xs font-semibold text-amber-200"
                            >E-mail niet bevestigd</span>
                            <span
                                v-else
                                class="inline-flex shrink-0 items-center rounded-full bg-emerald-500/10 border border-emerald-400/30 px-2 py-0.5 text-xs font-semibold text-emerald-200"
                            >Bevestigd</span>
                        </div>
                        <p class="text-xs text-slate-400 truncate">
                            {{ reg.email }} · {{ reg.registered_label }}
                            <template v-if="reg.children?.length"> · + {{ childrenLabel(reg.children) }}</template>
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-slate-700"
                        :disabled="busyId === reg.membership_id"
                        @click="$emit('seen', reg)"
                    >Gezien</button>
                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-slate-700"
                        @click="$emit('open', reg)"
                    >Fiche openen</button>
                    <button
                        type="button"
                        :disabled="activating === reg.membership_id"
                        class="rounded-2xl bg-cyan-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-cyan-500 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="$emit('activate', reg)"
                    >
                        <span v-if="activating === reg.membership_id" class="flex items-center gap-1.5">
                            <span class="h-3 w-3 animate-spin rounded-full border border-white/30 border-t-white"></span>
                            Bezig…
                        </span>
                        <span v-else>Abonnement toevoegen</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { UserPlusIcon } from '@heroicons/vue/24/outline'

defineProps({
    registrations: { type: Array, default: () => [] },
    activating: { type: Number, default: null },
    busyId: { type: Number, default: null },
})

defineEmits(['activate', 'seen', 'open'])

function initials(name) {
    return (name ?? '?').split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase() || '?'
}

function childrenLabel(children) {
    const names = children.map(c => c.age != null ? `${c.first_name} (${c.age})` : c.first_name)
    return `${children.length === 1 ? '1 kind' : `${children.length} kinderen`}: ${names.join(', ')}`
}
</script>
