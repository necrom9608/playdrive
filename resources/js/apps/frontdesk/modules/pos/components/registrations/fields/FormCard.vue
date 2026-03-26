<template>
    <div
        class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm"
        :class="$attrs.class"
    >
        <div class="border-b border-slate-800 px-4 py-3">
            <h3 class="text-sm font-semibold text-slate-100">
                {{ title }}
            </h3>
        </div>

        <div class="p-4">
            <slot />
        </div>
    </div>
</template>

<script setup>
defineOptions({
    inheritAttrs: false,
})

defineProps({
    title: {
        type: String,
        required: true,
    },
})
</script>
