import { createApp } from 'vue'
import { createPinia } from 'pinia'
import KioskApp from './App.vue'

const app = createApp(KioskApp)

app.use(createPinia())

app.mount('#app')
