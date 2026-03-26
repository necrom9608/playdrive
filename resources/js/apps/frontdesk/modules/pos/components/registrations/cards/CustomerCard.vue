<template>
    <FormCard title="Klant" class="h-full">
        <div class="space-y-5">
            <div>
                <label class="mb-2 block text-xs font-medium text-slate-400">
                    Naam
                </label>

                <input
                    v-model="model.name"
                    type="text"
                    autofocus
                    autocomplete="name"
                    placeholder="Bijv. Jansen"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                >
            </div>

            <div>
                <label class="mb-2 block text-xs font-medium text-slate-400">
                    Telefoon
                </label>

                <input
                    v-model="model.phone"
                    type="text"
                    autocomplete="tel"
                    placeholder="Bijv. 0470 12 34 56"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                >
            </div>

            <div>
                <label class="mb-2 block text-xs font-medium text-slate-400">
                    E-mail
                </label>

                <input
                    v-model="model.email"
                    type="email"
                    autocomplete="email"
                    placeholder="Bijv. klant@email.be"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                >
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-2 block text-xs font-medium text-slate-400">
                        Postcode
                    </label>

                    <input
                        v-model="model.postal_code"
                        type="text"
                        placeholder="8790"
                        class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                    >
                </div>

                <div>
                    <label class="mb-2 block text-xs font-medium text-slate-400">
                        Gemeente
                    </label>

                    <input
                        v-model="model.municipality"
                        type="text"
                        placeholder="Waregem"
                        class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                    >
                </div>
            </div>
        </div>
    </FormCard>
</template>

<script setup>
import FormCard from '../fields/FormCard.vue'

const model = defineModel({ type: Object, required: true })
</script>
