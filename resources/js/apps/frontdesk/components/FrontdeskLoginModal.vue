<template>
    <div class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/90 p-4">
        <div
            class="w-full max-w-md rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl"
            @keydown="handleKeydown"
            tabindex="0"
            ref="modalRef"
        >
            <div class="border-b border-slate-800 px-6 py-5">
                <h2 class="text-2xl font-bold text-white">Medewerker login</h2>
                <p class="mt-1 text-sm text-slate-400">
                    Log in met login + paswoord of scan je NFC-kaart.
                </p>
            </div>

            <div class="space-y-5 px-6 py-5">
                <div v-if="errorMessage" class="rounded-2xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                    {{ errorMessage }}
                </div>

                <form class="space-y-4" @submit.prevent="submitLogin">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Login</label>
                        <input
                            v-model="form.username"
                            type="text"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none ring-0 placeholder:text-slate-500 focus:border-blue-500"
                            autocomplete="username"
                        >
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Paswoord</label>
                        <input
                            v-model="form.password"
                            type="password"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none ring-0 placeholder:text-slate-500 focus:border-blue-500"
                            autocomplete="current-password"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-2xl bg-blue-600 px-4 py-3 font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy"
                    >
                        {{ busy ? 'Bezig...' : 'Inloggen' }}
                    </button>
                </form>

                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-slate-800"></div>
                    </div>
                    <div class="relative flex justify-center">
                        <span class="bg-slate-900 px-3 text-xs uppercase tracking-wider text-slate-500">of</span>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-4">
                    <div class="text-sm font-medium text-slate-200">NFC/RFID-kaart</div>
                    <div class="mt-1 text-sm text-slate-400">
                        Scan de kaart nu. Gedetecteerde UID:
                    </div>

                    <div class="mt-3 rounded-xl border border-dashed border-slate-700 px-4 py-3 font-mono text-sm text-cyan-300">
                        {{ scannedUid || 'Wachten op scan...' }}
                    </div>

                    <button
                        type="button"
                        class="mt-4 w-full rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 font-semibold text-slate-200 transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy || !scannedUid"
                        @click="submitCardLogin"
                    >
                        {{ busy ? 'Bezig...' : 'Inloggen met kaart' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { useAuthStore } from '../stores/authStore'

const auth = useAuthStore()

const modalRef = ref(null)
const busy = ref(false)
const errorMessage = ref('')
const scannedUid = ref('')
const rfidBuffer = ref('')
let rfidTimer = null

const form = ref({
    username: '',
    password: '',
})

async function submitLogin() {
    busy.value = true
    errorMessage.value = ''

    try {
        await auth.login(form.value.username, form.value.password)
    } catch (error) {
        errorMessage.value = error?.data?.errors?.username?.[0]
            || error?.data?.message
            || 'Inloggen mislukt.'
    } finally {
        busy.value = false
    }
}

async function submitCardLogin() {
    if (!scannedUid.value) {
        return
    }

    busy.value = true
    errorMessage.value = ''

    try {
        await auth.loginWithCard(scannedUid.value)
        scannedUid.value = ''
        rfidBuffer.value = ''
    } catch (error) {
        errorMessage.value = error?.data?.errors?.rfid_uid?.[0]
            || error?.data?.message
            || 'Inloggen met kaart mislukt.'
    } finally {
        busy.value = false
    }
}

function finalizeScan() {
    const value = rfidBuffer.value.trim()

    if (!value) {
        return
    }

    scannedUid.value = value
    rfidBuffer.value = ''
}

function handleKeydown(event) {
    if (auth.isAuthenticated) {
        return
    }

    if (event.key === 'Enter') {
        if (rfidBuffer.value.trim().length > 0) {
            event.preventDefault()
            finalizeScan()
            submitCardLogin()
        }

        return
    }

    if (event.key.length === 1) {
        rfidBuffer.value += event.key

        if (rfidTimer) {
            clearTimeout(rfidTimer)
        }

        rfidTimer = setTimeout(() => {
            finalizeScan()
        }, 120)
    }
}

onMounted(async () => {
    await nextTick()
    modalRef.value?.focus()
})
</script>
