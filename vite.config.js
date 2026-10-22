import { defineConfig, loadEnv } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ mode }) => {
    // Versie in de statusbalk: VITE_APP_VERSION als die gezet is, anders PATCH_VERSION
    // (het deployscript zet die vóór de build gelijk met .deploy/version).
    const env = loadEnv(mode, process.cwd(), '')
    const appVersion = env.VITE_APP_VERSION || env.PATCH_VERSION || '?'

    return {
        define: {
            'import.meta.env.VITE_APP_VERSION': JSON.stringify(appVersion),
        },
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/apps/frontdesk/app.js',
                    'resources/js/apps/backoffice/app.js',
                    'resources/js/apps/admin/app.js',
                    'resources/js/apps/kiosk/app.js',
                    'resources/js/apps/client/app.js',
                    'resources/js/apps/staff/app.js',
                    'resources/js/apps/display/app.js',
                    // Member app
                    'resources/css/member.css',
                    'resources/js/apps/member/app.js',
                    // Website
                    'resources/css/website.css',
                    'resources/js/apps/website/app.js',
                    // Portal
                    'resources/js/apps/portal/app.js',
                ],
                refresh: true,
            }),
            vue(),
            tailwindcss(),
        ],
    }
})
