import { createRouter, createWebHistory } from 'vue-router'

import DashboardPage from '../modules/dashboard/pages/DashboardPage.vue'
import PosPage from '../modules/pos/pages/PosPage.vue'
import SalesPage from '../modules/sales/pages/SalesPage.vue'
import VouchersPage from '../modules/vouchers/pages/VouchersPage.vue'
import AgendaPage from '../modules/agenda/pages/AgendaPage.vue'
import StaffPage from '../modules/staff/pages/StaffPage.vue'
import MembersPage from '../modules/members/pages/MembersPage.vue'
import TasksPage from '../modules/tasks/pages/TasksPage.vue'

const routes = [
    { path: '/', name: 'frontdesk.dashboard', component: DashboardPage },
    { path: '/pos', name: 'frontdesk.pos', component: PosPage },
    { path: '/sales', name: 'frontdesk.sales', component: SalesPage },
    { path: '/vouchers', name: 'frontdesk.vouchers', component: VouchersPage },
    { path: '/agenda', name: 'frontdesk.agenda', component: AgendaPage },
    { path: '/staff', name: 'frontdesk.staff', component: StaffPage },
    { path: '/members', name: 'frontdesk.members', component: MembersPage },
    { path: '/tasks', name: 'frontdesk.tasks', component: TasksPage },
]

export default createRouter({
    history: createWebHistory(),
    routes,
})
