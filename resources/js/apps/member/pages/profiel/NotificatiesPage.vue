<template>
    <div class="px-4 pt-3 pb-6 space-y-4">
        <button class="flex items-center gap-2 text-sm text-slate-400" @click="router.push('/profiel')">
            <ArrowLeftIcon class="w-4 h-4" />
            Profiel
        </button>
        <h1 class="text-lg font-semibold text-white">Notificaties</h1>

        <div class="glass-card rounded-3xl p-5">
            <p class="text-xs uppercase tracking-[0.12em] text-slate-400 mb-3">E-mail</p>

            <label class="flex items-start gap-3 cursor-pointer">
                <div class="w-9 h-9 rounded-xl bg-amber-500/15 border border-amber-500/25 flex items-center justify-center shrink-0">
                    <TrophyIcon class="w-5 h-5 text-amber-400" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-slate-100">Iemand pakt je #1 af</p>
                    <p class="text-xs text-slate-400 leading-relaxed mt-0.5">
                        Een mail als je je eerste plaats op een chart verliest. Maximaal één per dag, alles gebundeld.
                    </p>
                </div>
                <button
                    type="button"
                    role="switch"
                    :aria-checked="arcadeMail"
                    :disabled="saving"
                    class="relative w-11 h-6 rounded-full border transition-colors shrink-0 mt-1"
                    :class="arcadeMail ? 'bg-blue-500/80 border-blue-400/60' : 'bg-slate-700/70 border-slate-600/60'"
                    @click="toggle"
                >
                    <span
                        class="absolute top-0.5 left-0.5 w-[18px] h-[18px] rounded-full bg-white shadow transition-transform"
                        :class="arcadeMail ? 'translate-x-5' : ''"
                    />
                </button>
            </label>

            <p v-if="error" class="text-xs text-rose-300 mt-3">{{ error }}</p>
        </div>

        <p class="text-xs text-slate-500 text-center leading-relaxed px-4">
            Pushmeldingen volgen zodra de app in de Play Store en App Store staat.
        </p>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeftIcon, TrophyIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '../../stores/useAuthStore'

const router = useRouter()
const auth = useAuthStore()

const arcadeMail = ref(auth.account?.arcade_notify_mail ?? true)
const saving = ref(false)
const error = ref('')

watch(() => auth.account?.arcade_notify_mail, v => {
    if (v !== undefined) arcadeMail.value = v
})

async function toggle() {
    const next = !arcadeMail.value
    arcadeMail.value = next
    error.value = ''
    saving.value = true
    try {
        await auth.updateProfile({ arcade_notify_mail: next })
    } catch (e) {
        arcadeMail.value = !next
        error.value = e?.message ?? 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}
</script>
