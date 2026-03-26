<template>
    <FormCard title="Details" class="h-full">
        <div class="space-y-5">
            <div>
                <label class="mb-2 block text-xs font-medium text-slate-400">
                    Type event
                </label>

                <select
                    v-model="model.event_type"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-medium text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                >
                    <option value="free_play">Vrij spelen</option>
                    <option value="birthday">Verjaardag</option>
                    <option value="group_visit">Groepsbezoek</option>
                    <option value="team_building">Teambuilding</option>
                    <option value="school">School</option>
                    <option value="other">Andere</option>
                </select>
            </div>

            <div>
                <label class="mb-2 block text-xs font-medium text-slate-400">
                    Catering
                </label>

                <select
                    v-model="model.catering_option"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-medium text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                >
                    <option value="none">Geen</option>
                    <option value="drinks">Drank</option>
                    <option value="snacks">Snacks</option>
                    <option value="pancakes">Pannenkoeken</option>
                    <option value="pizza">Pizza</option>
                    <option value="custom">Aangepast</option>
                </select>
            </div>

            <div>
                <label class="mb-2 block text-xs font-medium text-slate-400">
                    Status
                </label>

                <select
                    v-model="model.status"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-medium text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                >
                    <option value="new">Nieuw</option>
                    <option value="confirmed">Gereserveerd</option>
                    <option value="checked_in">Ingecheckt</option>
                    <option value="checked_out">Uitgecheckt</option>
                    <option value="paid">Betaald</option>
                    <option value="cancelled">Geannuleerd</option>
                    <option value="no_show">No-show</option>
                </select>
            </div>

            <div>
                <label class="mb-2 block text-xs font-medium text-slate-400">
                    Commentaar
                </label>

                <textarea
                    v-model="model.comment"
                    rows="5"
                    placeholder="Extra info, opmerkingen of afspraken..."
                    class="w-full resize-none rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                />
            </div>
        </div>
    </FormCard>
</template>

<script setup>
import FormCard from '../fields/FormCard.vue'

const model = defineModel({ type: Object, required: true })
</script>
