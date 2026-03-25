import { createRouter, createWebHistory } from 'vue-router'

import ProductCategoriesPage from '../modules/product-categories/pages/ProductCategoriesPage.vue'
import ProductsPage from '../modules/products/pages/ProductsPage.vue'

const routes = [
    { path: '/', name: 'backoffice.product-categories', component: ProductCategoriesPage },
    { path: '/products', name: 'backoffice.products', component: ProductsPage },
]

export default createRouter({
    history: createWebHistory(),
    routes,
})
