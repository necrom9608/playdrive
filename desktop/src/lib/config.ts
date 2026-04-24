import { invoke } from '@tauri-apps/api/core'
import type { DesktopProfileKey } from './profiles'

export type DesktopEnvironment = 'live' | 'test'

export type DesktopConfig = {
  environment: DesktopEnvironment
  deviceName: string
  deviceType: string
  fullscreen: boolean
  // Display second screen
  displayEnabled: boolean
  displayScreen: number
  displayFullscreen: boolean
  // Frontdesk screen index
  frontdeskScreen: number
}

type LegacyDesktopConfig = {
  // Legacy fields — kept for migration, ignored going forward
  serverUrl?: string
  tenantSlug?: string
  profile?: DesktopProfileKey
  // Current fields
  environment?: DesktopEnvironment
  deviceName?: string
  deviceType?: string
  fullscreen?: boolean
  displayEnabled?: boolean
  displayScreen?: number
  displayFullscreen?: boolean
  frontdeskScreen?: number
}

export function createDefaultConfig(): DesktopConfig {
  return {
    environment: 'test',
    deviceName: 'Frontdesk 1',
    deviceType: 'pos',
    fullscreen: false,
    displayEnabled: false,
    displayScreen: 1,
    displayFullscreen: true,
    frontdeskScreen: 0,
  }
}

export function normalizeDesktopConfig(input?: LegacyDesktopConfig | null): DesktopConfig | null {
  if (!input) {
    return null
  }

  const defaults = createDefaultConfig()

  return {
    environment: input.environment || defaults.environment,
    deviceName: input.deviceName?.trim() || defaults.deviceName,
    deviceType: input.deviceType?.trim() || defaults.deviceType,
    fullscreen: Boolean(input.fullscreen),
    displayEnabled: Boolean(input.displayEnabled),
    displayScreen: input.displayScreen ?? defaults.displayScreen,
    displayFullscreen: input.displayFullscreen !== undefined
      ? Boolean(input.displayFullscreen)
      : defaults.displayFullscreen,
    frontdeskScreen: input.frontdeskScreen ?? defaults.frontdeskScreen,
  }
}

export function buildBaseUrl(config: DesktopConfig): string {
  if (config.environment === 'live') {
    return 'https://playdrive.be'
  }
  return 'http://playdrive.test'
}

export function buildLaunchUrl(config: DesktopConfig): string {
  return `${buildBaseUrl(config)}/frontdesk`
}

export function buildDisplayUrl(config: DesktopConfig): string {
  return `${buildBaseUrl(config)}/display`
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

export async function getMonitorCount(): Promise<number> {
  return invoke<number>('get_monitor_count')
}
