import { defineStore } from 'pinia'
import { apiFetch } from '../../../shared/services/api'

export const useStaffProfileStore = defineStore('staffProfile', {
  state: () => ({ loading: false, saving: false, form: { name: '', username: '', email: '', street: '', house_number: '', bus: '', postal_code: '', city: '', current_password: '', password: '', password_confirmation: '' }, message: '', error: '' }),
  actions: {
    async fetchProfile() {
      this.loading = true
      try {
        const response = await apiFetch('/api/staff/profile')
        this.form = { ...this.form, ...(response.data ?? {}), current_password: '', password: '', password_confirmation: '' }
      } finally {
        this.loading = false
      }
    },
    async saveProfile() {
      this.saving = true
      this.message = ''
      this.error = ''
      try {
        const response = await apiFetch('/api/staff/profile', { method: 'PUT', body: JSON.stringify(this.form) })
        this.message = response.message || 'Opgeslagen.'
        this.form.current_password = ''
        this.form.password = ''
        this.form.password_confirmation = ''
      } catch (error) {
        this.error = error?.data?.message || 'Opslaan mislukt.'
        throw error
      } finally {
        this.saving = false
      }
    },
  },
})
