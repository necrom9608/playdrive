import { createRouter, createWebHistory } from 'vue-router'

import ProductCategoriesPage from '../modules/product-categories/pages/ProductCategoriesPage.vue'
import ProductsPage from '../modules/products/pages/ProductsPage.vue'
import CateringOptionsPage from '../modules/options/pages/CateringOptionsPage.vue'
import EventTypesPage from '../modules/options/pages/EventTypesPage.vue'
import StayOptionsPage from '../modules/options/pages/StayOptionsPage.vue'
import StaffPage from '../modules/staff/pages/StaffPage.vue'
import PricingEnginePage from '../modules/pricing-engine/pages/PricingEnginePage.vue'

const routes = [
    { path: '/', name: 'backoffice.product-categories', component: ProductCategoriesPage },
    { path: '/products', name: 'backoffice.products', component: ProductsPage },
    { path: '/catering-options', name: 'backoffice.catering-options', component: CateringOptionsPage },
    { path: '/event-types', name: 'backoffice.event-types', component: EventTypesPage },
    { path: '/stay-options', name: 'backoffice.stay-options', component: StayOptionsPage },
    { path: '/staff', name: 'backoffice.staff', component: StaffPage },
    { path: '/pricing-engine', name: 'backoffice.pricing-engine', component: PricingEnginePage },
]

export default createRouter({
    history: createWebHistory(),
    routes,
})
