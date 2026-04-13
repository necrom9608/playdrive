import { invoke } from '@tauri-apps/api/core'
import type { DesktopProfileKey } from './profiles'

export type DesktopEnvironment = 'live' | 'test'

export type DesktopConfig = {
  tenantSlug: string
  environment: DesktopEnvironment
  profile: DesktopProfileKey
  deviceName: string
  deviceType: string
  fullscreen: boolean
}

type LegacyDesktopConfig = {
  serverUrl?: string
  tenantSlug?: string
  environment?: DesktopEnvironment
  profile?: DesktopProfileKey
  deviceName?: string
  deviceType?: string
  fullscreen?: boolean
}

function normalizeTenantFromUrl(serverUrl?: string): { tenantSlug: string; environment: DesktopEnvironment } {
  if (!serverUrl) {
    return { tenantSlug: '', environment: 'test' }
  }

  try {
    const url = new URL(serverUrl)
    const host = url.hostname.toLowerCase()

    if (host.endsWith('.playdrive.be')) {
      return {
        tenantSlug: host.replace(/\.playdrive\.be$/, ''),
        environment: 'live',
      }
    }

    if (host.endsWith('.playdrive.test')) {
      return {
        tenantSlug: host.replace(/\.playdrive\.test$/, ''),
        environment: 'test',
      }
    }
  } catch {
    // ignore parse failures
  }

  return { tenantSlug: '', environment: 'test' }
}

export function createDefaultConfig(): DesktopConfig {
  return {
    tenantSlug: '',
    environment: 'test',
    profile: 'frontdesk',
    deviceName: 'Frontdesk 1',
    deviceType: 'pos',
    fullscreen: false,
  }
}

export function normalizeDesktopConfig(input?: LegacyDesktopConfig | null): DesktopConfig | null {
  if (!input) {
    return null
  }

  const fallback = normalizeTenantFromUrl(input.serverUrl)
  const defaults = createDefaultConfig()

  return {
    tenantSlug: input.tenantSlug?.trim() || fallback.tenantSlug || defaults.tenantSlug,
    environment: input.environment || fallback.environment || defaults.environment,
    profile: input.profile || defaults.profile,
    deviceName: input.deviceName?.trim() || defaults.deviceName,
    deviceType: input.deviceType?.trim() || defaults.deviceType,
    fullscreen: Boolean(input.fullscreen),
  }
}

export function buildBaseUrl(config: DesktopConfig): string {
  const tenant = config.tenantSlug.trim().toLowerCase()

  if (!tenant) {
    return ''
  }

  if (config.environment === 'live') {
    return `https://${tenant}.playdrive.be`
  }

  return `http://${tenant}.playdrive.test`
}

export function buildLaunchUrl(config: DesktopConfig): string {
  const baseUrl = buildBaseUrl(config).replace(/\/+$/, '')
  const routeMap: Record<DesktopProfileKey, string> = {
    frontdesk: '/frontdesk',
    kiosk: '/kiosk',
    staff: '/staff',
    client: '/client',
    display: '/display',
  }

  return `${baseUrl}${routeMap[config.profile] ?? ''}`
}

export async function loadDesktopConfig(): Promise<DesktopConfig | null> {
  const config = await invoke<LegacyDesktopConfig | null>('load_desktop_config')
  return normalizeDesktopConfig(config)
}

export async function saveDesktopConfig(config: DesktopConfig): Promise<DesktopConfig> {
  const saved = await invoke<LegacyDesktopConfig>('save_desktop_config', { config })
  return normalizeDesktopConfig(saved) ?? config
}

export async function resetDesktopConfig(): Promise<boolean> {
  return invoke<boolean>('reset_desktop_config')
}

export async function openConfiguredProfile(config: DesktopConfig): Promise<void> {
  await invoke('open_configured_profile', { config })
}
