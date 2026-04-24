import axios from 'axios'

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.withCredentials = true
axios.defaults.withXSRFToken = true

// Lees de CSRF token uit de meta tag (door Laravel in elke blade template gezet)
// en stuur die als X-CSRF-TOKEN header mee in elke request.
//
// Reden: in een Tauri webview wordt de XSRF-TOKEN cookie niet altijd correct
// teruggestuurd in axios requests, waardoor Laravel's VerifyCsrfToken
// middleware faalt. De meta tag is een betrouwbare fallback.
function readCsrfTokenFromMeta() {
    if (typeof document === 'undefined') return null
    const meta = document.querySelector('meta[name="csrf-token"]')
    return meta ? meta.getAttribute('content') : null
}

const csrfToken = readCsrfTokenFromMeta()
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken
}

export default axios
