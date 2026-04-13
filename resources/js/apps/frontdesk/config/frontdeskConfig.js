import { getRuntimeEnvironment } from '../../../shared/runtime/environment'

function normalizeBasePath(path, fallback = '/') {
    const rawValue = typeof path === 'string' ? path.trim() : ''

    if (!rawValue) {
        return fallback
    }

    const value = rawValue.startsWith('/') ? rawValue : `/${rawValue}`

    if (value === '/') {
        return value
    }

    return value.replace(/\/+$/, '')
}

function joinPath(basePath, path = '') {
    const normalizedBase = normalizeBasePath(basePath)
    const normalizedPath = String(path || '').trim()

    if (!normalizedPath || normalizedPath === '/') {
        return normalizedBase
    }

    if (/^https?:\/\//i.test(normalizedPath)) {
        return normalizedPath
    }

    if (normalizedPath.startsWith('/')) {
        return `${normalizedBase}${normalizedPath}`.replace(/\/+/g, '/')
    }

    return `${normalizedBase}/${normalizedPath}`.replace(/\/+/g, '/')
}

const playDrive = typeof window !== 'undefined' ? (window.PlayDrive || {}) : {}

const frontdeskBasePath = normalizeBasePath(playDrive.frontdeskBasePath || '/frontdesk')
const apiBasePath = normalizeBasePath(playDrive.apiBasePath || '/api/frontdesk')

export const frontdeskConfig = {
    runtime: getRuntimeEnvironment(),
    tenantName: playDrive.tenantName || 'PlayDrive',
    tenantLogoUrl: playDrive.tenantLogoUrl || '',
    frontdeskBasePath,
    apiBasePath,
    realtime: playDrive.realtime || {},
    buildRoute(path = '') {
        return joinPath(frontdeskBasePath, path)
    },
    buildApiUrl(path = '') {
        return joinPath(apiBasePath, path)
    },
}

export function getFrontdeskConfig() {
    return frontdeskConfig
}
