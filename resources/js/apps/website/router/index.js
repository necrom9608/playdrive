import { createRouter, createWebHistory } from 'vue-router'
import LandingPage from '../pages/LandingPage.vue'
import BookingFormPage from '../pages/BookingFormPage.vue'
import ReservationDetailPage from '../pages/ReservationDetailPage.vue'
import JoinPage from '../pages/JoinPage.vue'

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
    {
        path: '/reservatie/:token',
        name: 'reservation-detail',
        component: ReservationDetailPage,
        props: true,
    },

    {
        // Lid worden via de QR-code op het klantenscherm (vaste link, niet wijzigen)
        path: '/join/:slug',
        name: 'join',
        component: JoinPage,
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
