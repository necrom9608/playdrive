import axios from 'axios'

export async function fetchBadgeTemplates() {
    const response = await axios.get('/api/backoffice/badge-templates')
    return response.data ?? []
}

export async function createBadgeTemplate(payload) {
    const response = await axios.post('/api/backoffice/badge-templates', payload)
    return response.data
}

export async function updateBadgeTemplate(id, payload) {
    const response = await axios.put(`/api/backoffice/badge-templates/${id}`, payload)
    return response.data
}

export async function deleteBadgeTemplate(id) {
    const response = await axios.delete(`/api/backoffice/badge-templates/${id}`)
    return response.data
}

export async function uploadBadgeTemplateImage(file, kind = 'element') {
    const formData = new FormData()
    formData.append('image', file)
    formData.append('kind', kind)

    const response = await axios.post('/api/backoffice/badge-templates/media', formData, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    })

    return response.data
}
