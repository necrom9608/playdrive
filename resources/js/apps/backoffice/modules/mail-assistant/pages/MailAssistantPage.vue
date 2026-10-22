<template>
    <div class="flex min-h-full flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-white">Mailassistent</h1>
                <p class="mt-0.5 text-sm text-slate-400">
                    Concept-antwoorden op klantmails, opgesteld met de gegevens uit Playdrive. Niets vertrekt zonder jouw akkoord.
                </p>
            </div>

            <div class="flex flex-wrap gap-2" role="tablist">
                <button
                    v-for="item in tabs"
                    :key="item.key"
                    type="button"
                    role="tab"
                    :aria-selected="tab === item.key"
                    class="inline-flex items-center gap-2 rounded-2xl border px-4 py-3 text-sm font-semibold transition"
                    :class="tab === item.key
                        ? 'border-blue-500 bg-blue-600 text-white shadow-sm'
                        : 'border-slate-700 text-slate-200 hover:bg-slate-800 hover:text-white'"
                    @click="setTab(item.key)"
                >
                    <component :is="item.icon" class="h-5 w-5" />
                    {{ item.label }}
                </button>
            </div>
        </div>

        <MailInboxTab v-if="tab === 'inbox'" @open-settings="setTab('settings')" />
        <MailSettingsTab v-else-if="tab === 'settings'" />
        <MailKnowledgeTab v-else />
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { BookOpenIcon, Cog6ToothIcon, InboxIcon } from '@heroicons/vue/24/outline'
import MailInboxTab from '../components/MailInboxTab.vue'
import MailSettingsTab from '../components/MailSettingsTab.vue'
import MailKnowledgeTab from '../components/MailKnowledgeTab.vue'

const route = useRoute()
const router = useRouter()

const tabs = [
    { key: 'inbox', label: 'INBOX', icon: InboxIcon },
    { key: 'settings', label: 'INSTELLINGEN', icon: Cog6ToothIcon },
    { key: 'knowledge', label: 'KENNISBANK', icon: BookOpenIcon },
]

const tab = ref(tabs.some(t => t.key === route.query.tab) ? route.query.tab : 'inbox')

watch(() => route.query.tab, (value) => {
    if (tabs.some(t => t.key === value)) tab.value = value
})

function setTab(key) {
    tab.value = key
    router.replace({ query: { ...route.query, tab: key === 'inbox' ? undefined : key } })
}
</script>
