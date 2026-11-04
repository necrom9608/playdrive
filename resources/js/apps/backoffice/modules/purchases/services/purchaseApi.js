import axios from 'axios'

const base = '/api/backoffice/purchases'

export async function fetchPurchases(params = {}) {
    const { data } = await axios.get(base, { params })
    return data
}

export async function fetchPurchaseDefaults() {
    const { data } = await axios.get(`${base}/defaults`)
    return data.data
}

export async function fetchPurchase(id) {
    const { data } = await axios.get(`${base}/${id}`)
    return data.data
}

export async function createPurchase(payload) {
    const { data } = await axios.post(base, payload)
    return data
}

export async function updatePurchase(id, payload) {
    const { data } = await axios.put(`${base}/${id}`, payload)
    return data
}

export async function deletePurchase(id) {
    const { data } = await axios.delete(`${base}/${id}`)
    return data
}

export async function sharePurchase(id, sendMail = false) {
    const { data } = await axios.post(`${base}/${id}/share`, { send_mail: sendMail })
    return data
}

export async function markPurchasePaid(id) {
    const { data } = await axios.post(`${base}/${id}/paid`)
    return data
}

export async function markPurchaseUnpaid(id) {
    const { data } = await axios.post(`${base}/${id}/unpaid`)
    return data
}

export async function cancelPurchase(id) {
    const { data } = await axios.post(`${base}/${id}/cancel`)
    return data
}

export async function resendPurchasePdf(id) {
    const { data } = await axios.post(`${base}/${id}/resend-pdf`)
    return data
}

export async function uploadPurchasePhoto(id, file) {
    const form = new FormData()
    form.append('photo', file)
    const { data } = await axios.post(`${base}/${id}/photos`, form)
    return data
}

export async function deletePurchasePhoto(id, index) {
    const { data } = await axios.delete(`${base}/${id}/photos/${index}`)
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
