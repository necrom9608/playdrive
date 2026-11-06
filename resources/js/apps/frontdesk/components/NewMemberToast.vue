<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed right-4 top-20 z-[170] flex w-[340px] max-w-[calc(100vw-2rem)] flex-col gap-2">
            <TransitionGroup name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.membership_id"
                    role="status"
                    class="pointer-events-auto flex items-start gap-3 rounded-2xl border border-cyan-400/40 bg-slate-900 px-4 py-3 shadow-2xl shadow-black/50"
                >
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-cyan-500/15">
                        <UserPlusIcon class="h-4 w-4 text-cyan-300" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-white">Nieuw lid: {{ toast.full_name }}</p>
                        <p class="truncate text-xs text-slate-400">
                            {{ toast.existing_account ? 'Bestaand Playdrive-account' : 'Via QR' }} · {{ toast.registered_label }}
                            <template v-if="toast.children?.length"> · + {{ toast.children.length === 1 ? '1 kind' : `${toast.children.length} kinderen` }}</template>
                        </p>
                    </div>
                    <button type="button" class="shrink-0 px-1 py-1.5 text-sm font-semibold text-cyan-300 hover:text-cyan-200" @click="open(toast)">Openen</button>
                    <button type="button" class="shrink-0 rounded-lg p-1.5 text-slate-500 hover:text-slate-300" aria-label="Sluiten" @click="dismiss(toast)">
                        <XMarkIcon class="h-4 w-4" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<script setup>
/**
 * Melding rechtsboven op elke frontdesk-pagina zodra iemand lid wordt via de QR-code (/join).
 * Bij het opstarten worden de bestaande nieuwe leden enkel onthouden (geen melding);
 * die staan in Leden → Nieuwe leden.
 */
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { UserPlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import axios from '@/lib/http'

const POLL_MS = 20000
const SHOW_MS = 20000

const router = useRouter()
const toasts = ref([])
const known = new Set()
let initialized = false
let timer = null

async function poll() {
    try {
        const { data } = await axios.get('/api/frontdesk/new-registrations')
        const list = Array.isArray(data?.data) ? data.data : []
        const fresh = list.filter(r => !known.has(r.membership_id))
        list.forEach(r => known.add(r.membership_id))
        if (!initialized) {
            initialized = true
            return
        }
        fresh.forEach(show)
    } catch {
        // volgende poging
    }
}

function show(registration) {
    toasts.value = [registration, ...toasts.value].slice(0, 3)
    window.setTimeout(() => dismiss(registration), SHOW_MS)
}

function dismiss(registration) {
    toasts.value = toasts.value.filter(t => t.membership_id !== registration.membership_id)
}

function open(registration) {
    dismiss(registration)
    router.push({ path: '/members', query: { search: registration.code } })
}

onMounted(() => {
    poll()
    timer = window.setInterval(poll, POLL_MS)
})

onUnmounted(() => window.clearInterval(timer))
</script>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: opacity .25s ease, transform .25s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(16px); }
</style>
