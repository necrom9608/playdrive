import { defineStore } from 'pinia'
import { apiFetch } from '../../../shared/services/api'

export const useStaffTasksStore = defineStore('staffTasks', {
  state: () => ({ loading: false, search: '', statuses: [], summary: { total: 0, open: 0, completed: 0, cancelled: 0 }, tasks: [] }),
  actions: {
    async fetchTasks() {
      this.loading = true
      const params = new URLSearchParams()
      if (this.search) params.set('search', this.search)
      this.statuses.forEach(status => params.append('statuses[]', status))
      try {
        const response = await apiFetch(`/api/staff/tasks${params.toString() ? `?${params.toString()}` : ''}`)
        this.summary = response.data?.summary ?? this.summary
        this.tasks = response.data?.tasks ?? []
      } finally {
        this.loading = false
      }
    },
    async updateStatus(taskId, status) {
      await apiFetch(`/api/staff/tasks/${taskId}`, { method: 'PUT', body: JSON.stringify({ status }) })
      await this.fetchTasks()
    },
  },
})
