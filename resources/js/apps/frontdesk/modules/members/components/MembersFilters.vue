<template>
    <div class="rounded-3xl border border-slate-800 bg-slate-900 p-4 shadow-xl">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid flex-1 gap-4 md:grid-cols-[minmax(0,1fr)_220px]">
                <label class="space-y-2 text-sm text-slate-300">
                    <span>Zoeken</span>
                    <input
                        :value="search"
                        type="text"
                        placeholder="Naam, e-mail, login of RFID"
                        class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                        @input="$emit('update:search', $event.target.value)"
                        @keyup.enter="$emit('search')"
                    >
                </label>

                <label class="space-y-2 text-sm text-slate-300">
                    <span>Status</span>
                    <select
                        :value="status"
                        class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                        @change="$emit('update:status', $event.target.value)"
                    >
                        <option value="all">Alle</option>
                        <option value="active">Actief</option>
                        <option value="expiring">Vervalt binnenkort</option>
                        <option value="expired">Vervallen</option>
                        <option value="inactive">Inactief</option>
                    </select>
                </label>
            </div>

            <div class="flex flex-wrap gap-3">
                <button
                    type="button"
                    class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
                    @click="$emit('search')"
                >
                    Zoeken
                </button>
                <button
                    type="button"
                    class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500"
                    @click="$emit('new')"
                >
                    Nieuwe abonnee
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    search: {
        type: String,
        default: '',
    },
    status: {
        type: String,
        default: 'all',
    },
})

defineEmits(['update:search', 'update:status', 'search', 'new'])
</script>
