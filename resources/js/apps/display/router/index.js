import { createRouter, createWebHistory } from 'vue-router'
import DisplayPage from '../pages/DisplayPage.vue'

export default createRouter({
    history: createWebHistory('/display/'),
    routes: [
        { path: '/', name: 'display.home', component: DisplayPage },
    ],
})
