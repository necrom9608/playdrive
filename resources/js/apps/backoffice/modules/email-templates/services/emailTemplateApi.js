import { apiFetch } from '../../../../../shared/services/api'

export function fetchEmailTemplates() {
    return apiFetch('/api/backoffice/email-templates')
}

export function updateEmailTemplate(key, data) {
    return apiFetch(`/api/backoffice/email-templates/${key}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    })
}

export function resetEmailTemplate(key) {
    return apiFetch(`/api/backoffice/email-templates/${key}/reset`, {
        method: 'POST',
    })
}

export function previewEmailTemplate(key, data) {
    return apiFetch(`/api/backoffice/email-templates/${key}/preview`, {
        method: 'POST',
        body: JSON.stringify(data),
    })
}
