<template>
    <div class="flex h-[calc(100vh-8.5rem)] min-h-[760px] flex-col gap-4">
        <div>
            <h1 class="text-3xl font-bold text-white">Badge creator</h1>
            <p class="mt-2 max-w-3xl text-slate-400">
                Bouw staff-, member- en vouchertemplates visueel op, vertrek van een starttemplate en sla tenant-specifieke ontwerpen op voor later gebruik.
            </p>
        </div>

        <div v-if="error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-200">
            {{ error }}
        </div>

        <div v-if="successMessage" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-200">
            {{ successMessage }}
        </div>

        <div class="grid min-h-0 flex-1 items-stretch gap-6 xl:grid-cols-[320px_minmax(0,1fr)_360px] overflow-hidden">
            <BadgeTemplateSidebar
                :filters="typeFilters"
                :active-filter="activeTypeFilter"
                :presets="filteredPresets"
                :saved-templates="filteredSavedTemplates"
                :loading="loading"
                :current-template-id="editorMeta.id"
                :current-source-id="editorMeta.sourceId"
                :element-tools="elementTools"
                @refresh="loadTemplates"
                @update:active-filter="activeTypeFilter = $event"
                @create-template="createBlankTemplate"
                @load-preset="loadPreset"
                @load-saved-template="loadSavedTemplate"
                @add-element="addElement"
            />

            <BadgeCanvasWorkspace
                :template="editorTemplate"
                :selected-element-id="selectedElementId"
                :sample-data="sampleDataForType"
                :has-stored-template="!!editorMeta.id"
                :saving="saving"
                @duplicate="duplicateCurrent"
                @reset="resetToBlank"
                @delete="deleteCurrentTemplate"
                @save="saveTemplate"
                @select-element="selectElement"
                @update-element-position="updateElementPosition"
            />

            <BadgePropertiesPanel
                :template="editorTemplate"
                :selected-element="selectedElement"
                :available-fields="availableFields"
                :sample-data="sampleDataForType"
                @remove-selected="removeSelectedElement"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import BadgeCanvasWorkspace from '../components/BadgeCanvasWorkspace.vue'
import BadgePropertiesPanel from '../components/BadgePropertiesPanel.vue'
import BadgeTemplateSidebar from '../components/BadgeTemplateSidebar.vue'
import {
    blankTemplate,
    clamp,
    clampNumber,
    createConfigClone,
    createEditorTemplate,
    elementTools,
    fieldCatalog,
    presets,
    sampleData,
    typeFilters,
    uid,
} from '../utils/badgeEditor'
import { createBadgeTemplate, deleteBadgeTemplate, fetchBadgeTemplates, updateBadgeTemplate } from '../services/badgeTemplateApi'

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const successMessage = ref('')
const savedTemplates = ref([])
const activeTypeFilter = ref('all')
const selectedElementId = ref(null)

const editorTemplate = ref(createEditorTemplate(blankTemplate('staff')))
const editorMeta = ref({ id: null, isPreset: true, sourceId: null })

const filteredPresets = computed(() => presets.filter(template => activeTypeFilter.value === 'all' || template.template_type === activeTypeFilter.value))
const filteredSavedTemplates = computed(() => savedTemplates.value.filter(template => activeTypeFilter.value === 'all' || template.template_type === activeTypeFilter.value))
const selectedElement = computed(() => editorTemplate.value.config_json.elements.find(element => element.id === selectedElementId.value) || null)
const availableFields = computed(() => fieldCatalog[editorTemplate.value.template_type] ?? [])
const sampleDataForType = computed(() => sampleData[editorTemplate.value.template_type] ?? {})

watch(
    () => editorTemplate.value.template_type,
    (newType, oldType) => {
        if (!newType || newType === oldType) {
            return
        }

        const firstField = fieldCatalog[newType]?.[0]?.value || 'full_name'
        const allowedFields = (fieldCatalog[newType] || []).map(item => item.value)

        editorTemplate.value.config_json.elements = editorTemplate.value.config_json.elements.map((element) => {
            if (element.type !== 'field') {
                return element
            }

            return {
                ...element,
                source: allowedFields.includes(element.source) ? element.source : firstField,
            }
        })
    }
)

