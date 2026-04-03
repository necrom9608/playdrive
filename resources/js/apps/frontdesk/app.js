import { createApp } from 'vue'
import { createPinia } from 'pinia'
import axios from '@/lib/http'
import router from './router'
import FrontdeskApp from './App.vue'

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
