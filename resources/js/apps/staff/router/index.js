import { createRouter, createWebHistory } from 'vue-router'
import DashboardPage from '../pages/DashboardPage.vue'
import AgendaPage from '../pages/AgendaPage.vue'
import AgendaDayPage from '../pages/agenda/AgendaDayPage.vue'
import AgendaWeekPage from '../pages/agenda/AgendaWeekPage.vue'
import AgendaMonthPage from '../pages/agenda/AgendaMonthPage.vue'
import TasksPage from '../pages/TasksPage.vue'
import SettingsPage from '../pages/SettingsPage.vue'

export default createRouter({
  history: createWebHistory('/staff/'),
  routes: [
    { path: '/', name: 'staff.dashboard', component: DashboardPage },
    {
      path: '/agenda',
      component: AgendaPage,
      children: [
        { path: '', redirect: { name: 'staff.agenda.day' } },
        { path: 'day', name: 'staff.agenda.day', component: AgendaDayPage },
        { path: 'week', name: 'staff.agenda.week', component: AgendaWeekPage },
        { path: 'month', name: 'staff.agenda.month', component: AgendaMonthPage },
      ],
    },
    { path: '/tasks', name: 'staff.tasks', component: TasksPage },
    { path: '/settings', name: 'staff.settings', component: SettingsPage },
  ],
})
