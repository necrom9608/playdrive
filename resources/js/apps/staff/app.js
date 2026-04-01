import { createApp } from 'vue'
import { createPinia } from 'pinia'
import StaffApp from './App.vue'
import router from './router'

const app = createApp(StaffApp)
app.use(createPinia())
app.use(router)
app.mount('#app')
