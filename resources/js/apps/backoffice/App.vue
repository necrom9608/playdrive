<template>
    <div v-if="!auth.initialized" class="flex min-h-screen items-center justify-center bg-[#030814] text-slate-400">Backoffice laden...</div>
    <BackofficeLoginCard v-else-if="!auth.isAuthenticated" />
    <AppShell
        v-else
        title="PlayDrive"
        subtitle="Backoffice"
        logo-url="/images/logos/logo_header.png"
        :tenant-label="tenantName"
        :navigation="navigationWithBadges"
        :status-label="statusLabel"
        searchable
        @logout="handleLogout"
    />
</template>

<script setup>
import { computed, markRaw, onMounted, ref, watch } from 'vue'
import {
    BanknotesIcon,
    CakeIcon,
    CalculatorIcon,
    CalendarDaysIcon,
    ChartBarIcon,
    ClipboardDocumentCheckIcon,
    ClipboardDocumentListIcon,
    ClockIcon,
    CreditCardIcon,
    CubeIcon,
    DocumentCheckIcon,
    DocumentTextIcon,
    EnvelopeIcon,
    EnvelopeOpenIcon,
    HomeIcon,
    HomeModernIcon,
    IdentificationIcon,
    PuzzlePieceIcon,
    QrCodeIcon,
    SparklesIcon,
    TagIcon,
    TicketIcon,
    TvIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline'
import AppShell from '../../shared/layouts/AppShell.vue'
import BackofficeLoginCard from './components/BackofficeLoginCard.vue'
import { useBackofficeAuthStore } from './stores/authStore'
import { fetchGamesSummary } from './modules/games/services/gameApi'

const auth = useBackofficeAuthStore()
const tenantName = window.PlayDrive?.tenantName || null

const navigation = [
    { label: 'Dashboard', to: '/', group: 'Inzichten', icon: HomeIcon },
    { label: 'Rapportering', to: '/reporting', group: 'Inzichten', icon: ChartBarIcon },
    { label: 'Dagtotalen', to: '/daytotals', group: 'Inzichten', icon: CalculatorIcon },
    { label: 'Productbeheer', to: '/catalog', group: 'Verkoop', icon: CubeIcon },
    { label: 'Badge designer', to: '/badges', group: 'Verkoop', icon: IdentificationIcon },
    { label: 'Voucher types', to: '/voucher-templates', group: 'Verkoop', icon: TicketIcon },
    { label: 'Kaartenbeheer', to: '/cards', group: 'Verkoop', icon: CreditCardIcon },
    { label: 'Cateringopties', to: '/catering-options', group: 'Verkoop', icon: CakeIcon },
    { label: 'Personeel', to: '/staff', group: 'Beheer', icon: UsersIcon },
    { label: 'Aanwezigheden', to: '/staff-attendance', group: 'Beheer', icon: ClipboardDocumentCheckIcon },
    { label: 'Maandprestaties', to: '/staff-payroll', group: 'Beheer', icon: ClipboardDocumentListIcon },
    { label: 'Uurroosters', to: '/rosters', group: 'Beheer', icon: CalendarDaysIcon },
    { label: 'Speelplekken', to: '/setups', group: 'Beheer', icon: QrCodeIcon },
    { label: 'Aankopen', to: '/purchases', group: 'Beheer', icon: DocumentCheckIcon },
    { label: 'Games', to: '/games', group: 'Beheer', icon: PuzzlePieceIcon },
    { label: 'Mailassistent', to: '/mail-assistant', group: 'Beheer', icon: SparklesIcon },
    { label: 'Mailbeheer', to: '/mail-logs', group: 'Beheer', icon: EnvelopeIcon },
    { label: 'Event types', to: '/event-types', group: 'Instellingen', icon: TagIcon },
    { label: 'Verblijfsopties', to: '/stay-options', group: 'Instellingen', icon: HomeModernIcon },
    { label: 'Openingsuren', to: '/opening-hours', group: 'Instellingen', icon: ClockIcon },
    { label: 'Klantenscherm', to: '/display-skin', group: 'Instellingen', icon: TvIcon },
    { label: 'Reservatieformulier', to: '/booking-form', group: 'Instellingen', icon: DocumentTextIcon },
    { label: 'E-mailtemplates', to: '/email-templates', group: 'Instellingen', icon: EnvelopeOpenIcon },
    { label: 'Boekhouding', to: '/accounting', group: 'Instellingen', icon: BanknotesIcon },
].map(item => ({ ...item, icon: markRaw(item.icon) }))

// Teller bij "Games": nieuwe games die Playscope meldde en nog niet ingedeeld zijn.
const newGames = ref(0)
const navigationWithBadges = computed(() => navigation.map(item =>
    item.to === '/games' && newGames.value ? { ...item, badge: newGames.value } : item,
))

async function refreshGamesBadge() {
    try {
        newGames.value = (await fetchGamesSummary())?.new ?? 0
    } catch {
        // Niet kritisch: geen teller.
    }
}

watch(() => auth.isAuthenticated, (ok) => { if (ok) refreshGamesBadge() })

const statusLabel = computed(() => auth.user ? `${auth.user.name} · admin` : null)

onMounted(() => {
    auth.initialize()
    window.addEventListener('backoffice-games-new', (e) => { newGames.value = Number(e.detail) || 0 })
    window.setInterval(() => { if (auth.isAuthenticated) refreshGamesBadge() }, 120000)
    window.addEventListener('backoffice-auth-required', () => {
        auth.user = null
        auth.initialized = true
    })
})

async function handleLogout() {
    await auth.logout()
}
</script>
