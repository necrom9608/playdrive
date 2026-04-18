<template>
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium" :class="styles[status] ?? styles.none">
        <span class="w-1.5 h-1.5 rounded-full" :class="dotStyles[status] ?? dotStyles.none" />
        {{ labels[status] ?? 'Onbekend' }}
    </span>
</template>

<script setup>
defineProps({ status: { type: String, default: 'none' } })

const labels = {
    active: 'Actief lid',
    expired: 'Verlopen',
    none: 'Bezoekersprofiel',
}

const styles = {
    active: 'bg-emerald-500/15 border border-emerald-500/25 text-emerald-300',
    expired: 'bg-amber-500/15 border border-amber-500/25 text-amber-300',
    none: 'bg-slate-700/50 border border-slate-600/40 text-slate-400',
}

const dotStyles = {
    active: 'bg-emerald-400',
    expired: 'bg-amber-400',
    none: 'bg-slate-500',
}
</script>
