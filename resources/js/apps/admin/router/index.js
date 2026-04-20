import { createRouter, createWebHistory } from 'vue-router'
import TenantsPage from '../modules/tenants/pages/TenantsPage.vue'
import AdminStaffPage from '../modules/staff/pages/AdminStaffPage.vue'
import EmailTemplatesPage from '../modules/email-templates/pages/EmailTemplatesPage.vue'

const routes = [
    { path: '/', redirect: '/tenants' },
    { path: '/tenants', name: 'admin.tenants', component: TenantsPage },
    { path: '/staff', name: 'admin.staff', component: AdminStaffPage },
    { path: '/email-templates', name: 'admin.email-templates', component: EmailTemplatesPage },
]

export default createRouter({
    history: createWebHistory('/admin/'),
    routes,
})
