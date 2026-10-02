<template>
  <div class="min-h-screen bg-gray-50">

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
        <p class="text-gray-500 text-sm">Loading your service details…</p>
      </div>
    </div>

    <!-- Not found -->
    <div v-else-if="!card" class="flex items-center justify-center min-h-screen p-6">
      <div class="text-center max-w-sm">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-800 mb-2">Job Card Not Found</h2>
        <p class="text-gray-500 text-sm">This link may be invalid or expired. Please contact the service center.</p>
      </div>
    </div>

    <!-- Content -->
    <div v-else>
      <!-- Header bar -->
      <div class="bg-gray-900 text-white">
        <div class="max-w-2xl mx-auto px-4 py-5 flex items-center justify-between">
          <div>
            <p class="font-bold text-blue-400 text-lg">{{ card.branch_name || 'Siril Motors' }}</p>
            <p class="text-xs text-gray-400">Vehicle Service Centre</p>
          </div>
        </div>
      </div>

      <div class="max-w-2xl mx-auto px-4 py-6 space-y-4">

        <!-- Status card -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
          <div class="h-2 w-full" :class="statusStripe(card.status)"></div>
          <div class="p-5">
            <div class="flex items-start justify-between mb-4">
              <div>
                <p class="text-xs text-gray-400 font-medium mb-0.5">Job Card Number</p>
                <p class="font-mono font-black text-blue-700 text-xl">{{ card.card_number }}</p>
              </div>
              <span :class="statusClass(card.status)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-bold">
                <span class="w-2 h-2 rounded-full" :class="statusDot(card.status)"></span>
                {{ statusLabel(card.status) }}
              </span>
            </div>

            <!-- Progress steps -->
            <div class="flex items-center gap-0 mb-5">
              <template v-for="(step, i) in steps" :key="step.key">
                <div class="flex flex-col items-center gap-1 flex-1 min-w-0">
                  <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold border-2 transition-colors"
                    :class="stepDone(step.key)
                      ? 'bg-green-500 border-green-500 text-white'
                      : stepCurrent(step.key)
                        ? 'bg-blue-600 border-blue-600 text-white'
                        : 'bg-white border-gray-200 text-gray-300'">
                    <CheckIcon v-if="stepDone(step.key)" class="w-4 h-4" />
                    <span v-else>{{ i + 1 }}</span>
                  </div>
                  <p class="text-[10px] font-semibold text-center leading-tight"
                    :class="stepDone(step.key) || stepCurrent(step.key) ? 'text-gray-700' : 'text-gray-300'">
                    {{ step.label }}
                  </p>
                </div>
                <div v-if="i < steps.length - 1"
                  class="h-0.5 flex-1 mb-5 transition-colors"
                  :class="stepDone(step.key) ? 'bg-green-400' : 'bg-gray-100'">
                </div>
              </template>
            </div>

            <!-- Completion note -->
            <div v-if="card.status === 'completed' || card.status === 'delivered'"
              class="bg-green-50 border border-green-200 rounded-xl p-3 flex items-center gap-3">
              <CheckCircleIcon class="w-6 h-6 text-green-500 shrink-0" />
              <div>
                <p class="font-bold text-green-800 text-sm">Service Complete</p>
                <p class="text-xs text-green-600">Your vehicle is ready for collection.</p>
              </div>
            </div>
            <div v-else-if="card.estimated_completion"
              class="bg-blue-50 border border-blue-100 rounded-xl p-3 flex items-center gap-2 text-sm text-blue-700">
              <CalendarDaysIcon class="w-4 h-4 shrink-0" />
              Estimated completion: <strong>{{ fmtDate(card.estimated_completion) }}</strong>
            </div>
          </div>
        </div>

        <!-- Vehicle & Customer -->
        <div class="grid grid-cols-2 gap-4">
          <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Your Vehicle</p>
            <p class="font-mono font-black text-gray-900 text-lg">{{ card.vehicle_number || '—' }}</p>
            <p class="text-sm text-gray-500">{{ [card.vehicle_make, card.vehicle_model].filter(Boolean).join(' ') || 'Vehicle' }}</p>
            <p v-if="card.mileage" class="text-xs text-gray-400 mt-1">{{ Number(card.mileage).toLocaleString() }} km</p>
          </div>
          <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Customer</p>
            <p class="font-bold text-gray-900">{{ card.customer_name || '—' }}</p>
            <p v-if="card.assigned_technician" class="text-xs text-gray-500 mt-2">
              Technician: <span class="font-medium text-gray-700">{{ card.assigned_technician }}</span>
            </p>
          </div>
        </div>

        <!-- Complaint -->
        <div v-if="card.complaint" class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Reported Issue</p>
          <p class="text-sm text-gray-700 leading-relaxed italic">"{{ card.complaint }}"</p>
        </div>

        <!-- Items -->
        <div v-if="card.items?.length" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-100">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Service Items ({{ card.items.length }})</p>
          </div>
          <div v-for="item in card.items" :key="item.description + item.type"
            class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 last:border-b-0">
            <span :class="{
              'bg-blue-100 text-blue-700': item.type === 'part',
              'bg-green-100 text-green-700': item.type === 'labour',
              'bg-gray-100 text-gray-600': item.type === 'other',
            }" class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold capitalize shrink-0">{{ item.type }}</span>
            <p class="flex-1 text-sm font-medium text-gray-800">{{ item.description }}</p>
            <div class="text-right shrink-0">
              <p class="font-bold text-gray-900 text-sm">LKR {{ lkr(item.total) }}</p>
              <p class="text-[10px] text-gray-400">{{ item.quantity }} × {{ lkr(item.unit_price) }}</p>
            </div>
          </div>
          <!-- Total -->
          <div class="flex items-center justify-between px-4 py-3 bg-gray-900">
            <span class="text-gray-400 text-sm">Total Amount</span>
            <span class="font-black text-blue-400 text-lg">LKR {{ lkr(card.total) }}</span>
          </div>
        </div>

        <!-- No items yet -->
        <div v-else class="bg-white rounded-2xl border border-gray-200 p-6 text-center text-gray-400 shadow-sm">
          <p class="text-sm">Service items will appear here as work progresses.</p>
        </div>

        <!-- Footer -->
        <p class="text-center text-xs text-gray-400 pb-4">
          Received on {{ fmtDate(card.created_at) }} &nbsp;·&nbsp; {{ card.branch_name || 'Siril Motors' }}
        </p>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { CheckIcon, CheckCircleIcon, CalendarDaysIcon, PrinterIcon } from '@heroicons/vue/24/outline'