onMounted(loadTemplates)

async function loadTemplates() {
    loading.value = true
    error.value = ''

    try {
        savedTemplates.value = await fetchBadgeTemplates()
    } catch (err) {
        console.error(err)
        error.value = err?.response?.data?.message ?? 'Kon badge templates niet laden.'
    } finally {
        loading.value = false
    }
}

function createBlankTemplate(type) {
    editorTemplate.value = createEditorTemplate(blankTemplate(type))
    editorMeta.value = { id: null, isPreset: true, sourceId: null }
    selectedElementId.value = editorTemplate.value.config_json.elements[0]?.id ?? null
    successMessage.value = ''
}

function resetToBlank() {
    createBlankTemplate(editorTemplate.value.template_type || 'staff')
}

function loadPreset(preset) {
    editorTemplate.value = createEditorTemplate({
        ...preset,
        name: `${preset.name}`,
    })
    editorMeta.value = { id: null, isPreset: true, sourceId: preset.id }
    selectedElementId.value = editorTemplate.value.config_json.elements[0]?.id ?? null
    successMessage.value = ''
}

function loadSavedTemplate(template) {
    editorTemplate.value = createEditorTemplate(template)
    editorMeta.value = { id: template.id, isPreset: false, sourceId: template.id }
    selectedElementId.value = editorTemplate.value.config_json.elements[0]?.id ?? null
    successMessage.value = ''
}

function duplicateCurrent() {
    const duplicate = createEditorTemplate({
        ...editorTemplate.value,
        name: `${editorTemplate.value.name} kopie`,
        is_default: false,
    })

    editorTemplate.value = duplicate
    editorMeta.value = { id: null, isPreset: true, sourceId: editorMeta.value.sourceId }
    selectedElementId.value = duplicate.config_json.elements[0]?.id ?? null
}

function addElement(type) {
    const baseField = availableFields.value[0]?.value || 'full_name'

    const defaults = {
        text: { label: 'Vrije tekst', text: 'Nieuwe tekst', width: 260, height: 68, fontSize: 30, fontWeight: 700, color: '#ffffff', backgroundColor: 'transparent', textAlign: 'left', borderRadius: 0, opacity: 1 },
        field: { label: 'Dataveld', source: baseField, width: 320, height: 72, fontSize: 36, fontWeight: 700, color: '#ffffff', backgroundColor: 'transparent', textAlign: 'left', borderRadius: 0, opacity: 1 },
        photo: { label: 'Foto', width: 220, height: 280, imagePath: '', imageUrl: '', fit: 'cover', backgroundColor: '#1e293b', borderRadius: 24, opacity: 1 },
        image: { label: 'Afbeelding', width: 220, height: 160, imagePath: '', imageUrl: '', fit: 'cover', backgroundColor: '#1e293b', borderRadius: 20, opacity: 1 },
        logo: { label: 'Logo', width: 180, height: 56, imagePath: '', imageUrl: '', fit: 'contain', backgroundColor: '#312e81', borderRadius: 18, opacity: 1 },
        qr: { label: 'QR', width: 150, height: 150, backgroundColor: '#ffffff', borderRadius: 24, opacity: 1 },
        shape: { label: 'Vorm', width: 280, height: 90, backgroundColor: '#7c3aed', borderRadius: 20, opacity: 1 },
    }

    const nextElement = {
        id: uid(),
        type,
        x: 80,
        y: 80,
        zIndex: highestZIndex() + 1,
        ...defaults[type],
    }

    editorTemplate.value.config_json.elements.push(nextElement)
    selectedElementId.value = nextElement.id
}

function selectElement(id) {
    selectedElementId.value = id
}

