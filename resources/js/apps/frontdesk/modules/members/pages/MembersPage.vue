<template>
    <div class="flex h-full min-h-0 flex-col gap-6">
        <div>
            <h2 class="text-3xl font-bold text-white">Abonnees</h2>
            <p class="mt-2 text-slate-400">
                Beheer van abonnees, RFID-koppelingen en abonnementsgeldigheid.
            </p>
        </div>

        <MembersSummaryCards :summary="store.summary" />

        <MembersFilters
            :search="store.search"
            :status="store.statusFilter"
            @update:search="store.setSearch($event)"
            @update:status="store.setStatusFilter($event)"
            @search="store.fetchMembers()"
            @new="openCreateModal"
        />

        <div v-if="store.error" class="rounded-3xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-200">
            {{ store.error }}
        </div>

        <div class="grid min-h-0 flex-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <MembersTable
                :members="store.members"
                :selected-member-id="store.selectedMemberId"
                @select="store.selectMember($event)"
                @edit="openEditModal"
                @renew="handleRenew"
            />

            <MemberDetailPanel
                :member="store.selectedMember"
                @edit="openEditModal"
                @renew="handleRenew"
                @send-email="handleSendEmail"
            />
        </div>

        <MemberModal
            :open="showModal"
            :member="editingMember"
            @close="closeModal"
            @submit="handleSubmit"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import MembersSummaryCards from '../components/MembersSummaryCards.vue'
import MembersFilters from '../components/MembersFilters.vue'
import MembersTable from '../components/MembersTable.vue'
import MemberDetailPanel from '../components/MemberDetailPanel.vue'
import MemberModal from '../components/MemberModal.vue'
import { useMembersStore } from '../stores/useMembersStore'

const store = useMembersStore()
const showModal = ref(false)
const editingMemberId = ref(null)

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

async function handleSubmit(payload) {
    try {
        await store.saveMember(payload)
        closeModal()
    } catch (error) {
        // foutmelding wordt al in store gezet
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
