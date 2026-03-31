<template>
    <section class="rounded-3xl border border-emerald-500/20 bg-gradient-to-br from-emerald-500/15 via-slate-900 to-slate-900 p-5 shadow-xl">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-emerald-200/90">Omzet</p>
                <div class="mt-3 flex items-end gap-2">
                    <span class="text-4xl font-bold text-white">{{ formattedTotal }}</span>
                </div>
                <p class="mt-2 text-sm text-emerald-100/80">Totale omzet van de dag</p>
            </div>

            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/15 p-3 text-emerald-200">
                <BanknotesIcon class="h-7 w-7" />
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue'
import { BanknotesIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    total: {
        type: Number,
        required: true,
    },
})

const formattedTotal = computed(() => {
    return new Intl.NumberFormat('nl-BE', {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(props.total || 0))
})
</script>
