import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import axios from 'axios'
import '../css/app.css'

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
const token = localStorage.getItem('token')
if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
}

axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            localStorage.removeItem('token')
            router.push('/login')
        }
        return Promise.reject(error)
    }
)

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')

// Keep shared hosting backend warm — ping every 60 s to prevent cold starts
setInterval(() => {
  axios.get('/api/ping').catch(() => {})
}, 60000)

