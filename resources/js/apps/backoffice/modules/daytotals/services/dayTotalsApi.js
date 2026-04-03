import axios from 'axios'

export async function fetchDayTotals(params = {}) {
    const { data } = await axios.get('/api/backoffice/day-totals', { params })
    return data.data
}

export function getDayTotalsExportUrl(params = {}) {
    const query = new URLSearchParams(
        Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== '')
    ).toString()

    return `/api/backoffice/day-totals/export${query ? `?${query}` : ''}`
}
