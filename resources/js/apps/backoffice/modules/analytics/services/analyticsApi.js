import axios from '@/lib/http'

export async function fetchDashboard() {
    const { data } = await axios.get('/api/backoffice/analytics/dashboard')
    return data?.data || {}
}

export async function fetchReporting(params = {}) {
    const { data } = await axios.get('/api/backoffice/analytics/reporting', { params })
    return data?.data || {}
}
