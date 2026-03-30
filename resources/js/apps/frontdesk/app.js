import { createApp } from 'vue'
import { createPinia } from 'pinia'
import axios from 'axios'
import router from './router'
import FrontdeskApp from './App.vue'

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.withCredentials = true

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken
}

axios.interceptors.response.use(
    response => response,
    error => {
        if (error?.response?.status === 401) {
            window.dispatchEvent(new CustomEvent('frontdesk-auth-required'))
        }

        return Promise.reject(error)
    }
)

const app = createApp(FrontdeskApp)

app.use(createPinia())
app.use(router)

app.mount('#app')
