import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

let echoInstance = null

function resolveRealtimeConfig() {
    const config = window.PlayDrive?.realtime ?? {}
    const isHttps = (config.scheme ?? window.location.protocol.replace(':', '')) === 'https'

    const fallbackPort = isHttps ? 443 : 80
    const port = Number(config.port ?? fallbackPort)

    return {
        broadcaster: 'reverb',
        key: config.appKey ?? 'playdrive',
        wsHost: config.host ?? window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: isHttps,
        enabledTransports: isHttps ? ['wss'] : ['ws'],
        disableStats: true,
    }
}

export function getEcho() {
    if (echoInstance) {
        return echoInstance
    }

    window.Pusher = Pusher
    echoInstance = new Echo(resolveRealtimeConfig())

    return echoInstance
}

export function leaveChannel(channelName) {
    if (!echoInstance || !channelName) {
        return
    }

    echoInstance.leave(channelName)
}
