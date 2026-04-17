<template>
    <div v-if="registrations.length > 0"
        class="rounded-3xl border border-cyan-500/20 bg-cyan-500/5 p-5 shadow-xl"
    >
        <!-- Header -->
        <div class="mb-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-2xl border border-cyan-400/30 bg-cyan-500/15">
                    <svg class="h-4 w-4 text-cyan-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-white">
                        Nieuwe registraties
                        <span class="ml-2 inline-flex items-center rounded-full bg-cyan-500/20 border border-cyan-400/30 px-2 py-0.5 text-xs font-semibold text-cyan-300">
                            {{ registrations.length }}
                        </span>
                    </h2>
                    <p class="text-xs text-slate-400">Geregistreerd via QR code — nog geen abonnement</p>
                </div>
            </div>
        </div>

        <!-- Lijst -->
        <div class="space-y-2">
            <div
                v-for="reg in registrations"
                :key="reg.membership_id"
                class="flex items-center justify-between gap-4 rounded-2xl border border-white/8 bg-white/4 px-4 py-3"
            >
                <div class="flex items-center gap-3 min-w-0">
                    <!-- Avatar -->
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-cyan-400/20 bg-cyan-500/10 text-xs font-bold text-cyan-300">
                        {{ initials(reg.full_name) }}
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-white truncate">{{ reg.full_name }}</p>
                            <span
                                v-if="reg.is_new"
                                class="inline-flex shrink-0 items-center rounded-full bg-cyan-500/15 border border-cyan-400/30 px-2 py-0.5 text-xs font-semibold text-cyan-300 animate-pulse"
                            >
                                Nieuw
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 truncate">{{ reg.email }}</p>
                        <p class="text-xs text-slate-500">{{ reg.registered_label }}</p>
                    </div>
                </div>

                <!-- Activeer knop -->
                <button
                    type="button"
                    :disabled="activating === reg.membership_id"
                    class="shrink-0 rounded-2xl bg-cyan-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-cyan-500 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="$emit('activate', reg)"
                >
                    <span v-if="activating === reg.membership_id" class="flex items-center gap-1.5">
                        <span class="h-3 w-3 animate-spin rounded-full border border-white/30 border-t-white"></span>
                        Bezig…
                    </span>
                    <span v-else>Abonnement activeren</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    registrations: { type: Array, default: () => [] },
    activating: { type: Number, default: null },
})

defineEmits(['activate'])

function initials(name) {
    return (name ?? '?').split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
}
</script>
