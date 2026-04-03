
import axios from 'axios'

export async function fetchDayTotals(params = {}) {
    const { data } = await axios.get('/api/backoffice/day-totals', { params })
    return data.data
}

export function getDayTotalsExportUrl(params = {}) {
    const query = new URLSearchParams(params).toString()
    return `/api/backoffice/day-totals/export?${query}`
}
