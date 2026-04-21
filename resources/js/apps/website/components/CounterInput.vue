<template>
    <div class="flex items-center gap-3">
        <button
            type="button"
            class="counter-btn"
            :disabled="modelValue <= 0"
            @click="$emit('update:modelValue', Math.max(0, modelValue - 1))"
        >
            −
        </button>
        <span class="w-6 text-center text-sm font-semibold" style="color: var(--text-main);">
            {{ modelValue }}
        </span>
        <button
            type="button"
            class="counter-btn"
            @click="$emit('update:modelValue', modelValue + 1)"
        >
            +
        </button>
    </div>
</template>

<script setup>
defineProps({
    modelValue: { type: Number, default: 0 },
})
defineEmits(['update:modelValue'])
</script>

<style scoped>
.counter-btn {
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    border: 1px solid rgba(75, 98, 148, 0.35);
    background: rgba(15, 23, 42, 0.60);
    color: var(--text-main);
    font-size: 1.1rem;
    line-height: 1;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.counter-btn:hover:not(:disabled) {
    background: rgba(59, 130, 246, 0.15);
    border-color: rgba(59, 130, 246, 0.45);
}
.counter-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}
</style>
