<template>
    <nav class="hidden xl:block">
        <div class="flex items-center gap-2">
            <router-link
                v-for="item in items"
                :key="item.to"
                :to="item.to"
                class="inline-flex items-center gap-2 rounded-2xl border px-4 py-3 text-sm font-semibold transition"
                :class="isActive(item)
                    ? 'border-blue-500 bg-blue-600 text-white shadow-sm'
                    : 'border-transparent bg-transparent text-slate-200 hover:border-slate-700 hover:bg-slate-800 hover:text-white'"
            >
                <component
                    :is="iconMap[item.icon]"
                    class="h-5 w-5 shrink-0"
                />
                <span>{{ item.label }}</span>
            </router-link>
        </div>
    </nav>
</template>

<script setup>
import { useRoute } from 'vue-router'
import {
    HomeIcon,
    CreditCardIcon,
    ClipboardDocumentListIcon,
    CalendarDaysIcon,
    ClipboardDocumentCheckIcon,
    TicketIcon,
    IdentificationIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()

defineProps({
    items: {
        type: Array,
        required: true,
    },
})

const iconMap = {
    home: HomeIcon,
    'credit-card': CreditCardIcon,
    'clipboard-document-list': ClipboardDocumentListIcon,
    'calendar-days': CalendarDaysIcon,
    'clipboard-document-check': ClipboardDocumentCheckIcon,
    ticket: TicketIcon,
    identification: IdentificationIcon,
    users: UsersIcon,
}

function isActive(item) {
    if (item.to === '/') {
        return route.path === '/'
    }

    return route.path === item.to || route.path.startsWith(item.to + '/')
}
</script>
