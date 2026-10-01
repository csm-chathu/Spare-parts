<template>
  <div class="space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Job Cards</h2>
        <p class="text-sm text-gray-500 mt-0.5">Vehicle service management</p>
      </div>
      <button @click="openCreate" class="btn-primary flex items-center gap-2">
        <PlusIcon class="w-4 h-4" /> New Job Card
      </button>
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-5 gap-3">
      <div v-for="s in statuses" :key="s.key"
        class="bg-white rounded-xl border p-4 cursor-pointer transition-all"
        :class="filterStatus === s.key ? 'border-blue-400 ring-2 ring-blue-100' : 'border-gray-200 hover:border-gray-300'"
        @click="filterStatus = filterStatus === s.key ? '' : s.key; load()">
        <p class="text-2xl font-black" :class="s.color">{{ counts[s.key] ?? 0 }}</p>
        <p class="text-xs text-gray-500 mt-1 font-medium">{{ s.label }}</p>
      </div>
    </div>

    <!-- Search -->
    <div class="flex gap-3">
      <div class="relative flex-1">
        <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
        <input v-model="search" placeholder="Search card number, customer, vehicle…" class="form-input pl-9" @input="load" />
      </div>
      <button v-if="filterStatus || search" @click="filterStatus = ''; search = ''; load()"
        class="px-4 py-2 text-sm text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
        Clear filters
      </button>
    </div>

    <!-- Tile grid -->
    <div v-if="cards.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div v-for="card in cards" :key="card.id"
        class="bg-white rounded-2xl border border-gray-200 hover:border-blue-300 hover:shadow-md transition-all cursor-pointer group overflow-hidden"
        @click="$router.push(`/job-cards/${card.id}`)">

        <!-- Status stripe -->
        <div class="h-1.5 w-full" :class="statusStripe(card.status)"></div>

        <div class="p-5">
          <!-- Top row -->
          <div class="flex items-start justify-between mb-3">
            <div>
              <p class="font-mono font-bold text-blue-700 text-sm">{{ card.card_number }}</p>
              <p class="text-xs text-gray-400 mt-0.5">{{ fmtDate(card.created_at) }}</p>
            </div>
            <span :class="statusClass(card.status)" class="badge capitalize text-xs px-2.5 py-1">
              {{ statusLabel(card.status) }}
            </span>
          </div>

          <!-- Customer -->
          <div class="flex items-center gap-2.5 mb-3">
            <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm shrink-0">
              {{ (card.customer_name || card.customer?.name || '?')[0].toUpperCase() }}
            </div>
            <div class="min-w-0">
              <p class="font-semibold text-gray-900 truncate">{{ card.customer_name || card.customer?.name || 'Walk-in' }}</p>
              <p class="text-xs text-gray-400 truncate">{{ card.customer_phone || card.customer?.phone || 'No phone' }}</p>
            </div>
          </div>

          <!-- Vehicle -->
          <div class="flex items-center gap-2 mb-3 bg-gray-50 rounded-xl px-3 py-2">
            <TruckIcon class="w-4 h-4 text-gray-400 shrink-0" />
            <div class="min-w-0 flex-1">
              <p class="font-mono font-bold text-gray-800 text-sm">{{ card.vehicle_number || '—' }}</p>
              <p class="text-xs text-gray-400 truncate">{{ [card.vehicle_make, card.vehicle_model].filter(Boolean).join(' ') || 'Unknown vehicle' }}</p>
            </div>
          </div>

          <!-- Complaint preview -->
          <p v-if="card.complaint" class="text-xs text-gray-500 line-clamp-2 mb-3 italic">
            "{{ card.complaint }}"
          </p>

          <!-- Footer -->
          <div class="flex items-center justify-between pt-3 border-t border-gray-100">
            <div class="flex items-center gap-1.5 text-xs text-gray-400">
              <WrenchScrewdriverIcon class="w-3.5 h-3.5" />
              {{ card.assigned_technician || 'Unassigned' }}
            </div>
            <div class="flex items-center gap-2">
              <p class="font-bold text-gray-900 text-sm">LKR {{ lkr(card.total) }}</p>
              <button v-if="card.public_token"
                @click.stop="receiptToken = card.public_token"
                class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold transition-colors shadow-sm">
                <PrinterIcon class="w-3.5 h-3.5" /> Print
              </button>
            </div>
          </div>
        </div>

        <!-- Hover arrow -->
        <div class="px-5 pb-3 flex items-center justify-end gap-1 text-xs text-blue-600 opacity-0 group-hover:opacity-100 transition-opacity -mt-1">
          Open details <ArrowRightIcon class="w-3.5 h-3.5" />
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="bg-white rounded-2xl border border-gray-200 py-20 flex flex-col items-center text-gray-400">
      <div class="w-20 h-20 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
        <WrenchScrewdriverIcon class="w-10 h-10 text-gray-300" />
      </div>
      <p class="font-semibold text-gray-500 mb-1">No job cards found</p>
      <p class="text-sm mb-5">{{ filterStatus || search ? 'Try adjusting your filters' : 'Create your first job card to get started' }}</p>
      <button @click="openCreate" class="btn-primary flex items-center gap-2">
        <PlusIcon class="w-4 h-4" /> New Job Card
      </button>
    </div>

    <!-- ── Create Modal ── -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[92vh] flex flex-col">

        <!-- Modal header -->
        <div class="flex items-center justify-between px-7 py-5 border-b shrink-0">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center shrink-0">
              <WrenchScrewdriverIcon class="w-5 h-5 text-white" />
            </div>
            <div>
              <h3 class="text-lg font-bold text-gray-900">New Job Card</h3>
              <p class="text-xs text-gray-400 mt-0.5 flex items-center gap-1">
                <ChatBubbleLeftIcon class="w-3.5 h-3.5" />
                SMS will be sent to customer on creation
              </p>
            </div>
          </div>
          <button @click="showModal = false" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 transition-colors">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- 2-column body -->
        <div class="flex flex-1 min-h-0">

          <!-- LEFT: Customer + Vehicle -->
          <div class="flex-1 overflow-y-auto px-7 py-5 border-r border-gray-100">

            <!-- Customer -->
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
              <UserIcon class="w-3.5 h-3.5" /> Customer
            </p>
            <div class="space-y-4 mb-6">
              <div>
                <label class="form-label">Full Name <span class="text-red-500">*</span></label>
                <input v-model="form.customer_name" required class="form-input" placeholder="e.g. Kamal Perera" />
              </div>
              <div>
                <label class="form-label">Phone Number</label>
                <input v-model="form.customer_phone" type="tel" class="form-input" placeholder="+94 77 123 4567" />
              </div>
            </div>

            <!-- Vehicle -->
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
              <TruckIcon class="w-3.5 h-3.5" /> Vehicle
            </p>
            <div class="space-y-4">
              <div>
                <label class="form-label">Vehicle Number</label>
                <input v-model="form.vehicle_number" class="form-input font-mono uppercase" placeholder="CAB-1234" />
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="form-label">Make</label>
                  <input v-model="form.vehicle_make" class="form-input" placeholder="Toyota" />
                </div>
                <div>
                  <label class="form-label">Model</label>
                  <input v-model="form.vehicle_model" class="form-input" placeholder="Corolla" />
                </div>
              </div>
              <div>
                <label class="form-label">Mileage (km)</label>
                <input v-model.number="form.mileage" type="number" min="0" class="form-input" placeholder="45000" />
              </div>
            </div>
          </div>

          <!-- RIGHT: Service details -->
          <div class="flex-1 overflow-y-auto px-7 py-5 flex flex-col">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
              <WrenchScrewdriverIcon class="w-3.5 h-3.5" /> Service
            </p>
            <div class="space-y-4 flex-1">
              <div>
                <label class="form-label">Assigned Technician</label>
                <select v-model="form.assigned_technician" class="form-input">
                  <option value="">— Unassigned —</option>
                  <option v-for="emp in employees" :key="emp.id" :value="emp.name">
                    {{ emp.name }}<template v-if="emp.designation"> — {{ emp.designation }}</template>
                  </option>
                </select>
              </div>
              <div>
                <label class="form-label">Estimated Completion</label>
                <input v-model="form.estimated_completion" type="date" class="form-input" />
              </div>
              <div>
                <label class="form-label">Customer Complaint / Issue</label>
                <textarea v-model="form.complaint" rows="5" class="form-input resize-none"
                  placeholder="Describe the issue reported by the customer…"></textarea>
              </div>
              <div>
                <label class="form-label">Internal Notes</label>
                <textarea v-model="form.notes" rows="3" class="form-input resize-none"
                  placeholder="Internal workshop notes…"></textarea>
              </div>
            </div>

            <p v-if="formError" class="mt-3 text-sm text-red-600 bg-red-50 border border-red-200 px-3 py-2 rounded-lg">
              {{ formError }}
            </p>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-7 py-4 border-t shrink-0 flex gap-3 bg-gray-50 rounded-b-2xl">
          <button type="button" @click="showModal = false" class="btn-secondary px-6">Cancel</button>
          <button @click="createCard" :disabled="saving"
            class="btn-primary flex-1 flex items-center justify-center gap-2">
            <ArrowPathIcon v-if="saving" class="w-4 h-4 animate-spin" />
            <PlusIcon v-else class="w-4 h-4" />
            {{ saving ? 'Creating…' : 'Create Job Card' }}
          </button>
        </div>
      </div>
    </div>

  <!-- Receipt Modal -->
  <JobCardReceiptModal v-if="receiptToken" :token="receiptToken" @close="receiptToken = ''" />

  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import {
  PlusIcon, WrenchScrewdriverIcon, MagnifyingGlassIcon,
  XMarkIcon, ArrowPathIcon, UserIcon, TruckIcon,
  ArrowRightIcon, ChatBubbleLeftIcon, PrinterIcon,
} from '@heroicons/vue/24/outline'
import JobCardReceiptModal from '@/components/JobCardReceiptModal.vue'

