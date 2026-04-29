import { createRouter, createWebHistory } from 'vue-router'
import DashboardPage from '../pages/DashboardPage.vue'
import InfoPage from '../pages/InfoPage.vue'
import MediaPage from '../pages/MediaPage.vue'
import ActivitiesPage from '../pages/ActivitiesPage.vue'
import AmenitiesPage from '../pages/AmenitiesPage.vue'
import LinksPage from '../pages/LinksPage.vue'
import PublicationPage from '../pages/PublicationPage.vue'

const routes = [
    { path: '/', name: 'portal.dashboard', component: DashboardPage },
    { path: '/info', name: 'portal.info', component: InfoPage },
    { path: '/media', name: 'portal.media', component: MediaPage },
    { path: '/activities', name: 'portal.activities', component: ActivitiesPage },
    { path: '/amenities', name: 'portal.amenities', component: AmenitiesPage },
    { path: '/links', name: 'portal.links', component: LinksPage },
    { path: '/publication', name: 'portal.publication', component: PublicationPage },
    { path: '/:pathMatch(.*)*', redirect: '/' },
]

export default createRouter({
    history: createWebHistory('/portal/'),
    routes,
})
