import { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
    appId: 'be.playdrive.member',
    appName: 'PlayDrive',
    webDir: 'public/member',
    server: {
        androidScheme: 'http',
    },
    plugins: {
        SplashScreen: {
            launchShowDuration: 0,
        },
    },
}

export default config
