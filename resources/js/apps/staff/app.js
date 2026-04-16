import { createApp } from 'vue'
import { createPinia } from 'pinia'
import StaffApp from './App.vue'
import router from './router'
import { usePwaStore } from './stores/pwaStore'

const pinia = createPinia()
const app = createApp(StaffApp)
app.use(pinia)
app.use(router)
app.mount('#app')

// Vang het event zo vroeg mogelijk op, vóór enige navigatie
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault()
  usePwaStore(pinia).setInstallPrompt(e)
})

window.addEventListener('appinstalled', () => {
  usePwaStore(pinia).isInstalled = true
  usePwaStore(pinia).installPrompt = null
})

if ('serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    try {
      await navigator.serviceWorker.register('/staff-sw.js?v=3', {
        scope: '/staff/',
        updateViaCache: 'none',
      })
    } catch (error) {
      console.error('Staff service worker registration failed', error)
    }
  })
}
