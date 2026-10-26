<template>
    <div v-if="!auth.initialized" class="flex min-h-screen items-center justify-center bg-[#030814] text-slate-400">Backoffice laden...</div>
    <BackofficeLoginCard v-else-if="!auth.isAuthenticated" />
    <AppShell
        v-else
        title="PlayDrive"
        subtitle="Backoffice"
        logo-url="/images/logos/logo_header.png"
        :tenant-label="tenantName"
        :navigation="navigation"
        :status-label="statusLabel"
        searchable
        @logout="handleLogout"
    />
</template>

<script setup>
import { computed, markRaw, onMounted } from 'vue'
import {
    BanknotesIcon,
    CakeIcon,
    CalculatorIcon,
    CalendarDaysIcon,
    ChartBarIcon,
    ClipboardDocumentCheckIcon,
    ClockIcon,
    ComputerDesktopIcon,
    CreditCardIcon,
    CubeIcon,
    DocumentTextIcon,
    EnvelopeIcon,
    EnvelopeOpenIcon,
    HomeIcon,
    HomeModernIcon,
    IdentificationIcon,
    QrCodeIcon,
    SparklesIcon,
    TagIcon,
    TicketIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline'
import AppShell from '../../shared/layouts/AppShell.vue'
import BackofficeLoginCard from './components/BackofficeLoginCard.vue'
import { useBackofficeAuthStore } from './stores/authStore'

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
    { label: 'Uurroosters', to: '/rosters', group: 'Beheer', icon: CalendarDaysIcon },
    { label: 'Speelplekken', to: '/setups', group: 'Beheer', icon: QrCodeIcon },
    { label: 'Displays & POS', to: '/devices', group: 'Beheer', icon: ComputerDesktopIcon },
    { label: 'Mailassistent', to: '/mail-assistant', group: 'Beheer', icon: SparklesIcon },
    { label: 'Mailbeheer', to: '/mail-logs', group: 'Beheer', icon: EnvelopeIcon },
    { label: 'Event types', to: '/event-types', group: 'Instellingen', icon: TagIcon },
    { label: 'Verblijfsopties', to: '/stay-options', group: 'Instellingen', icon: HomeModernIcon },
    { label: 'Openingsuren', to: '/opening-hours', group: 'Instellingen', icon: ClockIcon },
    { label: 'Reservatieformulier', to: '/booking-form', group: 'Instellingen', icon: DocumentTextIcon },
    { label: 'E-mailtemplates', to: '/email-templates', group: 'Instellingen', icon: EnvelopeOpenIcon },
    { label: 'Boekhouding', to: '/accounting', group: 'Instellingen', icon: BanknotesIcon },
].map(item => ({ ...item, icon: markRaw(item.icon) }))

const statusLabel = computed(() => auth.user ? `${auth.user.name} · admin` : null)

onMounted(() => {
    auth.initialize()
    window.addEventListener('backoffice-auth-required', () => {
        auth.user = null
        auth.initialized = true
    })
})

async function handleLogout() {
    await auth.logout()
}
</script>
