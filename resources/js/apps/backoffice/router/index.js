import { createRouter, createWebHistory } from 'vue-router'

import DashboardPage from '../modules/dashboard/pages/DashboardPage.vue'
import ReportingPage from '../modules/reporting/pages/ReportingPage.vue'
import DayTotalsPage from '../modules/daytotals/pages/DayTotalsPage.vue'
import ProductManagementPage from '../modules/product-management/pages/ProductManagementPage.vue'
import CateringOptionsPage from '../modules/options/pages/CateringOptionsPage.vue'
import EventTypesPage from '../modules/options/pages/EventTypesPage.vue'
import StayOptionsPage from '../modules/options/pages/StayOptionsPage.vue'
import StaffPage from '../modules/staff/pages/StaffPage.vue'
import DevicesPage from '../modules/devices/pages/DevicesPage.vue'

const routes = [
    { path: '/', name: 'backoffice.dashboard', component: DashboardPage },
    { path: '/reporting', name: 'backoffice.reporting', component: ReportingPage },
    { path: '/daytotals', name: 'backoffice.daytotals', component: DayTotalsPage },
    { path: '/catalog', name: 'backoffice.product-management', component: ProductManagementPage },
    {
        path: '/products',
        redirect: {
            name: 'backoffice.product-management',
            query: { tab: 'products' },
        },
    },
    {
        path: '/product-categories',
        redirect: {
            name: 'backoffice.product-management',
            query: { tab: 'categories' },
        },
    },
    {
        path: '/pricing-engine',
        redirect: {
            name: 'backoffice.product-management',
            query: { tab: 'pricing-rules' },
        },
    },
    { path: '/catering-options', name: 'backoffice.catering-options', component: CateringOptionsPage },
    { path: '/event-types', name: 'backoffice.event-types', component: EventTypesPage },
    { path: '/stay-options', name: 'backoffice.stay-options', component: StayOptionsPage },
    { path: '/staff', name: 'backoffice.staff', component: StaffPage },
    { path: '/devices', name: 'backoffice.devices', component: DevicesPage },
]

export default createRouter({
    history: createWebHistory('/backoffice/'),
    routes,
})
