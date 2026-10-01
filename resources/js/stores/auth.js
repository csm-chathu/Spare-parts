import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

export const useAuthStore = defineStore('auth', () => {
    const token    = ref(localStorage.getItem('token'))
    const user     = ref(JSON.parse(localStorage.getItem('user') || 'null'))
    const features = ref(JSON.parse(localStorage.getItem('features') || '[]'))

    async function fetchFeatures() {
        const { data } = await axios.get('/api/me/features')
        features.value = data
        localStorage.setItem('features', JSON.stringify(data))
    }

    async function login(username, password) {
        const { data } = await axios.post('/login', { username, password })
        token.value = data.token
        user.value  = data.user
        localStorage.setItem('token', data.token)
        localStorage.setItem('user', JSON.stringify(data.user))
        axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
        await fetchFeatures()
    }

    async function logout() {
        try {
            await axios.post('/api/logout')
        } finally {
            token.value    = null
            user.value     = null
            features.value = []
            localStorage.removeItem('token')
            localStorage.removeItem('user')
            localStorage.removeItem('features')
            delete axios.defaults.headers.common['Authorization']
        }
    }

    function hasFeature(key) {
        return features.value.some(f => f.key === key)
    }

    return { token, user, features, login, logout, fetchFeatures, hasFeature }
})
