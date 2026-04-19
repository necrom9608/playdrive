<template>
    <div class="flex h-full min-h-0 flex-col gap-6">
        <NewRegistrationsPanel
            :registrations="newRegistrations"
            :activating="activatingId"
            @activate="openActivateModal"
        />

        <MembersSummaryCards :summary="store.summary" />

        <MembersFilters
            :search="store.search"
            :selected-statuses="store.selectedStatuses"
            @update:search="store.setSearch($event)"
            @update:selected-statuses="store.setSelectedStatuses($event)"
            @search="store.fetchMembers()"
            @new-display="openDisplayWizard"
        />

        <div v-if="store.error" class="rounded-3xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-200">
            {{ store.error }}
        </div>

        <div v-if="inviteMessage" class="rounded-3xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-200">
            {{ inviteMessage }}
        </div>

        <MembersTable
            :members="store.members"
            :selected-member-id="store.selectedMemberId"
            @select="store.selectMember($event)"
        >
            <template #actions>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-slate-400">
                        <span v-if="store.selectedMember">
                            Geselecteerd: <span class="font-semibold text-white">#{{ store.selectedMember.id }} · {{ store.selectedMember.full_name }}</span>
                            <span class="ml-2 text-slate-500">{{ store.selectedMember.email }}</span>
                        </span>
                        <span v-else>Geen lid geselecteerd.</span>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button
                            v-if="store.selectedMember?.status === 'none'"
                            type="button"
                            class="rounded-2xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-500 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!store.selectedMember"
                            @click="openActivateModal(store.selectedMember)"
                        >
                            Abonnement activeren
                        </button>

                        <!-- Uitnodiging sturen — enkel als er een geldig e-mailadres is -->
                        <button
                            v-if="store.selectedMember && !isPlaceholderEmail(store.selectedMember.email)"
                            type="button"
                            class="rounded-2xl border border-violet-500/30 bg-violet-500/10 px-4 py-3 text-sm font-semibold text-violet-200 transition hover:bg-violet-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!store.selectedMember || inviting"
                            @click="handleInvite(store.selectedMember)"
                        >
                            <span v-if="inviting" class="flex items-center gap-2">
                                <span class="h-3.5 w-3.5 animate-spin rounded-full border border-violet-300/30 border-t-violet-300"></span>
                                Versturen…
                            </span>
                            <span v-else>Uitnodiging sturen</span>
                        </button>

                        <button
                            type="button"
                            class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!store.selectedMember"
                            @click="openEditModal(store.selectedMember)"
                        >
                            Membership bewerken
                        </button>

                        <button
                            type="button"
                            class="rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!store.selectedMember || store.selectedMember?.status === 'none'"
                            @click="handleRenew(store.selectedMember)"
                        >
                            Verlengen
                        </button>
                    </div>
                </div>
            </template>
        </MembersTable>

        <MembershipModal
            :open="showModal"
            :member="editingMember"
            :badge-templates="store.memberBadgeTemplates"
            @close="closeModal"
            @submit="handleSubmit"
        />

        <ActivateMembershipModal
            :open="showActivateModal"
            :registration="activatingRegistration"
            @close="showActivateModal = false"
            @activated="handleActivated"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import axios from '@/lib/http'
import MembersSummaryCards from '../components/MembersSummaryCards.vue'
import MembersFilters from '../components/MembersFilters.vue'
import MembersTable from '../components/MembersTable.vue'
import MembershipModal from '../components/MembershipModal.vue'
import NewRegistrationsPanel from '../components/NewRegistrationsPanel.vue'
import ActivateMembershipModal from '../components/ActivateMembershipModal.vue'
import { useMembersStore } from '../stores/useMembersStore'
import { usePosStore } from '@/apps/frontdesk/modules/pos/stores/usePosStore'

const store    = useMembersStore()
const posStore = usePosStore()

const showModal              = ref(false)
const editingMemberId        = ref(null)
const showActivateModal      = ref(false)
const activatingRegistration = ref(null)
const activatingId           = ref(null)
const newRegistrations       = ref([])
const inviting               = ref(false)
const inviteMessage          = ref('')

const editingMember = computed(() =>
    editingMemberId.value ? store.members.find(m => m.id === editingMemberId.value) ?? null : null
)

onMounted(() => {
    store.fetchMembers()
    store.startPolling(30000)
    fetchNewRegistrations()
    setInterval(fetchNewRegistrations, 20000)
})

onUnmounted(() => store.stopPolling())

async function fetchNewRegistrations() {
    try {
        const { data } = await axios.get('/api/frontdesk/new-registrations')
        newRegistrations.value = data.data ?? []
    } catch {}
}

function openActivateModal(registration) {
    activatingRegistration.value = registration
    showActivateModal.value = true
}

async function handleActivated() {
    activatingId.value = activatingRegistration.value?.membership_id ?? activatingRegistration.value?.id
    await Promise.all([fetchNewRegistrations(), store.fetchMembers()])
    activatingId.value = null
}

function openEditModal(member) {
    editingMemberId.value = member?.id ?? null
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    editingMemberId.value = null
}

async function openDisplayWizard() {
    store.error = null
    if (!posStore.displaySyncReady || !posStore.posDevice?.display_device_id) {
        store.error = 'Er is geen gekoppelde display beschikbaar.'
        return
    }
    try {
        await store.fetchMembers()
        const templates = store.memberBadgeTemplates ?? []
        await axios.post('/api/frontdesk/display/sync', {
            device_uuid: posStore.posDevice.device_uuid,
            device_token: posStore.posDevice.device_token,
            mode: 'member_registration',
            payload: {
                member_registration: {
                    step: 1, submitted: false, success: false, templates,
                    defaults: { type: 'adult', badge_template_id: templates.find(t => t.is_default)?.id ?? templates[0]?.id ?? null },
                },
            },
        })
    } catch (err) {
        store.error = err?.response?.data?.message ?? 'Wizard op display openen mislukt.'
    }
}

async function handleSubmit(payload) {
    try {
        await store.saveMember(payload)
        closeModal()
    } catch {}
}

async function handleRenew(member) {
    if (member?.id) await store.renewMember(member.id)
}

async function handleInvite(member) {
    if (!member?.id || inviting.value) return
    inviting.value = true
    inviteMessage.value = ''
    store.error = ''
    try {
        const { data } = await axios.post(`/api/frontdesk/members/${member.id}/invite`)
        inviteMessage.value = data.message
        setTimeout(() => { inviteMessage.value = '' }, 6000)
    } catch (err) {
        store.error = err?.response?.data?.message ?? 'Uitnodiging versturen mislukt.'
    } finally {
        inviting.value = false
    }
}

function isPlaceholderEmail(email) {
    if (!email) return true
    return email.includes('@migrated.local') || email.includes('@playdrive.local')
}
</script>
