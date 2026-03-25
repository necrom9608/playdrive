import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import BackofficeApp from './App.vue'

const app = createApp(BackofficeApp)

app.use(createPinia())
app.use(router)

app.mount('#app')
