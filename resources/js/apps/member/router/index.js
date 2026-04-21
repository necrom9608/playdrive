import { createRouter, createWebHashHistory } from 'vue-router'
import { useAuthStore } from '../stores/useAuthStore'

const routes = [
    // Auth
    {
        path: '/login',
        name: 'login',
        component: () => import('../pages/auth/LoginPage.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('../pages/auth/RegisterPage.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('../pages/auth/ForgotPasswordPage.vue'),
        meta: { layout: 'auth' },
    },

    // App — Mijn PlayDrive
    {
        path: '/',
        redirect: '/mijn',
    },
    {
        path: '/mijn',
        name: 'mijn',
        component: () => import('../pages/mijn/HomePage.vue'),
        meta: { layout: 'app', requiresAuth: true },
    },
    {
        path: '/mijn/lidmaatschap',
        name: 'lidmaatschap',
        component: () => import('../pages/mijn/LidmaatschapPage.vue'),
        meta: { layout: 'app', requiresAuth: true },
    },
    {
        path: '/mijn/reservaties',
        name: 'reservaties',
        component: () => import('../pages/mijn/ReservatiesPage.vue'),
        meta: { layout: 'app', requiresAuth: true },
    },
    {
        path: '/mijn/tickets',
        name: 'tickets',
        component: () => import('../pages/mijn/TicketsPage.vue'),
        meta: { layout: 'app', requiresAuth: true },
    },
    {
        path: '/mijn/bonnen',
        name: 'bonnen',
        component: () => import('../pages/mijn/BonnenPage.vue'),
        meta: { layout: 'app', requiresAuth: true },
    },
    {
        path: '/mijn/stats',
        name: 'stats',
        component: () => import('../pages/mijn/StatsPage.vue'),
        meta: { layout: 'app', requiresAuth: true },
    },

    // App — Ontdekken
    {
        path: '/ontdekken',
        name: 'ontdekken',
        component: () => import('../pages/ontdekken/OntdekkenPage.vue'),
        meta: { layout: 'app', requiresAuth: true },
    },

    // App — Profiel
    {
        path: '/profiel',
        name: 'profiel',
        component: () => import('../pages/profiel/ProfielPage.vue'),
        meta: { layout: 'app', requiresAuth: true },
    },
]

const router = createRouter({
    history: createWebHashHistory(),
    routes,
})

router.beforeEach(async (to) => {
    const auth = useAuthStore()

    if (!auth.initialized) {
        await auth.initialize()
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login' }
    }

    if (!to.meta.requiresAuth && auth.isAuthenticated && ['login', 'register'].includes(to.name)) {
        return { name: 'mijn' }
    }
})

export default router
