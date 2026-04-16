<template>
    <div class="flex h-full min-h-0 flex-col gap-4">
        <div
            v-if="feedback.message"
            class="rounded-3xl border px-5 py-4 text-sm shadow-lg"
            :class="feedback.type === 'error'
                ? 'border-rose-500/30 bg-rose-500/10 text-rose-100'
                : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-100'"
        >
            {{ feedback.message }}
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
            <QuickActionsCard
                :actions="quickActions"
                @new-registration="modals.registration = true"
                @member-attendance="openMemberAttendanceModal"
                @create-member="modals.createMember = true"
                @staff-attendance="openStaffAttendanceModal"
                @create-task="openTaskModal"
            />

            <RevenueCard :total="revenueTotal" />
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-12 items-stretch gap-4">
            <div class="col-span-12 flex self-stretch xl:col-span-5">
                <VisitorStatsCard
                    :date-label="formatDisplayDate(today)"
                    :cards="visitorCards"
                />
            </div>

            <div class="col-span-12 flex self-stretch xl:col-span-3">
                <CateringCard :items="cateringSummary" />
            </div>

            <div class="col-span-12 flex min-h-0 self-stretch xl:col-span-4">
                <TasksCard
                    :tasks="openTasks"
                    @complete-task="completeTask"
                />
            </div>
        </div>

        <RegistrationModal
            :open="modals.registration"
            :initial-values="{}"
            @close="modals.registration = false"
            @submit="createRegistration"
        />

        <MemberModal
            :open="modals.createMember"
            :member="null"
            @close="modals.createMember = false"
            @submit="createMember"
        />

        <MemberAttendanceModal
            v-model:open="modals.memberAttendance"
            @done="loadDashboard"
        />

        <StaffAttendanceModal
            v-model:open="modals.staffAttendance"
            @done="loadDashboard"
        />

        <TaskCreateModal
            :open="modals.task"
            :processing="taskForm.processing"
            :error="taskForm.error"
            :form="taskForm"
            :staff="taskStaff"
            @close="closeTaskModal"
            @update:form="updateTaskForm"
            @submit="createTask"
        />
    </div>
</template>

<script setup>
import axios from '@/lib/http'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import {
    UserPlusIcon,
    IdentificationIcon,
    UsersIcon,
    ClipboardDocumentListIcon,
    CalendarDaysIcon,
} from '@heroicons/vue/24/outline'

import QuickActionsCard from '../components/QuickActionsCard.vue'
import RevenueCard from '../components/RevenueCard.vue'
import VisitorStatsCard from '../components/VisitorStatsCard.vue'
import CateringCard from '../components/CateringCard.vue'
import TasksCard from '../components/TasksCard.vue'

import RegistrationModal from '../../pos/components/registrations/RegistrationModal.vue'
import MemberModal from '../../members/components/MemberModal.vue'
import MemberAttendanceModal from '../modals/MemberAttendanceModal.vue'
import StaffAttendanceModal from '../modals/StaffAttendanceModal.vue'
import TaskCreateModal from '../modals/TaskCreateModal.vue'

import { useAuthStore } from '../../../stores/authStore'

const auth = useAuthStore()

const today = getTodayDateString()

const registrations = ref([])
const tasks = ref([])
const taskStaff = ref([])
const salesSummary = ref(null)

const feedback = reactive({
    type: 'success',
    message: '',
})

const modals = reactive({
    registration: false,
    createMember: false,
    memberAttendance: false,
    staffAttendance: false,
    task: false,
})

const taskForm = reactive({
    title: '',
    description: '',
    due_date: today,
    assigned_user_id: null,
    processing: false,
    error: '',
})

onMounted(() => {
    window.addEventListener('frontdesk-auth-changed', handleAuthChanged)
    if (auth.isAuthenticated) {
        loadDashboard()
    }
})

onBeforeUnmount(() => {
    window.removeEventListener('frontdesk-auth-changed', handleAuthChanged)
})

watch(() => auth.isAuthenticated, isAuthenticated => {
    if (isAuthenticated) {
        loadDashboard()
        return
    }

    registrations.value = []
    salesSummary.value = null
    tasks.value = []
    taskStaff.value = []
})

