import axios from 'axios'

export async function fetchAccounting() {
    const { data } = await axios.get('/api/backoffice/accounting')
    return data.data
}

export async function updateAccountingSettings(payload) {
    const { data } = await axios.put('/api/backoffice/accounting/settings', payload)
    return data
}

export async function sendAccountingExport(params) {
    const { data } = await axios.post('/api/backoffice/accounting/send', params)
    return data
}

export function errorMessage(error, fallback = 'Er ging iets mis.') {
    const response = error?.response?.data

    if (response?.errors) {
        const first = Object.values(response.errors)[0]
        if (Array.isArray(first) && first.length) {
            return first[0]
        }
    }

    return response?.message || fallback
}
