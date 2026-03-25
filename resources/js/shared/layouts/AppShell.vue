<template>
    <div class="min-h-screen bg-slate-950 text-slate-100">
        <div class="flex min-h-screen">
            <aside class="w-72 border-r border-slate-800 bg-slate-900 p-4">
                <div class="mb-6">
                    <div class="text-xl font-bold text-white">{{ title }}</div>
                    <div class="text-sm text-slate-400">{{ subtitle }}</div>
                </div>

                <nav class="space-y-2">
                    <router-link
                        v-for="item in navigation"
                        :key="item.to"
                        :to="item.to"
                        class="block rounded-xl px-4 py-3 text-sm font-medium transition"
                        :class="$route.path === item.to
                            ? 'bg-blue-600 text-white'
                            : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
                    >
                        {{ item.label }}
                    </router-link>
                </nav>
            </aside>

            <main class="flex-1 p-6">
                <router-view />
            </main>
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        required: true,
    },
    navigation: {
        type: Array,
        required: true,
    },
})
</script>
