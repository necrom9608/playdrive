<template>
    <div class="mb-3 shrink-0">
        <div class="flex flex-wrap gap-2">


            <button
                v-for="category in store.categories"
                :key="category.id"
                type="button"
                @click="store.selectCategory(category.id)"
                class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
                :class="store.selectedCategoryId === category.id
                    ? 'border-blue-500 bg-blue-600 text-white'
                    : 'border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700'"
            >
                {{ category.name }}
            </button>

            <button
                type="button"
                @click="store.selectCategory(null)"
                class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
                :class="store.selectedCategoryId === null
                    ? 'border-blue-500 bg-blue-600 text-white'
                    : 'border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700'"
            >
                Alles
            </button>
        </div>
    </div>
</template>

<script setup>
import { usePosStore } from '../../stores/usePosStore.js'

const store = usePosStore()
</script>
