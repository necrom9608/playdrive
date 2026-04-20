<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Tenants</h1>
                <p class="mt-2 text-slate-400">Beheer alle PlayDrive-tenants, hun gegevens en gekoppelde domeinen.</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Totaal</div>
                <div class="mt-2 text-3xl font-bold text-white">{{ tenants.length }}</div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Actief</div>
                <div class="mt-2 text-3xl font-bold text-emerald-300">{{ tenants.filter(t => t.is_active).length }}</div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Inactief</div>
                <div class="mt-2 text-3xl font-bold text-amber-300">{{ tenants.filter(t => !t.is_active).length }}</div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Domeinen</div>
                <div class="mt-2 text-3xl font-bold text-cyan-300">{{ tenants.reduce((sum, t) => sum + (t.domains?.length ?? 0), 0) }}</div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3">
            <button
                type="button"
                :disabled="loading"
                class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-60"
                @click="loadTenants"
            >
                Vernieuwen
            </button>
            <button
                type="button"
                class="rounded-xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-500"
                @click="openCreateModal"
            >
                Nieuwe tenant
            </button>
        </div>

        <div v-if="error" class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">
            {{ error }}
        </div>

        <!-- Table -->
        <div class="rounded-2xl border border-slate-800 bg-slate-900">
            <div v-if="loading" class="p-6 text-sm text-slate-400">Laden...</div>
            <div v-else-if="tenants.length === 0" class="p-6 text-sm text-slate-400">Nog geen tenants gevonden.</div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950 text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Naam</th>
                            <th class="px-4 py-3 text-left font-semibold">Slug</th>
                            <th class="px-4 py-3 text-left font-semibold">Bedrijf</th>
                            <th class="px-4 py-3 text-left font-semibold">E-mail</th>
                            <th class="px-4 py-3 text-left font-semibold">Domeinen</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="tenant in tenants"
                            :key="tenant.id"
                            class="border-t border-slate-800"
                        >
                            <td class="px-4 py-3 font-medium text-white">{{ tenant.name }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ tenant.slug }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ tenant.company_name || '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ tenant.email || '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="domain in (tenant.domains ?? []).slice(0, 3)"
                                        :key="domain.id"
                                        class="rounded-full bg-slate-800 px-2 py-0.5 text-xs text-slate-300"
                                    >
                                        {{ domain.domain }}
                                    </span>
                                    <span v-if="(tenant.domains?.length ?? 0) > 3" class="rounded-full bg-slate-800 px-2 py-0.5 text-xs text-slate-500">
                                        +{{ tenant.domains.length - 3 }}
                                    </span>
                                    <span v-if="!tenant.domains?.length" class="text-slate-500">—</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="tenant.is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300'"
                                >
                                    {{ tenant.is_active ? 'Actief' : 'Inactief' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-xl border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800"
                                        @click="openEditModal(tenant)"
                                    >
                                        Bewerken
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/20"
                                        @click="handleDelete(tenant)"
                                    >
                                        Verwijderen
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <TenantFormModal
            :open="modalOpen"
            :tenant="editingTenant"
            :saving="saving"
            :error="modalError"
            @close="closeModal"
            @submit="handleSubmit"
        />
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import TenantFormModal from '../components/TenantFormModal.vue'
import { createTenant, deleteTenant, fetchTenants, updateTenant } from '../services/tenantApi'

const tenants = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const modalError = ref('')
const modalOpen = ref(false)
const editingTenant = ref(null)

async function loadTenants() {
    loading.value = true
    error.value = ''
    try {
        const response = await fetchTenants()
        tenants.value = response.tenants ?? response ?? []
    } catch (err) {
        error.value = 'Kon tenants niet laden.'
    } finally {
        loading.value = false
    }
}

function openCreateModal() {
    editingTenant.value = null
    modalError.value = ''
    modalOpen.value = true
}

function openEditModal(tenant) {
    editingTenant.value = tenant
    modalError.value = ''
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
    editingTenant.value = null
    modalError.value = ''
}

async function handleSubmit(payload) {
    saving.value = true
    modalError.value = ''
    try {
        if (editingTenant.value?.id) {
            await updateTenant(editingTenant.value.id, payload)
        } else {
            await createTenant(payload)
        }
        closeModal()
        await loadTenants()
    } catch (err) {
        modalError.value = err?.data?.message || 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}

async function handleDelete(tenant) {
    if (!confirm(`Tenant "${tenant.name}" verwijderen? Dit kan niet ongedaan worden.`)) return
    try {
        await deleteTenant(tenant.id)
        await loadTenants()
    } catch (err) {
        error.value = 'Verwijderen mislukt.'
    }
}

onMounted(loadTenants)
</script>
