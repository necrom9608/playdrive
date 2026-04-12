<template>
    <div class="flex h-full min-h-0 flex-col gap-6">
        <MembersSummaryCards :summary="store.summary" />

        <MembersFilters
            :search="store.search"
            :selected-statuses="store.selectedStatuses"
            @update:search="store.setSearch($event)"
            @update:selected-statuses="store.setSelectedStatuses($event)"
            @search="store.fetchMembers()"
            @new="openCreateModal"
            @new-display="openDisplayWizard"
        />

        <div
            v-if="store.error"
            class="rounded-3xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-200"
        >
            {{ store.error }}
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
                        </span>
                        <span v-else>Geen abonnee geselecteerd.</span>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!store.selectedMember"
                            @click="openEditModal(store.selectedMember)"
                        >
                            Bewerken
                        </button>

                        <button
                            type="button"
                            class="rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!store.selectedMember"
                            @click="handleRenew(store.selectedMember)"
                        >
                            Verlengen
                        </button>

                        <button
                            type="button"
                            class="rounded-2xl border border-sky-500/30 bg-sky-500/10 px-4 py-3 text-sm font-semibold text-sky-200 transition hover:bg-sky-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!store.selectedMember"
                            @click="handleSendEmail({ member: store.selectedMember, type: 'confirmation' })"
                        >
                            Bevestigingsmail
                        </button>

                        <button
                            type="button"
                            class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm font-semibold text-amber-200 transition hover:bg-amber-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!store.selectedMember"
                            @click="handleSendEmail({ member: store.selectedMember, type: 'expiring' })"
                        >
                            Mail vervalt binnenkort
                        </button>

                        <button
                            type="button"
                            class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!store.selectedMember"
                            @click="handleSendEmail({ member: store.selectedMember, type: 'expired' })"
                        >
                            Mail vervallen
                        </button>
                    </div>
                </div>
            </template>
        </MembersTable>

        <MemberModal
            :open="showModal"
            :member="editingMember"
            :badge-templates="store.memberBadgeTemplates"
            @close="closeModal"
            @submit="handleSubmit"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from '@/lib/http'
import MembersSummaryCards from '../components/MembersSummaryCards.vue'
import MembersFilters from '../components/MembersFilters.vue'
import MembersTable from '../components/MembersTable.vue'
import MemberModal from '../components/MemberModal.vue'
import { useMembersStore } from '../stores/useMembersStore'
import { usePosStore } from '@/apps/frontdesk/modules/pos/stores/usePosStore'

const store = useMembersStore()
const showModal = ref(false)
const editingMemberId = ref(null)
const posStore = usePosStore()

const editingMember = computed(() => {
    if (!editingMemberId.value) {
        return null
    }

    return store.members.find(member => member.id === editingMemberId.value) ?? null
})

onMounted(() => {
    store.fetchMembers()
})

function openCreateModal() {
    editingMemberId.value = null
    showModal.value = true
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

    if (!posStore.displaySyncReady || !posStore.posDevice?.display_device_id || !posStore.posDevice?.device_uuid) {
        store.error = 'Er is geen gekoppelde display beschikbaar. Koppel eerst een display aan deze POS-terminal.'
        return
    }

    try {
        await store.fetchMembers()

        const templates = Array.isArray(store.memberBadgeTemplates) ? store.memberBadgeTemplates : []
        const defaultTemplateId = templates.find(template => template.is_default)?.id ?? templates[0]?.id ?? null

        await axios.post('/api/frontdesk/display/sync', {
            device_uuid: posStore.posDevice.device_uuid,
            device_token: posStore.posDevice.device_token,
            mode: 'member_registration',
            payload: {
                member_registration: {
                    step: 1,
                    submitted: false,
                    success: false,
                    templates,
                    defaults: {
                        type: 'adult',
                        badge_template_id: defaultTemplateId,
                    },
                },
            },
        })
    } catch (error) {
        console.error('Failed to open display member wizard', error)
        store.error = error?.response?.data?.message ?? 'Wizard op de display openen mislukt.'
    }
}

async function handleSubmit(payload) {
    try {
        await store.saveMember(payload)
        closeModal()
    } catch {
        // foutmelding zit al in store
    }
}

async function handleRenew(member) {
    if (!member?.id) {
        return
    }

    await store.renewMember(member.id)
}

async function handleSendEmail({ member, type }) {
    if (!member?.id || !type) {
        return
    }

    await store.sendLifecycleEmail(member.id, type)
}
</script>
