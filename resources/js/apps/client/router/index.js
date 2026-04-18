import { createRouter, createWebHistory } from 'vue-router'
import RegisterPage from '../RegisterPage.vue'

const routes = [
    {
        path: '/register/:tenant',
        name: 'register',
        component: RegisterPage,
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
