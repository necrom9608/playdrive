export type DesktopProfileKey = 'frontdesk' | 'kiosk' | 'staff' | 'client' | 'display'

export type DesktopProfileDefinition = {
  key: DesktopProfileKey
  label: string
  route: string
  description: string
  defaultDeviceType: string
  fullscreenByDefault: boolean
}

export const DESKTOP_PROFILES: DesktopProfileDefinition[] = [
  {
    key: 'frontdesk',
    label: 'Frontdesk',
    route: '/frontdesk',
    description: 'Voor POS, reservaties en dagelijkse baliewerking.',
    defaultDeviceType: 'pos',
    fullscreenByDefault: true,
  },
  {
    key: 'kiosk',
    label: 'Kiosk',
    route: '/kiosk',
    description: 'Voor self-service check-in, scans en bezoekersflow.',
    defaultDeviceType: 'kiosk',
    fullscreenByDefault: true,
  },
  {
    key: 'staff',
    label: 'Staff',
    route: '/staff',
    description: 'Voor personeel, taken en aanwezigheden.',
    defaultDeviceType: 'staff_terminal',
    fullscreenByDefault: false,
  },
  {
    key: 'client',
    label: 'Client',
    route: '/client',
    description: 'Voor klantenportaal en klantgerichte flows.',
    defaultDeviceType: 'client_terminal',
    fullscreenByDefault: false,
  },
  {
    key: 'display',
    label: 'Display',
    route: '/display',
    description: 'Voor customer display of gekoppelde schermweergave.',
    defaultDeviceType: 'display',
    fullscreenByDefault: true,
  },
]

export function getProfileDefinition(profile: string | null | undefined): DesktopProfileDefinition | null {
  return DESKTOP_PROFILES.find(item => item.key === profile) ?? null
}
