import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api, storage } from '../services/api'

export const useVenueStore = defineStore('venue', () => {
    const venues = ref([])
    const activeSlug = ref(null)
    const membership = ref(null)
    const loading = ref(false)

    const activeVenue = computed(() => venues.value.find(v => v.slug === activeSlug.value) ?? null)

    async function loadVenues() {
        loading.value = true
        try {
            const { data } = await api.get('/venues')
            venues.value = data

            const saved = await storage.get('active_venue_slug')
            const exists = data.find(v => v.slug === saved)
            activeSlug.value = exists ? saved : data[0]?.slug ?? null

            if (activeSlug.value) {
                await loadMembership(activeSlug.value)
            }
        } finally {
            loading.value = false
        }
    }

    async function loadMembership(slug) {
        try {
            const { data } = await api.get(`/venues/${slug}/membership`)
            membership.value = data
        } catch {
            membership.value = null
        }
    }

    async function switchVenue(slug) {
        activeSlug.value = slug
        membership.value = null
        await storage.set('active_venue_slug', slug)
        await loadMembership(slug)
    }

    async function joinVenue(slug) {
        const data = await api.post(`/venues/${slug}/join`)
        await loadVenues()
        await switchVenue(slug)
        return data
    }

    return { venues, activeSlug, activeVenue, membership, loading, loadVenues, switchVenue, joinVenue, loadMembership }
})
