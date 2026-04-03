import { defineStore } from 'pinia'
import axios from '@/lib/http'

export const useTasksStore = defineStore('frontdeskTasks', {
    state: () => ({
        loading: false,
        saving: false,
        error: null,
        search: '',
        statuses: [],
        assignedUserId: '',
        summary: { total: 0, open: 0, completed: 0, cancelled: 0 },
        staff: [],
        tasks: [],
        selectedTaskId: null,
    }),

    getters: {
        selectedTask(state) {
            return state.tasks.find(task => task.id === state.selectedTaskId) ?? null
        },
    },

    actions: {
        async fetchTasks() {
            this.loading = true
            this.error = null

            try {
                const response = await axios.get('/api/frontdesk/tasks', {
                    params: {
                        search: this.search || undefined,
                        statuses: this.statuses.length ? this.statuses : undefined,
                        assigned_user_id: this.assignedUserId !== '' ? this.assignedUserId : undefined,
                    },
                })

                this.summary = response.data?.data?.summary ?? this.summary
                this.staff = response.data?.data?.staff ?? []
                this.tasks = response.data?.data?.tasks ?? []
                this.selectedTaskId = this.tasks.find(task => task.id === this.selectedTaskId)?.id ?? this.tasks[0]?.id ?? null
            } catch (error) {
                console.error('Failed to fetch tasks', error)
                this.error = error?.response?.data?.message ?? 'Taken konden niet geladen worden.'
                this.tasks = []
            } finally {
                this.loading = false
            }
        },

        async saveTask(payload) {
            this.saving = true
            this.error = null

            try {
                if (payload.id) {
                    await axios.put(`/api/frontdesk/tasks/${payload.id}`, payload)
                } else {
                    await axios.post('/api/frontdesk/tasks', payload)
                }

                await this.fetchTasks()
                return true
            } catch (error) {
                console.error('Failed to save task', error)
                this.error = error?.response?.data?.message ?? 'Taak opslaan mislukt.'
                throw error
            } finally {
                this.saving = false
            }
        },
    },
})
