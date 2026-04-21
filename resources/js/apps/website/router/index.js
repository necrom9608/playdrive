import { createRouter, createWebHistory } from 'vue-router'
import LandingPage from '../pages/LandingPage.vue'
import BookingFormPage from '../pages/BookingFormPage.vue'

const routes = [
    {
        path: '/',
        name: 'landing',
        component: LandingPage,
    },
    {
        path: '/reserveren/:tenant',
        name: 'booking-form',
        component: BookingFormPage,
        props: true,
    },

    // Fallback — alles naar landing
    { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

export default router
