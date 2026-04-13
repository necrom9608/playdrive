import { useEffect, useState } from 'react'
import SetupPage from './pages/SetupPage'
import {
  loadDesktopConfig,
  openConfiguredProfile,
  resetDesktopConfig,
  type DesktopConfig,
} from './lib/config'
import { isTauriRuntime } from './lib/runtime'

export default function App() {
  const [busy, setBusy] = useState(false)
  const [config, setConfig] = useState<DesktopConfig | null>(null)
  const [error, setError] = useState<string | null>(
    isTauriRuntime() ? null : 'Deze desktop shell werkt enkel binnen Tauri. Open dit project via npm run tauri:dev.'
  )

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
