import { createRouter, createWebHistory } from 'vue-router'
import DashboardPage from '../pages/DashboardPage.vue'
import AgendaPage from '../pages/AgendaPage.vue'
import TasksPage from '../pages/TasksPage.vue'
import SettingsPage from '../pages/SettingsPage.vue'

export default createRouter({
  history: createWebHistory('/staff/'),
  routes: [
    { path: '/', name: 'staff.dashboard', component: DashboardPage },
    { path: '/agenda', name: 'staff.agenda', component: AgendaPage },
    { path: '/tasks', name: 'staff.tasks', component: TasksPage },
    { path: '/settings', name: 'staff.settings', component: SettingsPage },
  ],
})
