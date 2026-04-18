import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import ClientApp from './App.vue'

const app = createApp(ClientApp)

app.use(createPinia())
app.use(router)

app.mount('#app')
