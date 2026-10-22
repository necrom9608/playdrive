import axios from 'axios'

const base = '/api/backoffice/mail-assistant'

export async function fetchInbox(params = {}) {
    const { data } = await axios.get(base, { params })
    return data.data
}

export async function fetchMessage(id) {
    const { data } = await axios.get(`${base}/messages/${id}`)
    return data.data
}

export async function saveDraft(id, draftBody) {
    const { data } = await axios.put(`${base}/messages/${id}/draft`, { draft_body: draftBody })
    return data
}

export async function sendReply(id, body) {
    const { data } = await axios.post(`${base}/messages/${id}/send`, { body })
    return data
}

export async function regenerateDraft(id) {
    const { data } = await axios.post(`${base}/messages/${id}/regenerate`)
    return data
}

export async function setMessageStatus(id, status) {
    const { data } = await axios.post(`${base}/messages/${id}/status`, { status })
    return data
}

export async function checkMailbox() {
    const { data } = await axios.post(`${base}/check`)
    return data
}

export async function fetchSettings() {
    const { data } = await axios.get(`${base}/settings`)
    return data.data
}

export async function updateSettings(payload) {
    const { data } = await axios.put(`${base}/settings`, payload)
    return data
}

export async function testConnection() {
    const { data } = await axios.post(`${base}/settings/test`)
    return data.data
}

export async function fetchFaqs() {
    const { data } = await axios.get(`${base}/faqs`)
    return data.data
}

export async function createFaq(payload) {
    const { data } = await axios.post(`${base}/faqs`, payload)
    return data
}

export async function updateFaq(id, payload) {
    const { data } = await axios.put(`${base}/faqs/${id}`, payload)
    return data
}

export async function deleteFaq(id) {
    const { data } = await axios.delete(`${base}/faqs/${id}`)
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

export const STATUS_META = {
    new: { label: 'Wordt opgesteld', class: 'bg-slate-700/60 text-slate-300' },
    draft: { label: 'Concept klaar', class: 'bg-blue-500/15 text-blue-300' },
    manual: { label: 'Medewerker', class: 'bg-rose-500/15 text-rose-300' },
    ignored: { label: 'Genegeerd', class: 'bg-slate-800 text-slate-400' },
    sent: { label: 'Verstuurd', class: 'bg-emerald-500/15 text-emerald-300' },
    done: { label: 'Afgehandeld', class: 'bg-emerald-500/10 text-emerald-200' },
    error: { label: 'Fout', class: 'bg-amber-500/15 text-amber-300' },
}

export function formatDateTime(iso) {
    if (!iso) return ''
    const date = new Date(iso)
    const today = new Date()
    const sameDay = date.toDateString() === today.toDateString()
    const yesterday = new Date(today)
    yesterday.setDate(today.getDate() - 1)

    const time = date.toLocaleTimeString('nl-BE', { hour: '2-digit', minute: '2-digit' })
    if (sameDay) return time
    if (date.toDateString() === yesterday.toDateString()) return `gisteren ${time}`

    return date.toLocaleDateString('nl-BE', { day: 'numeric', month: 'short' }) + ` ${time}`
}