const router       = useRouter()
const cards        = ref([])
const search       = ref('')
const filterStatus = ref('')
const showModal    = ref(false)
const saving       = ref(false)
const formError    = ref('')
const counts       = ref({})
const receiptToken = ref('')
const employees    = ref([])

const statuses = [
  { key: 'received',    label: 'Received',    color: 'text-blue-600' },
  { key: 'in_progress', label: 'In Progress', color: 'text-amber-600' },
  { key: 'completed',   label: 'Completed',   color: 'text-green-600' },
  { key: 'delivered',   label: 'Delivered',   color: 'text-purple-600' },
  { key: 'cancelled',   label: 'Cancelled',   color: 'text-red-500' },
]

const form = reactive({
  customer_name: '', customer_phone: '', vehicle_number: '',
  vehicle_make: '', vehicle_model: '', mileage: '',
  assigned_technician: '', estimated_completion: '',
  complaint: '', notes: '',
})

async function load() {
  const { data } = await axios.get('/api/job-cards', {
    params: { search: search.value, status: filterStatus.value, per_page: 100 },
  })
  cards.value  = data.data
  // Count per status
  const all = data.data
  counts.value = {}
  statuses.forEach(s => {
    counts.value[s.key] = all.filter(c => c.status === s.key).length
  })
}

