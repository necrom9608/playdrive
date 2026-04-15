import { createRouter, createWebHistory } from 'vue-router'
import DashboardPage from '../pages/DashboardPage.vue'
import AgendaPage from '../pages/AgendaPage.vue'
import TasksPage from '../pages/TasksPage.vue'
import SettingsPage from '../pages/SettingsPage.vue'

export default createRouter({
  history: createWebHistory('/staff/'),
  routes: [
    { path: '/', name: 'staff.dashboard', component: DashboardPage, meta: { title: 'Dashboard' } },
    { path: '/agenda', name: 'staff.agenda', component: AgendaPage, meta: { title: 'Agenda' } },
    { path: '/tasks', name: 'staff.tasks', component: TasksPage, meta: { title: 'Taken' } },
    { path: '/settings', name: 'staff.settings', component: SettingsPage, meta: { title: 'Settings' } },
  ],
})
