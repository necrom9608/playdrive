import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
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
})
