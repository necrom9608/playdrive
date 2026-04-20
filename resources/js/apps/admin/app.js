import axios from 'axios'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import AdminApp from './App.vue'

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.withCredentials = true

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken
}

const app = createApp(AdminApp)

app.use(createPinia())
app.use(router)

app.mount('#app')
