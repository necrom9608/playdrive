import type { DesktopConfig } from '../lib/config'
import { buildLaunchUrl } from '../lib/config'

type LaunchPageProps = {
  config: DesktopConfig
  busy?: boolean
  error?: string | null
  onLaunch: () => Promise<void>
  onBack: () => void
  onReset: () => Promise<void>
}

export default function LaunchPage({
  config,
  busy = false,
  error,
  onLaunch,
  onBack,
  onReset,
}: LaunchPageProps) {
  const launchUrl = buildLaunchUrl(config)

  return (
    <div className="page-shell">
      <div className="panel hero-panel">
        <div className="eyebrow">Playdrive Desktop</div>
        <h1>{config.deviceName}</h1>
        <p>Het toestel is geconfigureerd en klaar om het gekozen Playdrive-profiel te openen.</p>
      </div>

      <div className="panel summary-panel">
        <dl className="summary-grid">
          <div>
            <dt>Server</dt>
            <dd>{config.serverUrl}</dd>
          </div>
          <div>
            <dt>Profiel</dt>
            <dd>{config.profile}</dd>
          </div>
          <div>
            <dt>Device type</dt>
            <dd>{config.deviceType}</dd>
          </div>
          <div>
            <dt>Fullscreen</dt>
            <dd>{config.fullscreen ? 'Ja' : 'Nee'}</dd>
          </div>
          <div className="summary-full">
            <dt>Launch URL</dt>
            <dd><code>{launchUrl}</code></dd>
          </div>
        </dl>

        {error ? <div className="error-box">{error}</div> : null}

        <div className="button-row">
          <button onClick={onLaunch} disabled={busy}>{busy ? 'Starten…' : 'Start profiel'}</button>
          <button type="button" className="secondary" onClick={onBack} disabled={busy}>Wijzigen</button>
          <button type="button" className="ghost" onClick={onReset} disabled={busy}>Reset config</button>
        </div>
      </div>
    </div>
  )
}
