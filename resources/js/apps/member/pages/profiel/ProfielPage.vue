<template>
    <div class="px-4 pt-3 pb-6 space-y-4">
        <h1 class="text-lg font-semibold text-white">Profiel</h1>

        <!-- Account card -->
        <div class="glass-card rounded-3xl p-5 space-y-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full bg-blue-500/20 border border-blue-500/30 flex items-center justify-center shrink-0">
                    <span class="text-base font-semibold text-blue-300">{{ initials }}</span>
                </div>
                <div>
                    <p class="text-base font-semibold text-white">{{ auth.account?.first_name }} {{ auth.account?.last_name }}</p>
                    <p class="text-xs text-slate-400">{{ auth.account?.email }}</p>
                </div>
            </div>

            <div class="border-t border-slate-800/60 pt-4 space-y-3">
                <ProfileRow label="Naam" :value="`${auth.account?.first_name} ${auth.account?.last_name}`" />
                <ProfileRow label="E-mail" :value="auth.account?.email" />
                <ProfileRow v-if="auth.account?.phone" label="Telefoon" :value="auth.account.phone" />
                <ProfileRow v-if="auth.account?.city" label="Gemeente" :value="auth.account.city" />
            </div>
        </div>

        <!-- Instellingen -->
        <div class="glass-card rounded-3xl p-5 space-y-1">
            <SettingsRow :icon="BellIcon" label="Notificaties" @click="router.push('/profiel/notificaties')" />
            <SettingsRow :icon="FingerPrintIcon" label="Biometrisch inloggen" />
        </div>

        <!-- Uitloggen -->
        <button
            class="w-full flex items-center justify-center gap-2 py-3.5 rounded-2xl border border-rose-500/30 bg-rose-500/10 text-rose-300 text-sm font-medium"
            :disabled="loading"
            @click="doLogout"
        >
            <ArrowRightOnRectangleIcon class="w-4 h-4" />
            Uitloggen
        </button>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import {
    BellIcon,
    FingerPrintIcon,
    ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '../../stores/useAuthStore'
import { useVenueStore } from '../../stores/useVenueStore'
import ProfileRow from '../../components/ProfileRow.vue'
import SettingsRow from '../../components/SettingsRow.vue'

const router = useRouter()
const auth = useAuthStore()
const venue = useVenueStore()
const loading = ref(false)

const initials = computed(() => {
    const a = auth.account
    if (!a) return '?'
    return `${a.first_name?.[0] ?? ''}${a.last_name?.[0] ?? ''}`.toUpperCase()
})

async function doLogout() {
    loading.value = true
    try {
        await auth.logout()
        venue.$reset()
        router.replace('/login')
    } finally {
        loading.value = false
    }
}
</script>
