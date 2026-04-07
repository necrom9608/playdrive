<template>
    <div class="grid gap-5 lg:grid-cols-2">
        <label class="space-y-2 text-sm text-slate-300 lg:col-span-2">
            <span>Straat</span>
            <input :value="form.street" type="text" :class="fieldClass" @input="update('street', $event.target.value)">
        </label>

        <label class="space-y-2 text-sm text-slate-300">
            <span>Huisnummer</span>
            <input :value="form.house_number" type="text" :class="fieldClass" @input="update('house_number', $event.target.value)">
        </label>

        <label class="space-y-2 text-sm text-slate-300">
            <span>Box</span>
            <input :value="form.box" type="text" :class="fieldClass" @input="update('box', $event.target.value)">
        </label>

        <label class="space-y-2 text-sm text-slate-300">
            <span>Postcode</span>
            <input :value="form.postal_code" type="text" :class="fieldClass" @input="update('postal_code', $event.target.value)">
        </label>

        <label class="space-y-2 text-sm text-slate-300">
            <span>Gemeente</span>
            <input :value="form.city" type="text" :class="fieldClass" @input="update('city', $event.target.value)">
        </label>

        <label class="space-y-2 text-sm text-slate-300 lg:col-span-2">
            <span>Land</span>
            <input :value="form.country" type="text" :class="fieldClass" @input="update('country', $event.target.value)">
        </label>
    </div>
</template>

<script setup>
defineProps({
    form: { type: Object, required: true },
    fieldClass: { type: String, required: true },
})

const emit = defineEmits(['update'])

function update(field, value) {
    emit('update', { field, value })
}
</script>
