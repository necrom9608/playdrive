<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Cateringopties</h1>
                <p class="mt-2 text-slate-400">
                    Beheer cateringopties en koppel producten die automatisch toegevoegd worden bij het uitchecken.
                </p>
            </div>
        </div>

        <CateringOptionsTable
            :options="options"
            :loading="loading"
            :error="error"
            @refresh="loadOptions"
            @create="openCreateModal"
            @edit="openEditModal"
            @delete="handleDelete"
            @manage-products="openProductsModal"
            @reorder="handleReorder"
        />

        <CateringOptionFormModal
            :open="formModalOpen"
            :option="editingOption"
            :saving="saving"
            :error="formError"
            @close="closeFormModal"
            @submit="handleSubmit"
        />

        <CateringOptionProductsModal
            :open="productsModalOpen"
            :option="selectedOptionForProducts"
            :products="products"
            @close="closeProductsModal"
        />
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import CateringOptionsTable from '../components/CateringOptionsTable.vue'
import CateringOptionFormModal from '../components/CateringOptionFormModal.vue'
import CateringOptionProductsModal from '../components/CateringOptionProductsModal.vue'
import {
    createOption,
    deleteOption,
    fetchOptions,
    reorderOptions,
    updateOption,
} from '../services/optionApi'
import { fetchProducts } from '../../products/services/productApi'

const options = ref([])
const products = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const formError = ref('')

const formModalOpen = ref(false)
const productsModalOpen = ref(false)

const editingOption = ref(null)
const selectedOptionForProducts = ref(null)

async function loadOptions() {
    loading.value = true
    error.value = ''

    try {
        options.value = await fetchOptions('catering-options')
    } catch (err) {
        console.error(err)
        error.value = 'Kon cateringopties niet laden.'
    } finally {
        loading.value = false
    }
}

async function loadProducts() {
    try {
        products.value = await fetchProducts()
    } catch (err) {
        console.error(err)
    }
}

function openCreateModal() {
    editingOption.value = null
    formError.value = ''
    formModalOpen.value = true
}

function openEditModal(option) {
    editingOption.value = option
    formError.value = ''
    formModalOpen.value = true
}

function closeFormModal() {
    formModalOpen.value = false
    editingOption.value = null
    formError.value = ''
}

async function handleSubmit(payload) {
    saving.value = true
    formError.value = ''

    try {
        if (editingOption.value?.id) {
            await updateOption('catering-options', editingOption.value.id, payload)
        } else {
            await createOption('catering-options', payload)
        }

        closeFormModal()
        await loadOptions()
    } catch (err) {
        console.error(err)

        if (err?.status === 422 && err?.data?.errors) {
            const firstError = Object.values(err.data.errors)?.[0]?.[0]
            formError.value = firstError || 'Validatiefout bij opslaan van cateringoptie.'
        } else if (err?.data?.message) {
            formError.value = err.data.message
        } else if (err?.message) {
            formError.value = err.message
        } else {
            formError.value = 'Kon cateringoptie niet opslaan.'
        }
    } finally {
        saving.value = false
    }
}

async function handleDelete(option) {
    if (!window.confirm(`Cateringoptie "${option.name}" verwijderen?`)) {
        return
    }

    error.value = ''

    try {
        await deleteOption('catering-options', option.id)
        await loadOptions()
    } catch (err) {
        console.error(err)
        error.value = 'Kon cateringoptie niet verwijderen.'
    }
}

async function handleReorder(items) {
    error.value = ''

    try {
        await reorderOptions('catering-options', items.map((item) => ({ id: item.id })))
        await loadOptions()
    } catch (err) {
        console.error(err)
        error.value = 'Kon volgorde niet opslaan.'
    }
}

function openProductsModal(option) {
    selectedOptionForProducts.value = option
    productsModalOpen.value = true
}

function closeProductsModal() {
    productsModalOpen.value = false
    selectedOptionForProducts.value = null
}

onMounted(async () => {
    await Promise.all([
        loadOptions(),
        loadProducts(),
    ])
})
</script>
