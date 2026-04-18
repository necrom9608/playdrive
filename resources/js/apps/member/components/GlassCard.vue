<template>
    <div class="glass-card rounded-3xl p-5" :class="$attrs.class">
        <slot />
    </div>
</template>

<script setup>
defineOptions({ inheritAttrs: false })
</script>
