<template>
  <div class="min-h-screen flex">

    <!-- ── Left branding panel ── -->
    <div class="hidden lg:flex lg:w-[52%] xl:w-[55%] flex-col relative overflow-hidden" style="background: #0f172a;">
      <!-- subtle grid texture -->
      <div class="absolute inset-0 opacity-[0.04]"
        style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px);
               background-size: 40px 40px;"></div>

      <!-- top-left glow -->
      <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full opacity-20"
        style="background: radial-gradient(circle, #3b82f6, transparent 70%);"></div>

      <!-- bottom-right glow -->
      <div class="absolute -bottom-24 -right-24 w-80 h-80 rounded-full opacity-15"
        style="background: radial-gradient(circle, #6366f1, transparent 70%);"></div>

      <div class="relative z-10 flex flex-col h-full px-12 py-10">
        <!-- Logo + name -->
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center shadow-lg">
            <img v-if="branding.logo_url" :src="branding.logo_url" alt="Logo"
              class="w-8 h-8 rounded-lg object-contain" />
            <svg v-else class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
            </svg>
          </div>
          <span class="text-white font-semibold text-lg tracking-tight">{{ branding.shop_name }}</span>
        </div>

        <!-- Centre content -->
        <div class="flex-1 flex flex-col justify-center max-w-sm">
          <div class="inline-flex items-center gap-2 mb-6 px-3 py-1.5 rounded-full text-xs font-medium text-blue-300 border border-blue-500/30 bg-blue-500/10 w-fit">
            <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse"></span>
            Automotive Management System
          </div>
          <h1 class="text-4xl font-bold text-white leading-tight mb-4">
            Manage your<br/>
            <span class="text-transparent bg-clip-text" style="background-image: linear-gradient(135deg, #60a5fa, #a78bfa);">
              dealership smarter.
            </span>
          </h1>
          <p class="text-gray-400 text-sm leading-relaxed">
            Full-cycle control over inventory, sales, purchasing, accounting,
            and HR — all in one platform built for motor businesses.
          </p>

          <!-- Feature pills -->
          <div class="flex flex-wrap gap-2 mt-8">
            <span v-for="f in features" :key="f"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs text-gray-300 border border-white/10 bg-white/5">
              <svg class="w-3 h-3 text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              {{ f }}
            </span>
          </div>
        </div>

        <!-- Bottom tagline -->
        <p class="text-gray-600 text-xs">
          &copy; {{ new Date().getFullYear() }} {{ branding.shop_name }}. All rights reserved.
        </p>
      </div>
    </div>

    <!-- ── Right form panel ── -->
    <div class="flex-1 flex flex-col items-center justify-center bg-white px-6 sm:px-12 lg:px-16">

      <!-- Mobile logo (shown on small screens only) -->
      <div class="lg:hidden flex items-center gap-2 mb-8">
        <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
          </svg>
        </div>
        <span class="font-bold text-gray-900 text-lg">{{ branding.shop_name }}</span>
      </div>

      <div class="w-full max-w-sm">
        <!-- Heading -->
        <div class="mb-8">
          <h2 class="text-2xl font-bold text-gray-900">Welcome back</h2>
          <p class="mt-1 text-sm text-gray-500">Sign in to your account to continue</p>
        </div>

        <!-- Form -->
        <form @submit.prevent="submit" class="space-y-5">

          <!-- Username -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Username</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </span>
              <input v-model="form.username" type="text" required autocomplete="username"
                class="field-input pl-10 pr-4"
                placeholder="your username" />
            </div>
          </div>

          <!-- Password -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-sm font-medium text-gray-700">Password</label>
            </div>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </span>
              <input v-model="form.password" :type="showPw ? 'text' : 'password'" required
                class="field-input pl-10 pr-11"
                placeholder="••••••••" />
              <button type="button" @click="showPw = !showPw"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 transition-colors">
                <svg v-if="showPw" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Error -->
          <div v-if="error" class="flex items-start gap-2.5 px-3.5 py-3 rounded-lg bg-red-50 border border-red-200">
            <svg class="w-4 h-4 text-red-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm text-red-600">{{ error }}</p>
          </div>

          <!-- Submit -->
          <button type="submit" :disabled="loading" class="submit-btn">
            <svg v-if="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            {{ loading ? 'Signing in…' : 'Sign in' }}
          </button>
        </form>

        <!-- Bottom note -->
        <p class="mt-8 text-center text-xs text-gray-400">
          Protected system &mdash; authorised personnel only
        </p>
      </div>
    </div>

  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

const auth    = useAuthStore()
const router  = useRouter()
const form    = reactive({ username: '', password: '' })
const error   = ref('')
const loading = ref(false)
const showPw  = ref(false)
const branding = ref({
  shop_name: import.meta.env.VITE_APP_NAME ?? 'Siril Motors',
  logo_url: '',
})

const features = [
  'Parts Inventory', 'Sales & POS', 'Purchase Orders',
  'Accounting', 'HR & Payroll', 'Audit Logs',
]

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/shop-branding')
    if (data?.shop_name) branding.value.shop_name = data.shop_name
    if (data?.logo_url) branding.value.logo_url = data.logo_url
  } catch {
    // Keep fallback branding if API is unavailable.
  }
})

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(form.username, form.password)
    router.push('/')
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Invalid email or password'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.field-input {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  padding-top: 0.6rem;
  padding-bottom: 0.6rem;
  font-size: 0.875rem;
  color: #111827;
  background: #fff;
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.field-input::placeholder { color: #9ca3af; }
.field-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}

.submit-btn {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  background: #1d4ed8;
  color: #fff;
  font-weight: 600;
  font-size: 0.875rem;
  padding: 0.7rem 1.25rem;
  border-radius: 0.5rem;
  border: none;
  cursor: pointer;
  transition: background 0.15s, box-shadow 0.15s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}
.submit-btn:hover:not(:disabled) {
  background: #1e40af;
  box-shadow: 0 4px 12px rgba(29, 78, 216, 0.35);
}
.submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
