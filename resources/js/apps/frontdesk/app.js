import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import FrontdeskApp from './App.vue'

const app = createApp(FrontdeskApp)

app.use(createPinia())
app.use(router)

app.mount('#app')
