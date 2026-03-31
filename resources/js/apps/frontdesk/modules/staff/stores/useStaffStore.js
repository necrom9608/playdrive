import { defineStore } from 'pinia'
import { apiFetch } from '../../../../../shared/services/api'

export const useStaffStore = defineStore('frontdeskStaff', {
    state: () => ({
        summary: {
            staff_total: 0,
            checked_in_now: 0,
            checked_in_today: 0,
            checked_out_today: 0,
        },
        activeCheckins: [],
        recentEvents: [],
        loading: false,
        scanning: false,
        error: '',
        lastScanResult: null,
    }),

    actions: {
        async fetchOverview() {
            this.loading = true
            this.error = ''

            try {
                const response = await apiFetch('/api/frontdesk/staff-attendance')
                this.summary = response.data?.summary ?? this.summary
                this.activeCheckins = response.data?.active_checkins ?? []
                this.recentEvents = response.data?.recent_events ?? []
            } catch (error) {
                this.error = error?.data?.message || 'Personeelsgegevens konden niet geladen worden.'
            } finally {
                this.loading = false
            }
        },

        async scanCard(rfidUid) {
            this.scanning = true
            this.error = ''

            try {
                const response = await apiFetch('/api/frontdesk/staff-attendance/scan', {
                    method: 'POST',
                    body: JSON.stringify({ rfid_uid: rfidUid }),
                })

                this.lastScanResult = {
                    message: response.message,
                    action: response.action,
                    entry: response.entry,
                }

                await this.fetchOverview()

                return response
            } catch (error) {
                this.error = error?.data?.errors?.rfid_uid?.[0]
                    || error?.data?.message
                    || 'Scannen van personeelskaart mislukt.'
                throw error
            } finally {
                this.scanning = false
            }
        },

        clearLastScan() {
            this.lastScanResult = null
        },
    },
})
