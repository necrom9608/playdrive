import { apiFetch } from '../../../../../shared/services/api'

export function fetchPricingEngineOverview() {
    return apiFetch('/api/backoffice/pricing-engine')
}

export function createPricingProfile(payload) {
    return apiFetch('/api/backoffice/pricing-engine/profiles', {
        method: 'POST',
        body: JSON.stringify(payload),
    })
}

export function updatePricingProfile(id, payload) {
    return apiFetch(`/api/backoffice/pricing-engine/profiles/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    })
}

export function deletePricingProfile(id) {
    return apiFetch(`/api/backoffice/pricing-engine/profiles/${id}`, {
        method: 'DELETE',
    })
}

export function reorderPricingProfiles(items) {
    return apiFetch('/api/backoffice/pricing-engine/profiles/reorder', {
        method: 'POST',
        body: JSON.stringify({ items }),
    })
}

export function createPricingRule(profileId, payload) {
    return apiFetch(`/api/backoffice/pricing-engine/profiles/${profileId}/rules`, {
        method: 'POST',
        body: JSON.stringify(payload),
    })
}

export function updatePricingRule(id, payload) {
    return apiFetch(`/api/backoffice/pricing-engine/rules/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    })
}

export function deletePricingRule(id) {
    return apiFetch(`/api/backoffice/pricing-engine/rules/${id}`, {
        method: 'DELETE',
    })
}

export function reorderPricingRules(profileId, items) {
    return apiFetch(`/api/backoffice/pricing-engine/profiles/${profileId}/rules/reorder`, {
        method: 'POST',
        body: JSON.stringify({ items }),
    })
}
