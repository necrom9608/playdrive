import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

let echoInstance = null

function resolveRealtimeConfig() {
    const config = window.PlayDrive?.realtime ?? {}
    const isHttps = (config.scheme ?? window.location.protocol.replace(':', '')) === 'https'

    return {
        broadcaster: 'reverb',
        key: config.appKey ?? 'playdrive',
        wsHost: config.host ?? window.location.hostname,
        wsPort: Number(config.port ?? (isHttps ? 443 : 8080)),
        wssPort: Number(config.port ?? (isHttps ? 443 : 8080)),
        forceTLS: isHttps,
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
    }
}

export function getEcho() {
    if (echoInstance) {
        return echoInstance
    }

    window.Pusher = Pusher
    window.Pusher.logToConsole = true

    const config = resolveRealtimeConfig()
    console.log('[realtime] config', config)

    echoInstance = new Echo(config)

    window.__pusher = echoInstance.connector?.pusher

    if (window.__pusher) {
        window.__pusher.connection.bind('state_change', (states) => {
            console.log('[realtime] state change', states)
        })

        window.__pusher.connection.bind('connected', () => {
            console.log('[realtime] connected')
        })

        window.__pusher.connection.bind('error', (error) => {
            console.error('[realtime] connection error', error)
        })
    } else {
        console.warn('[realtime] no pusher connector found')
    }

    return echoInstance
}

export function leaveChannel(channelName) {
    if (!echoInstance || !channelName) {
        return
    }

    echoInstance.leave(channelName)
}