function openCreate() {
  Object.assign(form, {
    customer_name: '', customer_phone: '', vehicle_number: '',
    vehicle_make: '', vehicle_model: '', mileage: '',
    assigned_technician: '', estimated_completion: '',
    complaint: '', notes: '',
  })
  formError.value = ''
  showModal.value = true
}

async function createCard() {
  if (!form.customer_name.trim()) { formError.value = 'Customer name is required'; return }
  saving.value = true; formError.value = ''
  try {
    const { data } = await axios.post('/api/job-cards', form)
    showModal.value = false
    router.push(`/job-cards/${data.id}`)
  } catch (e) {
    formError.value = e.response?.data?.message
      ?? Object.values(e.response?.data?.errors ?? {}).flat().join(', ')
  } finally { saving.value = false }
}

function lkr(v) { return Number(v || 0).toLocaleString('en-LK', { minimumFractionDigits: 2 }) }
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—' }

const STATUS_LABELS = {
  received: 'Received', in_progress: 'In Progress',
  completed: 'Completed', delivered: 'Delivered', cancelled: 'Cancelled',
}
function statusLabel(s) { return STATUS_LABELS[s] ?? s }

function statusClass(s) {
  return {
    received:    'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-700',
    completed:   'bg-green-100 text-green-700',
    delivered:   'bg-purple-100 text-purple-700',
    cancelled:   'bg-red-100 text-red-700',
  }[s] ?? 'bg-gray-100 text-gray-600'
}

function statusStripe(s) {
  return {
    received:    'bg-blue-400',
    in_progress: 'bg-amber-400',
    completed:   'bg-green-400',
    delivered:   'bg-purple-400',
    cancelled:   'bg-red-400',
  }[s] ?? 'bg-gray-200'
}

onMounted(async () => {
  await load()
  const { data } = await axios.get('/api/employees/all').catch(() => ({ data: [] }))
  employees.value = data
})
</script>
