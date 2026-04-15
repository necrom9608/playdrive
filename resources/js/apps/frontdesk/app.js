import { createApp } from 'vue'
import { createPinia } from 'pinia'
import axios from '@/lib/http'
import router from './router'
import FrontdeskApp from './App.vue'
import { frontdeskConfig } from './config/frontdeskConfig'
import { getDeviceRuntimeSummary } from './services/deviceService'
import { usePosStore } from './modules/pos/stores/usePosStore'

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

app.provide('frontdeskConfig', frontdeskConfig)
app.provide('frontdeskRuntime', getDeviceRuntimeSummary())

app.config.globalProperties.$frontdeskConfig = frontdeskConfig

const pinia = createPinia()

app.use(pinia)
app.use(router)

const posStore = usePosStore(pinia)
posStore.initializeDisplayBridge().catch(() => {})

app.mount('#app')
