import { useMemo, useState } from 'react'
import {
  buildBaseUrl,
  buildLaunchUrl,
  createDefaultConfig,
  saveDesktopConfig,
  type DesktopConfig,
  type DesktopEnvironment,
} from '../lib/config'
import {
  DESKTOP_PROFILES,
  getProfileDefinition,
  type DesktopProfileKey,
} from '../lib/profiles'

type SetupPageProps = {
  initialConfig?: DesktopConfig | null
  saving?: boolean
  error?: string | null
  onLaunch: (config: DesktopConfig) => Promise<void>
  onReset?: () => Promise<void>
}

export default function SetupPage({
  initialConfig,
  saving = false,
  error,
  onLaunch,
  onReset,
}: SetupPageProps) {
  const defaults = useMemo(() => initialConfig ?? createDefaultConfig(), [initialConfig])
  const [tenantSlug, setTenantSlug] = useState(defaults.tenantSlug)
  const [environment, setEnvironment] = useState<DesktopEnvironment>(defaults.environment)
  const [profile, setProfile] = useState<DesktopProfileKey>(defaults.profile)
  const [deviceName, setDeviceName] = useState(defaults.deviceName)
  const [deviceType, setDeviceType] = useState(defaults.deviceType)
  const [fullscreen, setFullscreen] = useState(defaults.fullscreen)
  const [localError, setLocalError] = useState<string | null>(null)

  const selectedProfile = getProfileDefinition(profile)

  const suggestDeviceName = (profileKey: DesktopProfileKey) => {
    const definition = getProfileDefinition(profileKey)

    if (!definition) {
      return ''
    }

    const current = deviceName.trim().toLowerCase()
    const knownDefaults = ['frontdesk 1', 'kiosk 1', 'staff 1', 'client 1', 'display 1']

    if (!current || knownDefaults.includes(current)) {
      return `${definition.label} 1`
    }

    return deviceName
  }

  const handleProfileChange = (nextProfile: DesktopProfileKey) => {
    setProfile(nextProfile)
    const definition = getProfileDefinition(nextProfile)

    if (definition) {
      setDeviceType(definition.defaultDeviceType)
      setFullscreen(definition.fullscreenByDefault)
      setDeviceName(suggestDeviceName(nextProfile))
    }
  }

  const trimmedTenant = tenantSlug.trim().toLowerCase().replace(/[^a-z0-9-]/g, '')
  const configPreview: DesktopConfig = {
    tenantSlug: trimmedTenant,
    environment,
    profile,
    deviceName: deviceName.trim(),
    deviceType: deviceType.trim(),
    fullscreen,
  }

  const baseUrl = trimmedTenant ? buildBaseUrl(configPreview) : ''
  const launchUrl = trimmedTenant ? buildLaunchUrl(configPreview) : ''

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault()
    setLocalError(null)

    const finalTenant = tenantSlug.trim().toLowerCase().replace(/[^a-z0-9-]/g, '')
    const trimmedDeviceName = deviceName.trim()
    const trimmedDeviceType = deviceType.trim()

    if (!finalTenant) {
      setLocalError('Geef een tenantnaam in, bijvoorbeeld game-inn.')
      return
    }

    if (!/^[a-z0-9-]+$/.test(finalTenant)) {
      setLocalError('Tenantnaam mag enkel kleine letters, cijfers en koppelteken bevatten.')
      return
    }

    if (!trimmedDeviceName) {
      setLocalError('Geef een toestelnaam in.')
      return
    }

    if (!trimmedDeviceType) {
      setLocalError('Geef een device type in.')
      return
    }

    const config: DesktopConfig = {
      tenantSlug: finalTenant,
      environment,
      profile,
      deviceName: trimmedDeviceName,
      deviceType: trimmedDeviceType,
      fullscreen,
    }

    const saved = await saveDesktopConfig(config)
    await onLaunch(saved)
  }

  return (
    <div className="page-shell">
      <div className="panel hero-panel">
        <div className="eyebrow">Playdrive Desktop</div>
        <h1>Toestel instellen</h1>
        <p>
          Stel dit toestel één keer in. Daarna start Playdrive Desktop automatisch meteen door
          naar het gekozen profiel. Voor testen kan je dit scherm opnieuw openen via <code>?setup=1</code>.
        </p>
      </div>

      <form className="panel form-panel" onSubmit={handleSubmit}>
        <div className="field-grid">
          <label className="field">
            <span>Tenant</span>
            <input
              value={tenantSlug}
              onChange={(event) => setTenantSlug(event.target.value)}
              placeholder="game-inn"
            />
          </label>

          <label className="field">
            <span>Omgeving</span>
            <select
              value={environment}
              onChange={(event) => setEnvironment(event.target.value as DesktopEnvironment)}
            >
              <option value="test">Test</option>
              <option value="live">Live</option>
            </select>
          </label>

          <label className="field">
            <span>Profiel</span>
            <select
              value={profile}
              onChange={(event) => handleProfileChange(event.target.value as DesktopProfileKey)}
            >
              {DESKTOP_PROFILES.map(item => (
                <option key={item.key} value={item.key}>
                  {item.label}
                </option>
              ))}
            </select>
          </label>

          <label className="field">
            <span>Toestelnaam</span>
            <input
              value={deviceName}
              onChange={(event) => setDeviceName(event.target.value)}
              placeholder="Frontdesk 1"
            />
          </label>

          <label className="field">
            <span>Device type</span>
            <input
              value={deviceType}
              onChange={(event) => setDeviceType(event.target.value)}
              placeholder="pos"
            />
          </label>
        </div>

        <label className="toggle-row">
          <input
            type="checkbox"
            checked={fullscreen}
            onChange={(event) => setFullscreen(event.target.checked)}
          />
          <span>Open in fullscreen</span>
        </label>

        {selectedProfile ? (
          <div className="profile-preview">
            <strong>{selectedProfile.label}</strong>
            <p>{selectedProfile.description}</p>
            <code>{selectedProfile.route}</code>
          </div>
        ) : null}

        {baseUrl ? (
          <div className="profile-preview">
            <strong>Doeladres</strong>
            <p>Basisadres: <code>{baseUrl}</code></p>
            <p>Profieladres: <code>{launchUrl}</code></p>
          </div>
        ) : null}

        {(localError || error) ? <div className="error-box">{localError ?? error}</div> : null}

        <div className="button-row">
          <button type="submit" disabled={saving}>
            {saving ? 'Opslaan…' : 'Opslaan en starten'}
          </button>
          {onReset ? (
            <button type="button" className="ghost" onClick={() => void onReset()} disabled={saving}>
              Reset config
            </button>
          ) : null}
        </div>
      </form>
    </div>
  )
}
