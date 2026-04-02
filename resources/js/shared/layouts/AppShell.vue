<template>
    <div class="h-screen overflow-hidden bg-slate-950 text-slate-100">
        <div class="flex h-full">
            <aside class="sticky top-0 h-screen w-72 shrink-0 overflow-y-auto border-r border-slate-800 bg-slate-900 p-4">
                <div class="mb-6">
                    <div class="text-xl font-bold text-white">{{ title }}</div>
                    <div class="text-sm text-slate-400">{{ subtitle }}</div>
                </div>

                <nav class="space-y-6">
                    <div v-for="group in groupedNavigation" :key="group.label" class="space-y-2">
                        <div v-if="group.label" class="px-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                            {{ group.label }}
                        </div>

                        <div class="space-y-2">
                            <router-link
                                v-for="item in group.items"
                                :key="item.to"
                                :to="item.to"
                                class="block rounded-xl px-4 py-3 text-sm font-medium transition"
                                :class="isActive(item)
                                    ? 'bg-blue-600 text-white'
                                    : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
                            >
                                {{ item.label }}
                            </router-link>
                        </div>
                    </div>
                </nav>

                <div v-if="statusLabel || canLogout" class="mt-8 space-y-3 rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                    <div v-if="statusLabel" class="text-sm text-slate-300">{{ statusLabel }}</div>
                    <button v-if="canLogout" type="button" class="w-full rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20" @click="$emit('logout')">Uitloggen</button>
                </div>
            </aside>

            <main class="min-h-0 flex-1 overflow-y-auto p-6">
                <router-view />
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, required: true },
    navigation: { type: Array, required: true },
    statusLabel: { type: String, default: null },
    canLogout: { type: Boolean, default: true },
})

defineEmits(['logout'])

const route = useRoute()

const groupedNavigation = computed(() => {
    const groups = []

    props.navigation.forEach((item) => {
        const groupLabel = item.group ?? ''
        let group = groups.find(entry => entry.label === groupLabel)

        if (!group) {
            group = { label: groupLabel, items: [] }
            groups.push(group)
        }

        group.items.push(item)
    })

    return groups
})

function isActive(item) {
    return route.path === item.to || route.path.startsWith(`${item.to}/`)
}
</script>
