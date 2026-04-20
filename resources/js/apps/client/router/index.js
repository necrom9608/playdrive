import { createRouter, createWebHistory } from 'vue-router'
import RegisterPage from '../RegisterPage.vue'
import VerifiedPage from '../VerifiedPage.vue'

const routes = [
    {
        path: '/register/:tenant',
        name: 'register',
        component: RegisterPage,
    },
    {
        path: '/verified',
        name: 'verified',
        component: VerifiedPage,
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/register/unknown',
    },
]

const router = createRouter({
    history: createWebHistory('/client'),
    routes,
})

export default router
