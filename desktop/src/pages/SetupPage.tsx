import { useEffect, useMemo, useState } from 'react'
import {
    buildBaseUrl,
    createDefaultConfig,
    saveDesktopConfig,
    getMonitorCount,
    type DesktopConfig,
    type DesktopEnvironment,
} from '../lib/config'

type SetupPageProps = {
    initialConfig?: DesktopConfig | null
    saving?: boolean
    error?: string | null
    onLaunch: (config: DesktopConfig) => Promise<void>
    onReset?: () => Promise<void>
}

function MonitorSelect({
    value,
    onChange,
    monitorCount,
    disabled,
}: {
    value: number
    onChange: (v: number) => void
    monitorCount: number
    disabled?: boolean
}) {
    if (monitorCount <= 1) {
        return (
            <select value={value} onChange={(e) => onChange(Number(e.target.value))} disabled={disabled}>
                <option value={0}>Scherm 1</option>
            </select>
        )
    }

    return (
        <select value={value} onChange={(e) => onChange(Number(e.target.value))} disabled={disabled}>
            {Array.from({ length: monitorCount }, (_, i) => (
                <option key={i} value={i}>
                    Scherm {i + 1}
                </option>
            ))}
        </select>
    )
}

export default function SetupPage({
    initialConfig,
    saving = false,
    error,
    onLaunch,
    onReset,
}: SetupPageProps) {
    const defaults = useMemo(() => initialConfig ?? createDefaultConfig(), [initialConfig])

    const [environment, setEnvironment] = useState<DesktopEnvironment>(defaults.environment)
    const [deviceName, setDeviceName] = useState(defaults.deviceName)

    // Frontdesk
    const [frontdeskScreen, setFrontdeskScreen] = useState(defaults.frontdeskScreen)
    const [frontdeskFullscreen, setFrontdeskFullscreen] = useState(defaults.fullscreen)

    // Display
    const [displayEnabled, setDisplayEnabled] = useState(defaults.displayEnabled)
    const [displayScreen, setDisplayScreen] = useState(defaults.displayScreen)
    const [displayFullscreen, setDisplayFullscreen] = useState(defaults.displayFullscreen)

    const [monitorCount, setMonitorCount] = useState(2)
    const [localError, setLocalError] = useState<string | null>(null)

    useEffect(() => {
        getMonitorCount()
            .then((count) => setMonitorCount(Math.max(count, 1)))
            .catch(() => setMonitorCount(2))
    }, [])

    const configPreview: DesktopConfig = {
        environment,
        profile: 'frontdesk',
        deviceName: deviceName.trim(),
        deviceType: 'pos',
        fullscreen: frontdeskFullscreen,
        displayEnabled,
        displayScreen,
        displayFullscreen,
        frontdeskScreen,
    }


    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault()
        setLocalError(null)

        const trimmedDeviceName = deviceName.trim()

        if (!trimmedDeviceName) {
            setLocalError('Geef een toestelnaam in.')
            return
        }

        if (displayEnabled && displayScreen === frontdeskScreen && monitorCount > 1) {
            setLocalError('Frontdesk en display kunnen niet op hetzelfde scherm staan.')
            return
        }

        const config: DesktopConfig = {
            ...configPreview,
            deviceName: trimmedDeviceName,
        }

        const saved = await saveDesktopConfig(config)
        await onLaunch(saved)
    }

    return (
        <div className="page-shell">
            <div
                style={{
                    width: '100%',
                    maxWidth: '520px',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: '24px',
                }}
            >
                <div style={{ display: 'flex', justifyContent: 'center' }}>
                    <img
                        src="/images/logos/logo_header.png"
                        alt="Playdrive"
                        style={{
                            height: '72px',
                            width: 'auto',
                            objectFit: 'contain',
                        }}
                    />
                </div>

                <form className="panel form-panel" onSubmit={handleSubmit}>

                    {/* ── Omgeving + Toestelnaam ── */}
                    <div className="field-grid">
                        <label className="field">
                            <span>Omgeving</span>
                            <select
                                value={environment}
                                onChange={(e) => setEnvironment(e.target.value as DesktopEnvironment)}
                            >
                                <option value="test">Test</option>
                                <option value="live">Live</option>
                            </select>
                        </label>

                        <label className="field">
                            <span>Toestelnaam</span>
                            <input
                                value={deviceName}
                                onChange={(e) => setDeviceName(e.target.value)}
                                placeholder="Frontdesk 1"
                            />
                        </label>
                    </div>

                    {/* ── Frontdesk sectie ── */}
                    <div className="section-block">
                        <div className="section-header">
                            <span className="section-title">Frontdesk</span>
                        </div>
                        <div className="section-row">
                            <label className="field field-grow">
                                <span>Scherm</span>
                                <MonitorSelect
                                    value={frontdeskScreen}
                                    onChange={setFrontdeskScreen}
                                    monitorCount={monitorCount}
                                />
                            </label>
                            <label className="toggle-inline">
                                <input
                                    type="checkbox"
                                    checked={frontdeskFullscreen}
                                    onChange={(e) => setFrontdeskFullscreen(e.target.checked)}
                                />
                                <span>Fullscreen</span>
                            </label>
                        </div>
                    </div>

                    {/* ── Display sectie ── */}
                    <div className={`section-block${displayEnabled ? '' : ' section-muted'}`}>
                        <div className="section-header">
                            <label className="toggle-inline">
                                <input
                                    type="checkbox"
                                    checked={displayEnabled}
                                    onChange={(e) => setDisplayEnabled(e.target.checked)}
                                />
                                <span className="section-title">Display</span>
                            </label>
                        </div>
                        {displayEnabled && (
                            <div className="section-row">
                                <label className="field field-grow">
                                    <span>Scherm</span>
                                    <MonitorSelect
                                        value={displayScreen}
                                        onChange={setDisplayScreen}
                                        monitorCount={monitorCount}
                                    />
                                </label>
                                <label className="toggle-inline">
                                    <input
                                        type="checkbox"
                                        checked={displayFullscreen}
                                        onChange={(e) => setDisplayFullscreen(e.target.checked)}
                                    />
                                    <span>Fullscreen</span>
                                </label>
                            </div>
                        )}
                    </div>

                    {/* ── Server preview ── */}
                    <div className="url-preview">
                        <code>{environment === 'live' ? 'https://playdrive.be' : 'http://playdrive.test'}</code>
                    </div>

                    {(localError || error) ? (
                        <div className="error-box">{localError ?? error}</div>
                    ) : null}

                    <div className="button-row" style={{ justifyContent: 'flex-end' }}>
                        {onReset ? (
                            <button
                                type="button"
                                className="ghost"
                                onClick={() => void onReset()}
                                disabled={saving}
                            >
                                Reset config
                            </button>
                        ) : null}
                        <button type="submit" disabled={saving}>
                            {saving ? 'Opslaan…' : 'Opslaan en starten'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    )
}