function handleAuthChanged(event) {
    if (event?.detail?.authenticated) {
        loadDashboard()
    }
}

async function loadDashboard() {
    if (!auth.isAuthenticated) {
        return
    }
    try {
        const [r, s, t] = await Promise.all([
            axios.get('/api/frontdesk/registrations'),
            axios.get('/api/frontdesk/sales', { params: { date: today } }),
            axios.get('/api/frontdesk/tasks', { params: { statuses: ['open'] } }),
        ])

        registrations.value = (r.data?.data || []).filter(isRegistrationForToday)
        salesSummary.value = s.data?.data?.summary || null
        tasks.value = t.data?.data?.tasks || []
        taskStaff.value = t.data?.data?.staff || []
    } catch (e) {
        console.error('Dashboard laden mislukt', e)
        showFeedback('error', 'Dashboard laden mislukt')
    }
}

function isRegistrationForToday(registration) {
    return String(registration?.event_date || '') === today
}

/* ---------------- HELPERS ---------------- */

function getTotalVisitors(r) {
    return Number(r.total_count ?? 0)
        || getChildrenStudentsCount(r) + getAdultsCount(r)
}

function getChildrenStudentsCount(r) {
    return Number(
        r.participants_children
        ?? r.children_count
        ?? r.child_count
        ?? 0
    )
}

function getAdultsCount(r) {
    return Number(
        r.participants_adults
        ?? r.adults_count
        ?? r.adult_count
        ?? 0
    ) + Number(
        r.participants_supervisors
        ?? r.supervisors_count
        ?? r.supervisor_count
        ?? 0
    )
}

/* ---------------- STATS ---------------- */

const visitorCards = computed(() => {
    const map = {
        reserved: init(),
        members: init(),
        checked_in: init(),
        checked_out: init(),
        paid: init(),
        no_show: init(),
    }

    registrations.value.forEach(r => {
        const v = getTotalVisitors(r)
        const c = getChildrenStudentsCount(r)
        const a = getAdultsCount(r)

        const status = String(r.status || '')

        if (status === 'new' || status === 'confirmed') {
            add(map.reserved, v, c, a)
        }

        if (status === 'checked_in') {
            add(map.checked_in, v, c, a)
        }

        if (status === 'checked_out') {
            add(map.checked_out, v, c, a)
        }

        if (status === 'paid') {
            add(map.paid, v, c, a)
        }

        if (status === 'no_show') {
            add(map.no_show, v, c, a)
        }

        if (r.is_member) {
            add(map.members, v, c, a)
        }
    })

    return Object.entries(map).map(([key, val]) => ({
        key,
        ...val,
    }))
})

function init() {
    return { reservations: 0, visitors: 0, childrenStudents: 0, adults: 0 }
}

function add(bucket, visitors, childrenStudents, adults) {
    bucket.reservations++
    bucket.visitors += visitors
    bucket.childrenStudents += childrenStudents
    bucket.adults += adults
}

/* ---------------- DATA ---------------- */

const cateringSummary = computed(() => {
    const map = {}

    registrations.value.forEach(r => {
        if (!r.catering_option || r.catering_option_code === 'none') return

        const key = r.catering_option_id || r.catering_option

        if (!map[key]) {
            map[key] = {
                key,
                label: r.catering_option,
                emoji: r.catering_option_emoji || '🍽️',
                reservations: 0,
                visitors: 0,
                childrenStudents: 0,
                adults: 0,
            }
        }

        const v = getTotalVisitors(r)
        const c = getChildrenStudentsCount(r)
        const a = getAdultsCount(r)

        map[key].reservations++
        map[key].visitors += v
        map[key].childrenStudents += c
        map[key].adults += a
    })

    return Object.values(map)
})

const openTasks = computed(() => tasks.value.filter(t => t.status === 'open'))
const revenueTotal = computed(() => Number(salesSummary.value?.net_revenue || 0))

