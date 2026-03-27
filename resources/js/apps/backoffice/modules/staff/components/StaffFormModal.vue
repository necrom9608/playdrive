<template>
    <ModalDialog
        :open="open"
        :title="isEditing ? 'Medewerker bewerken' : 'Nieuwe medewerker'"
        description="Voeg een medewerker toe, pas gegevens aan en koppel optioneel een RFID-kaart."
        @close="handleClose"
    >
        <form class="space-y-4" @submit.prevent="submitForm">
            <div
                v-if="error"
                class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300"
            >
                {{ error }}
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Naam</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Login</label>
                    <input
                        v-model="form.username"
                        type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">E-mail</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Paswoord</label>
                    <input
                        v-model="form.password"
                        type="password"
                        :placeholder="isEditing ? 'Leeg laten om niet te wijzigen' : ''"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    />
                </div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-300">RFID-kaart</label>
                        <p class="mt-1 text-xs text-slate-400">
                            Scan een kaart om deze medewerker te koppelen.
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="startRfidScan"
                            :class="[
                                'rounded-xl px-4 py-2 text-sm font-semibold transition',
                                scanningRfid
                                    ? 'bg-emerald-600 text-white hover:bg-emerald-500'
                                    : 'bg-blue-600 text-white hover:bg-blue-500'
                            ]"
                        >
                            {{ scanningRfid ? 'Scan actief...' : 'Scan kaart' }}
                        </button>

                        <button
                            type="button"
                            @click="clearRfid"
                            class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 transition hover:bg-slate-800"
                        >
                            Wissen
                        </button>
                    </div>
                </div>

                <input
                    v-model="form.rfid_uid"
                    type="text"
                    placeholder="Nog geen RFID gekoppeld"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                />

                <p v-if="scanningRfid" class="text-xs text-emerald-300">
                    Wacht op scan... scan nu het kaartje.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Straat</label>
                    <input
                        v-model="form.street"
                        type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Nummer</label>
                    <input
                        v-model="form.house_number"
                        type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Bus</label>
                    <input
                        v-model="form.bus"
                        type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Postcode</label>
                    <input
                        v-model="form.postal_code"
                        type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Gemeente</label>
                    <input
                        v-model="form.city"
                        type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    />
                </div>
            </div>

            <label class="flex items-center gap-3 text-sm text-slate-300">
                <input
                    v-model="form.is_active"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-600 bg-slate-950"
                />
                Actief
            </label>

            <div class="flex flex-wrap gap-3 pt-2">
                <button
                    type="submit"
                    :disabled="saving"
                    class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60"
                >
                    {{ saving ? 'Opslaan...' : isEditing ? 'Medewerker opslaan' : 'Medewerker toevoegen' }}
                </button>

                <button
                    type="button"
                    @click="handleClose"
                    class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
                >
                    Annuleren
                </button>
            </div>
        </form>
    </ModalDialog>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import ModalDialog from '../../../components/ModalDialog.vue'

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    staff: {
        type: Object,
        default: null,
    },
    saving: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['close', 'submit'])

const form = reactive({
    name: '',
    username: '',
    email: '',
    password: '',
    rfid_uid: '',
    street: '',
    house_number: '',
    bus: '',
    postal_code: '',
    city: '',
    is_active: true,
})

const scanningRfid = ref(false)
const rfidBuffer = ref('')
let rfidTimer = null

const isEditing = computed(() => !!props.staff?.id)

watch(
    () => [props.open, props.staff],
    ([open]) => {
        if (open) {
            fillFormFromProps()
        } else {
            resetForm()
            stopRfidScan()
        }
    },
    { immediate: true, deep: true },
)

function fillFormFromProps() {
    form.name = props.staff?.name ?? ''
    form.username = props.staff?.username ?? ''
    form.email = props.staff?.email ?? ''
    form.password = ''
    form.rfid_uid = props.staff?.rfid_uid ?? ''
    form.street = props.staff?.street ?? ''
    form.house_number = props.staff?.house_number ?? ''
    form.bus = props.staff?.bus ?? ''
    form.postal_code = props.staff?.postal_code ?? ''
    form.city = props.staff?.city ?? ''
    form.is_active = props.staff?.is_active ?? true
}

function resetForm() {
    form.name = ''
    form.username = ''
    form.email = ''
    form.password = ''
    form.rfid_uid = ''
    form.street = ''
    form.house_number = ''
    form.bus = ''
    form.postal_code = ''
    form.city = ''
    form.is_active = true
}

function submitForm() {
    emit('submit', {
        name: form.name,
        username: form.username,
        email: form.email,
        password: form.password,
        rfid_uid: form.rfid_uid,
        street: form.street,
        house_number: form.house_number,
        bus: form.bus,
        postal_code: form.postal_code,
        city: form.city,
        is_active: form.is_active,
    })
}

function handleClose() {
    stopRfidScan()
    emit('close')
}

function startRfidScan() {
    scanningRfid.value = true
    rfidBuffer.value = ''
}

function stopRfidScan() {
    scanningRfid.value = false
    rfidBuffer.value = ''

    if (rfidTimer) {
        clearTimeout(rfidTimer)
        rfidTimer = null
    }
}

function clearRfid() {
    form.rfid_uid = ''
}

function handleRfidKeydown(event) {
    if (!props.open || !scanningRfid.value) {
        return
    }

    if (event.key === 'Enter') {
        event.preventDefault()

        const scannedValue = rfidBuffer.value.trim()

        if (scannedValue) {
            form.rfid_uid = scannedValue
        }

        stopRfidScan()
        return
    }

    if (event.key.length === 1) {
        rfidBuffer.value += event.key

        if (rfidTimer) {
            clearTimeout(rfidTimer)
        }

        rfidTimer = setTimeout(() => {
            const scannedValue = rfidBuffer.value.trim()

            if (scannedValue) {
                form.rfid_uid = scannedValue
            }

            stopRfidScan()
        }, 150)
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleRfidKeydown)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleRfidKeydown)
    stopRfidScan()
})
</script>
