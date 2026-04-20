import { createRouter, createWebHistory } from 'vue-router'
import LandingPage from '../pages/LandingPage.vue'

const routes = [
    {
        path: '/',
        name: 'landing',
        component: LandingPage,
    },
    // Toekomstige routes:
    // { path: '/venues', name: 'venues', component: () => import('../pages/VenuesPage.vue') },
    // { path: '/venues/:slug', name: 'venue', component: () => import('../pages/VenuePage.vue') },
    // { path: '/login', name: 'login', component: () => import('../pages/LoginPage.vue') },

    // Fallback — alles naar landing
    { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

export default router
