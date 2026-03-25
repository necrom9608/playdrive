import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ClientApp from './App.vue'

const app = createApp(ClientApp)

app.use(createPinia())

app.mount('#app')
