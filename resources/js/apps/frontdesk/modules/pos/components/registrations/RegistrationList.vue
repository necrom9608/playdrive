<template>
    <div class="flex-1 overflow-y-auto bg-slate-900">
        <div class="flex min-h-full flex-col gap-2 p-3">
            <div class="grid grid-cols-[6px_minmax(260px,2fr)_52px_52px_52px_72px_90px] gap-2 rounded-2xl border border-slate-800 bg-slate-900/70 p-2 text-center text-xs font-semibold text-slate-300">
                <div></div>
                <div class="flex items-center justify-center rounded-xl bg-slate-800 px-3 py-2">
                    Naam
                </div>
                <div class="flex items-center justify-center rounded-xl bg-slate-800 text-[11px] font-semibold text-slate-400">
                    K
                </div>
                <div class="flex items-center justify-center rounded-xl bg-slate-800 text-[11px] font-semibold text-slate-400">
                    V
                </div>
                <div class="flex items-center justify-center rounded-xl bg-slate-800 text-[11px] font-semibold text-slate-300">
                    T
                </div>
                <div class="flex items-center justify-center rounded-xl bg-slate-800">
                    Van
                </div>
                <div class="flex items-center justify-center rounded-xl bg-slate-800">
                    Duur
                </div>
            </div>

            <RegistrationItem
                v-for="reservation in reservations"
                :key="reservation.id"
                :registration="reservation"
                :selected="selectedId === reservation.id"
                @select="$emit('select', $event)"
                @edit="$emit('edit', $event)"
            />

            <div
                v-if="reservations.length === 0"
                class="rounded-3xl border border-dashed border-slate-700 bg-slate-900 px-6 py-10 text-center shadow-sm"
            >
                <div class="text-base font-semibold text-slate-200">
                    Geen registraties gevonden
                </div>

                <div class="mt-1 text-sm text-slate-400">
                    Pas je filters aan of maak een nieuwe registratie aan.
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import RegistrationItem from './RegistrationItem.vue'

defineProps({
    reservations: {
        type: Array,
        required: true,
    },
    selectedId: {
        type: Number,
        default: null,
    },
})

defineEmits(['select', 'edit'])
</script>