const quickActions = [
    {
        key: 'registration',
        label: 'Nieuwe registratie',
        description: 'Nieuwe reservatie of walk-in',
        icon: CalendarDaysIcon,
        class: 'border-sky-500/25 bg-sky-500/15 hover:bg-sky-500/20',
        emit: 'new-registration',
    },
    {
        key: 'memberAttendance',
        label: 'Lid in / uitchecken',
        description: 'Maak of sluit een member bezoek',
        icon: IdentificationIcon,
        class: 'border-cyan-500/25 bg-cyan-500/15 hover:bg-cyan-500/20',
        emit: 'member-attendance',
    },
    {
        key: 'memberCreate',
        label: 'Lid toevoegen',
        description: 'Open nieuwe abonnee modal',
        icon: UserPlusIcon,
        class: 'border-emerald-500/25 bg-emerald-500/15 hover:bg-emerald-500/20',
        emit: 'create-member',
    },
    {
        key: 'staffAttendance',
        label: 'Staf in / uitchecken',
        description: 'Verwerk personeelskaart',
        icon: UsersIcon,
        class: 'border-violet-500/25 bg-violet-500/15 hover:bg-violet-500/20',
        emit: 'staff-attendance',
    },
    {
        key: 'task',
        label: 'Taak toevoegen',
        description: 'Open snelle taakmodal',
        icon: ClipboardDocumentListIcon,
        class: 'border-amber-500/25 bg-amber-500/15 hover:bg-amber-500/20',
        emit: 'create-task',
    },
]

/* ---------------- ACTIONS ---------------- */

async function createRegistration(data) {
    await axios.post('/api/frontdesk/registrations', data)
    modals.registration = false
    await loadDashboard()
}

async function createMember(data) {
    await axios.post('/api/frontdesk/members', data)
    modals.createMember = false
    await loadDashboard()
}

async function createTask() {
    taskForm.processing = true
    taskForm.error = ''

    try {
        await axios.post('/api/frontdesk/tasks', {
            title: taskForm.title,
            description: taskForm.description || null,
            due_date: taskForm.due_date,
            assigned_user_id: taskForm.assigned_user_id,
            status: 'open',
            task_type: 'single',
        })

        modals.task = false
        resetTaskForm()
        showFeedback('success', 'Taak toegevoegd.')
        await loadDashboard()
    } catch (error) {
        console.error('Taak aanmaken mislukt', error)
        taskForm.error = error?.response?.data?.message ?? 'Taak opslaan mislukt.'
    } finally {
        taskForm.processing = false
    }
}

async function completeTask(task) {
    try {
        await axios.put(`/api/frontdesk/tasks/${task.id}`, {
            title: task.title,
            description: task.description || null,
            status: 'completed',
            task_type: task.task_type || 'single',
            recurrence_pattern: task.recurrence_pattern || null,
            due_date: task.task_type === 'single' ? (task.due_date || today) : null,
            start_date: task.task_type === 'recurring' ? (task.start_date || today) : null,
            end_date: task.task_type === 'recurring' ? (task.end_date || null) : null,
            assigned_user_id: task.assigned_user_id || null,
        })

        showFeedback('success', 'Taak afgewerkt.')
        await loadDashboard()
    } catch (error) {
        console.error('Taak afronden mislukt', error)
        showFeedback('error', error?.response?.data?.message ?? 'Taak afronden mislukt.')
    }
}

/* ---------------- UI ---------------- */

function openMemberAttendanceModal() {
    modals.memberAttendance = true
}

function openStaffAttendanceModal() {
    modals.staffAttendance = true
}

function openTaskModal() {
    resetTaskForm()
    modals.task = true
}

function closeTaskModal() {
    modals.task = false
}

function resetTaskForm() {
    taskForm.title = ''
    taskForm.description = ''
    taskForm.due_date = today
    taskForm.assigned_user_id = null
    taskForm.processing = false
    taskForm.error = ''
}

function updateTaskForm(value) {
    Object.assign(taskForm, value)
}

function showFeedback(type, message) {
    feedback.type = type
    feedback.message = message
}

/* ---------------- UTILS ---------------- */

function getTodayDateString() {
    const d = new Date()
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

function formatDisplayDate(value) {
    if (!value) return 'Vandaag'

    const date = new Date(value)

    return new Intl.DateTimeFormat('nl-BE', {
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
    }).format(date)
}
</script>