const route   = useRoute()
const card    = ref(null)
const loading = ref(true)

const currentUrl = computed(() => window.location.href.split('?')[0].replace(/\/$/, ''))

const steps = [
  { key: 'received',    label: 'Received' },
  { key: 'in_progress', label: 'In Progress' },
  { key: 'completed',   label: 'Completed' },
  { key: 'delivered',   label: 'Delivered' },
]

const ORDER = ['received', 'in_progress', 'completed', 'delivered']

function stepDone(key) {
  const cur = ORDER.indexOf(card.value?.status)
  const idx = ORDER.indexOf(key)
  return idx < cur || (card.value?.status === 'delivered' && key !== 'cancelled')
}
function stepCurrent(key) { return card.value?.status === key }

function statusLabel(s) {
  return { received: 'Received', in_progress: 'In Progress', completed: 'Completed', delivered: 'Delivered', cancelled: 'Cancelled' }[s] ?? s
}
function statusClass(s) {
  return {
    received:    'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-800',
    completed:   'bg-green-100 text-green-700',
    delivered:   'bg-purple-100 text-purple-700',
    cancelled:   'bg-red-100 text-red-700',
  }[s] ?? 'bg-gray-100 text-gray-600'
}
function statusStripe(s) {
  return { received: 'bg-blue-400', in_progress: 'bg-amber-400', completed: 'bg-green-400', delivered: 'bg-purple-400', cancelled: 'bg-red-400' }[s] ?? 'bg-gray-200'
}
function statusDot(s) {
  return { received: 'bg-blue-500', in_progress: 'bg-amber-500', completed: 'bg-green-500', delivered: 'bg-purple-500', cancelled: 'bg-red-500' }[s] ?? 'bg-gray-400'
}

function lkr(v) { return Number(v || 0).toLocaleString('en-LK', { minimumFractionDigits: 2 }) }
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—' }

onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/job-cards/public/${route.params.token}`)
    card.value = data
  } catch {
    card.value = null
  } finally {
    loading.value = false
  }
})
</script>
