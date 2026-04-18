import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api, storage } from '../services/api'

export const useAuthStore = defineStore('auth', () => {
    const account = ref(null)
    const initialized = ref(false)

    const isAuthenticated = computed(() => !!account.value)

    async function initialize() {
        const token = await storage.get('member_token')
        if (token) {
            try {
                const { data } = await api.get('/auth/me')
                account.value = data
            } catch {
                await storage.remove('member_token')
            }
        }
        initialized.value = true
    }

    async function login(email, password) {
        const data = await api.post('/auth/login', { email, password, device_name: 'mobile' })
        await storage.set('member_token', data.token)
        account.value = data.account
        return data
    }

    async function register(payload) {
        const data = await api.post('/auth/register', payload)
        await storage.set('member_token', data.token)
        account.value = data.account
        return data
    }

    async function logout() {
        try {
            await api.post('/auth/logout')
        } finally {
            await storage.remove('member_token')
            account.value = null
        }
    }

    async function forgotPassword(email) {
        return await api.post('/auth/forgot-password', { email })
    }

    async function updateProfile(payload) {
        const { data } = await api.put('/profile', payload)
        account.value = data
        return data
    }

    return { account, initialized, isAuthenticated, initialize, login, register, logout, forgotPassword, updateProfile }
})
