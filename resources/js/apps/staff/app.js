import { createApp } from 'vue'
import { createPinia } from 'pinia'
import StaffApp from './App.vue'
import router from './router'

const app = createApp(StaffApp)
app.use(createPinia())
app.use(router)
app.mount('#app')

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/staff-sw.js').catch((error) => {
      console.error('Staff service worker registration failed', error)
    })
  })
}
