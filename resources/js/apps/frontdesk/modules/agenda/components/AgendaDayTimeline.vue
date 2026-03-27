<template>
    <div class="rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="border-b border-slate-800 p-4">
            <h3 class="text-lg font-semibold text-white">Reservaties van de dag</h3>
            <p class="mt-1 text-sm text-slate-400">
                Elke rij is één reservatie. De balkkleur volgt de status.
            </p>
        </div>

        <div v-if="!registrations.length" class="p-6 text-sm text-slate-400">
            Geen reservaties voor deze dag.
        </div>

        <div v-else class="flex flex-col gap-3 p-4">
            <article
                v-for="registration in registrations"
                :key="registration.id"
                class="rounded-3xl border p-4"
                :class="[registration.status_color.bg, registration.status_color.border, registration.status_color.text]"
            >
                <div class="flex items-start gap-4">
                    <div class="mt-1 h-full min-h-16 w-1.5 shrink-0 rounded-full" :class="registration.status_color.accent" />

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-lg font-semibold text-white">
                                        {{ registration.name }}
                                    </h4>

                                    <span
                                        class="rounded-full border px-2.5 py-1 text-xs font-semibold"
                                        :class="registration.status_color.badge"
                                    >
                                        {{ registration.status_label }}
                                    </span>
                                </div>

                                <p class="mt-1 text-sm text-slate-200/80">
                                    {{ registration.event_time || 'Geen uur' }}
                                    <span v-if="registration.duration_label"> · {{ registration.duration_label }}</span>
                                    <span> · {{ registration.total_count }} personen</span>
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2 xl:justify-end">
                                <MetaTag v-if="registration.event_type" :label="`${registration.event_type_emoji || '🎉'} ${registration.event_type}`" />
                                <MetaTag v-if="registration.catering_option" :label="`${registration.catering_option_emoji || '🍽️'} ${registration.catering_option}`" />
                                <MetaTag :label="`👥 ${registration.participants_children}/${registration.participants_adults}/${registration.participants_supervisors}`" />
                                <MetaTag v-if="registration.invoice_requested" label="🧾 Factuur" />
                                <MetaTag v-if="registration.outside_opening_hours" label="🌙 Buiten openingsuren" />
                            </div>
                        </div>

                        <p v-if="registration.comment" class="mt-3 text-sm text-slate-100/80">
                            {{ registration.comment }}
                        </p>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>

<script setup>
import MetaTag from './MetaTag.vue'

defineProps({
    registrations: {
        type: Array,
        default: () => [],
    },
})
</script>