function updateElementPosition({ id, x, y }) {
    const element = editorTemplate.value.config_json.elements.find(item => item.id === id)
    if (!element) {
        return
    }

    element.x = clamp(x, 0, editorTemplate.value.config_json.width - element.width)
    element.y = clamp(y, 0, editorTemplate.value.config_json.height - element.height)
}

function removeSelectedElement() {
    if (!selectedElement.value) {
        return
    }

    const index = editorTemplate.value.config_json.elements.findIndex(element => element.id === selectedElement.value.id)
    if (index === -1) {
        return
    }

    editorTemplate.value.config_json.elements.splice(index, 1)
    selectedElementId.value = editorTemplate.value.config_json.elements[0]?.id ?? null
}

async function saveTemplate() {
    saving.value = true
    error.value = ''
    successMessage.value = ''

    try {
        const payload = normalizedPayload()
        let storedTemplate = null

        if (editorMeta.value.id) {
            storedTemplate = await updateBadgeTemplate(editorMeta.value.id, payload)
            successMessage.value = 'Template bijgewerkt.'
        } else {
            storedTemplate = await createBadgeTemplate(payload)
            successMessage.value = 'Template opgeslagen.'
        }

        await loadTemplates()
        loadSavedTemplate(storedTemplate)
    } catch (err) {
        console.error(err)
        error.value = err?.response?.data?.message ?? 'Kon badge template niet opslaan.'
    } finally {
        saving.value = false
    }
}

async function deleteCurrentTemplate() {
    if (!editorMeta.value.id) {
        return
    }

    if (!window.confirm(`Template "${editorTemplate.value.name}" verwijderen?`)) {
        return
    }

    try {
        await deleteBadgeTemplate(editorMeta.value.id)
        successMessage.value = 'Template verwijderd.'
        await loadTemplates()
        createBlankTemplate(editorTemplate.value.template_type || 'staff')
    } catch (err) {
        console.error(err)
        error.value = err?.response?.data?.message ?? 'Kon badge template niet verwijderen.'
    }
}

function normalizedPayload() {
    const config = createConfigClone(editorTemplate.value.config_json)

    config.elements = config.elements.map((element, index) => ({
        id: String(element.id),
        type: element.type,
        label: element.label || null,
        source: element.type === 'field' ? (element.source || availableFields.value[0]?.value || 'full_name') : null,
        text: element.type === 'text' ? (element.text || '') : null,
        imagePath: ['photo', 'image', 'logo'].includes(element.type) ? (element.imagePath || null) : null,
        imageUrl: ['photo', 'image', 'logo'].includes(element.type) ? (element.imageUrl || null) : null,
        fit: ['photo', 'image', 'logo'].includes(element.type) ? (element.fit || (element.type === 'logo' ? 'contain' : 'cover')) : null,
        x: clampNumber(element.x, 0),
        y: clampNumber(element.y, 0),
        width: clampNumber(element.width, 1),
        height: clampNumber(element.height, 1),
        fontSize: ['text', 'field'].includes(element.type) ? clampNumber(element.fontSize, 12) : null,
        fontWeight: ['text', 'field'].includes(element.type) ? clampNumber(element.fontWeight, 700) : null,
        color: ['text', 'field'].includes(element.type) ? (element.color || '#ffffff') : null,
        backgroundColor: element.backgroundColor || null,
        borderRadius: clampNumber(element.borderRadius, 0),
        textAlign: ['text', 'field'].includes(element.type) ? (element.textAlign || 'left') : null,
        opacity: Number(element.opacity ?? 1),
        zIndex: Number(element.zIndex ?? index + 1),
    }))

    return {
        name: (editorTemplate.value.name || '').trim() || `Nieuwe ${editorTemplate.value.template_type} template`,
        template_type: editorTemplate.value.template_type,
        description: (editorTemplate.value.description || '').trim() || null,
        is_default: !!editorTemplate.value.is_default,
        config_json: config,
    }
}

function highestZIndex() {
    return editorTemplate.value.config_json.elements.reduce((highest, element) => Math.max(highest, Number(element.zIndex || 1)), 1)
}
</script>
