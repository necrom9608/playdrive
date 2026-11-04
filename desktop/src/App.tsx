import { useEffect, useState } from 'react'
import { invoke } from '@tauri-apps/api/core'
import { listen } from '@tauri-apps/api/event'
import SetupPage from './pages/SetupPage'
import {
  loadDesktopConfig,
  openConfiguredProfile,
  resetDesktopConfig,
  type DesktopConfig,
} from './lib/config'
import { isTauriRuntime } from './lib/runtime'

// Zie src-tauri/src/commands/updater.rs
type UpdateStatus = {
  current: string
  available: string | null
  version: string | null
  phase: string | null
  error: string | null
}

export default function App() {
  const [busy, setBusy] = useState(false)
  const [config, setConfig] = useState<DesktopConfig | null>(null)
  const [error, setError] = useState<string | null>(
    isTauriRuntime() ? null : 'Deze desktop shell werkt enkel binnen Tauri. Open dit project via npm run tauri:dev.'
  )

  // Bijwerken bij het opstarten: de app toont dit venster tot de installer overneemt.
  const [update, setUpdate] = useState<UpdateStatus | null>(null)

  useEffect(() => {
    if (!isTauriRuntime()) return
    let unlisten: (() => void) | undefined
    invoke<UpdateStatus>('desktop_update_status').then(setUpdate).catch(() => {})
    listen<UpdateStatus>('desktop-update', (event) => {
      setUpdate(event.payload)
    }).then((fn) => { unlisten = fn })
    return () => unlisten?.()
  }, [])

  useEffect(() => {
    const bootstrap = async () => {
      try {
        const storedConfig = await loadDesktopConfig()
        setConfig(storedConfig)
      } catch (loadError) {
        setError(loadError instanceof Error ? loadError.message : 'Configuratie laden mislukt.')
      }
    }

    bootstrap()
  }, [])

  const handleLaunch = async (savedConfig: DesktopConfig) => {
    setBusy(true)
    setError(null)

    try {
      setConfig(savedConfig)
      await openConfiguredProfile(savedConfig)
    } catch (launchError) {
        console.error('Open configured profile failed:', launchError)

        const message =
            launchError instanceof Error
                ? launchError.message
                : typeof launchError === 'string'
                    ? launchError
                    : JSON.stringify(launchError)

        setError(`Opstarten mislukt: ${message}`)
    } finally {
      setBusy(false)
    }
  }

  const handleReset = async () => {
    setBusy(true)
    setError(null)

    try {
      await resetDesktopConfig()
      setConfig(null)
    } catch (resetError) {
      setError(resetError instanceof Error ? resetError.message : 'Configuratie resetten mislukt.')
    } finally {
      setBusy(false)
    }
  }

  if (update?.phase) {
    return (
      <div className="page-shell">
        <div className="panel hero-panel">
          <div className="eyebrow">Playdrive Desktop</div>
          <h1>Bijwerken{update.version ? ` naar ${update.version}` : ''}…</h1>
          <p>
            {update.phase === 'installing'
              ? 'De nieuwe versie wordt geïnstalleerd. De app start daarna vanzelf opnieuw.'
              : 'De nieuwe versie wordt gedownload. Even geduld.'}
          </p>
        </div>
      </div>
    )
  }

  return (
    <SetupPage
      initialConfig={config}
      saving={busy}
      error={error}
      onLaunch={handleLaunch}
      onReset={handleReset}
    />
  )
}
