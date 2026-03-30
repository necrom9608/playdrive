import { createRouter, createWebHistory } from 'vue-router'

import ProductManagementPage from '../modules/product-management/pages/ProductManagementPage.vue'
import CateringOptionsPage from '../modules/options/pages/CateringOptionsPage.vue'
import EventTypesPage from '../modules/options/pages/EventTypesPage.vue'
import StayOptionsPage from '../modules/options/pages/StayOptionsPage.vue'
import StaffPage from '../modules/staff/pages/StaffPage.vue'

const routes = [
    { path: '/', name: 'backoffice.product-management', component: ProductManagementPage },
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
]

export default createRouter({
    history: createWebHistory(),
    routes,
})
