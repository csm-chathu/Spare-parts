<template>
  <div class="space-y-5">

    <!-- Header + actions -->
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
      <div class="flex flex-wrap items-center gap-2">
        <div class="relative">
          <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
          <input v-model="search" type="search" placeholder="Invoice, customer, vehicle no…"
            class="form-input pl-8 w-64" @input="debouncedFetch" />
        </div>
        <input v-model="dateFrom" type="date" class="form-input w-36" @change="fetchData" title="From date" />
        <span class="text-gray-400 text-xs">to</span>
        <input v-model="dateTo"   type="date" class="form-input w-36" @change="fetchData" title="To date" />
        <select v-model="statusFilter" class="form-input w-32" @change="fetchData">
          <option value="">All status</option>
          <option value="draft">Draft</option>
          <option value="paid">Paid</option>
          <option value="pending">Pending</option>
          <option value="partial">Partial</option>
          <option value="refunded">Refunded</option>
        </select>
        <select v-model="typeFilter" class="form-input w-32" @change="fetchData">
          <option value="">All types</option>
          <option value="instant">Instant</option>
          <option value="booking">Booking</option>
        </select>
        <button v-if="search || dateFrom || dateTo || statusFilter || typeFilter" @click="clearFilters"
          class="text-xs text-gray-400 hover:text-gray-600 underline">Clear</button>
      </div>
      <router-link to="/sales/new"
        class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-semibold text-sm shadow-sm transition-colors shrink-0">
        <PlusIcon class="w-4 h-4" /> New Sale
      </router-link>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="card flex items-center gap-4">
        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
          <ReceiptPercentIcon class="w-5 h-5 text-blue-600" />
        </div>
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wide">Total Sales</p>
          <p class="text-2xl font-bold text-gray-800">{{ sales.total ?? 0 }}</p>
        </div>
      </div>
      <div class="card flex items-center gap-4">
        <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
          <BanknotesIcon class="w-5 h-5 text-amber-600" />
        </div>
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wide">Revenue</p>
          <p class="text-xl font-bold text-amber-700">LKR {{ totalRevenue }}</p>
        </div>
      </div>
      <div class="card flex items-center gap-4">
        <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center shrink-0">
          <CheckCircleIcon class="w-5 h-5 text-green-600" />
        </div>
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wide">Paid</p>
          <p class="text-2xl font-bold text-green-700">{{ paidCount }}</p>
        </div>
      </div>
      <div class="card flex items-center gap-4">
        <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center shrink-0">
          <ChartBarIcon class="w-5 h-5 text-purple-600" />
        </div>
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wide">Avg Sale</p>
          <p class="text-xl font-bold text-purple-700">LKR {{ avgSale }}</p>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="card p-0 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[700px]">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="table-th w-36">Invoice</th>
              <th class="table-th w-48">Customer</th>
              <th class="table-th w-28">Vehicle No.</th>
              <th class="table-th w-28">Date</th>
              <th class="table-th w-36 text-right">Total</th>
              <th class="table-th w-32">Payment</th>
              <th class="table-th w-24">Delivery</th>
              <th class="table-th w-24">Status</th>
              <th class="table-th w-28 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="loading">
              <td colspan="9" class="table-td text-center py-10 text-gray-400">
                <div class="flex items-center justify-center gap-2">
                  <ArrowPathIcon class="w-4 h-4 animate-spin" /> Loading…
                </div>
              </td>
            </tr>
            <template v-else>
              <tr v-for="s in sales.data" :key="s.id"
                class="hover:bg-amber-50/40 transition-colors cursor-default group">
                <td class="table-td">
                  <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded"
                    :class="s.is_draft ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-700'">
                    {{ s.invoice_number }}
                  </span>
                  <span v-if="s.is_draft" class="ml-1 text-[10px] font-bold text-yellow-700 bg-yellow-100 border border-yellow-300 px-1 py-0.5 rounded uppercase tracking-wide">Draft</span>
                  <div class="mt-1">
                    <span class="badge text-[10px]" :class="s.sale_type === 'booking' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-500'">
                      {{ s.sale_type || 'instant' }}
                    </span>
                  </div>
                </td>
                <td class="table-td">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-amber-100 flex items-center justify-center text-amber-700 font-bold text-xs shrink-0">
                      {{ (s.customer?.name ?? 'W')[0].toUpperCase() }}
                    </div>
                    <div>
                      <p class="text-sm font-medium text-gray-800">{{ s.customer?.name ?? 'Walk-in' }}</p>
                      <p v-if="s.customer?.phone" class="text-xs text-gray-400">{{ s.customer.phone }}</p>
                    </div>
                  </div>
                </td>
                <td class="table-td">
                  <span v-if="s.customer?.vehicle_number"
                    class="font-mono text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded">
                    {{ s.customer.vehicle_number }}
                  </span>
                  <span v-else class="text-gray-300 text-xs">—</span>
                </td>
                <td class="table-td text-xs text-gray-500">
                  <div>{{ fmtDate(s.sold_at) }}</div>
                  <div class="text-gray-400">{{ formatTime(s.sold_at) }}</div>
                </td>
                <td class="table-td text-right">
                  <span class="font-bold text-amber-700">LKR {{ Number(s.total).toLocaleString() }}</span>
                  <div v-if="s.tax > 0" class="text-[10px] text-gray-400 mt-0.5">Tax: LKR {{ Number(s.tax).toLocaleString() }}</div>
                </td>
                <td class="table-td">
                  <span :class="methodClass(s.payment_method)"
                    class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full capitalize">
                    {{ s.payment_method?.replace('_', ' ') }}
                  </span>
                </td>
                <td class="table-td text-xs">
                  <span class="badge" :class="deliveryClass(s.delivery_status)">{{ s.delivery_status || 'delivered' }}</span>
                </td>
                <td class="table-td">
                  <span :class="statusClass(s.payment_status)" class="badge capitalize">{{ s.payment_status }}</span>
                </td>
                <td class="table-td text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <router-link :to="s.is_draft ? `/sales/${s.id}/edit` : `/sales/${s.id}`"
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium hover:bg-opacity-80 transition-colors"
                      :class="s.is_draft ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-blue-100 text-blue-700 hover:bg-blue-200'">
                      <PencilSquareIcon v-if="s.is_draft" class="w-3.5 h-3.5" />
                      <PrinterIcon v-else class="w-3.5 h-3.5" />
                      {{ s.is_draft ? 'Edit Draft' : 'Receipt' }}
                    </router-link>
                    <button v-if="s.is_draft" @click="finalizeDraft(s)"
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-emerald-100 text-emerald-700 hover:bg-emerald-200">
                      <CheckCircleIcon class="w-3.5 h-3.5" /> Finalize
                    </button>
                    <button v-if="!s.is_draft && ['partial','pending'].includes(s.payment_status)"
                      @click="openSettle(s)"
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-emerald-100 text-emerald-700 hover:bg-emerald-200">
                      <BanknotesIcon class="w-3.5 h-3.5" /> Settle
                    </button>
                    <button @click="del(s)"
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-red-100 text-red-700 hover:bg-red-200">
                      <TrashIcon class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!sales.data?.length">
                <td colspan="9" class="table-td text-center py-12">
                  <div class="flex flex-col items-center gap-2 text-gray-400">
                    <ReceiptPercentIcon class="w-10 h-10 opacity-30" />
                    <span>No sales found</span>
                    <router-link to="/sales/new" class="text-amber-600 hover:underline text-sm font-medium">Create your first sale →</router-link>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="px-5 py-3 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-sm text-gray-500 bg-gray-50/50">
        <span class="text-xs">
          Showing <strong class="text-gray-700">{{ sales.from ?? 0 }}–{{ sales.to ?? 0 }}</strong>
          of <strong class="text-gray-700">{{ sales.total ?? 0 }}</strong> records
        </span>
        <div class="flex items-center gap-1">
          <button @click="page--; fetchData()" :disabled="page<=1"
            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md text-xs font-medium border border-gray-200 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
            <ChevronLeftIcon class="w-3.5 h-3.5" /> Prev
          </button>
          <span class="px-3 py-1.5 text-xs font-semibold text-gray-700">Page {{ page }} / {{ sales.last_page ?? 1 }}</span>
          <button @click="page++; fetchData()" :disabled="page>=(sales.last_page ?? 1)"
            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md text-xs font-medium border border-gray-200 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
            Next <ChevronRightIcon class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Settle Payment Modal -->
    <div v-if="settleModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-100">
          <p class="font-bold text-gray-800">Settle Payment</p>
          <p class="text-xs text-gray-400 mt-0.5">{{ settleSale?.invoice_number }} · {{ settleSale?.customer?.name ?? 'Walk-in' }}</p>
        </div>

        <div class="px-6 py-4 space-y-4">
          <!-- Balance summary -->
          <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 grid grid-cols-3 gap-3 text-center text-xs">
            <div>
              <p class="text-gray-400 mb-0.5">Total</p>
              <p class="font-bold text-gray-800">{{ lkr(settleSale?.total) }}</p>
            </div>
            <div>
              <p class="text-gray-400 mb-0.5">Already Paid</p>
              <p class="font-bold text-green-600">{{ lkr(settleSale?.amount_paid) }}</p>
            </div>
            <div>
              <p class="text-gray-400 mb-0.5">Balance Due</p>
              <p class="font-bold text-red-600">{{ settleRemaining }}</p>
            </div>
          </div>

          <!-- Method -->
          <div>
            <label class="text-xs font-semibold text-gray-500 uppercase mb-1 block">Payment Method</label>
            <select v-model="settleForm.payment_method" class="form-input">
              <option value="cash">Cash</option>
              <option value="card">Card</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="cheque">Cheque</option>
              <option value="other">Other</option>
            </select>
          </div>

          <!-- Amount received -->
          <div>
            <label class="text-xs font-semibold text-gray-500 uppercase mb-1 block">Amount Received (LKR)</label>
            <input v-model.number="settleForm.amount_received" type="number" min="0" step="0.01"
              @focus="$event.target.select()" class="form-input text-lg font-bold" />
          </div>

          <!-- Change due -->
          <div v-if="settleChange > 0" class="bg-green-50 border border-green-200 rounded-xl px-4 py-2 flex items-center justify-between text-sm">
            <span class="text-green-700 font-medium">Change to return</span>
            <span class="font-black text-green-700">LKR {{ lkr(settleChange) }}</span>
          </div>
          <div v-else-if="settleShort > 0" class="bg-yellow-50 border border-yellow-200 rounded-xl px-4 py-2 flex items-center justify-between text-sm">
            <span class="text-yellow-700 font-medium">Still outstanding</span>
            <span class="font-black text-yellow-700">LKR {{ lkr(settleShort) }}</span>
          </div>

          <!-- Notes -->
          <div>
            <label class="text-xs font-semibold text-gray-500 uppercase mb-1 block">Notes (optional)</label>
            <input v-model="settleForm.notes" type="text" class="form-input text-sm" placeholder="e.g. Cash received at counter" />
          </div>

          <p v-if="settleError" class="text-sm text-red-600 bg-red-50 border border-red-200 px-3 py-2 rounded-lg">{{ settleError }}</p>
        </div>

        <div class="px-6 py-4 border-t border-gray-100 flex gap-2 justify-end">
          <button @click="settleModal = false" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold">Cancel</button>
          <button @click="submitSettle" :disabled="settling || !settleForm.amount_received"
            class="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white text-sm font-bold transition-colors">
            {{ settling ? 'Saving…' : 'Confirm Payment' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'
import {
  PlusIcon, TrashIcon, EyeIcon, MagnifyingGlassIcon,
  ReceiptPercentIcon, BanknotesIcon, CheckCircleIcon, PrinterIcon,
  ChartBarIcon, ArrowPathIcon, ChevronLeftIcon, ChevronRightIcon,
  DocumentTextIcon, PencilSquareIcon,
} from '@heroicons/vue/24/outline'
import { fmtDate } from '../utils/date.js'

const router       = useRouter()
const route        = useRoute()
const sales        = ref({ data: [] })
const search       = ref('')
const page         = ref(1)
const dateFrom     = ref(new Date(Date.now() - 2 * 86400000).toISOString().slice(0, 10))
const dateTo       = ref('')
const statusFilter = ref('')
const typeFilter   = ref('')
const loading      = ref(false)
const settleModal  = ref(false)
const settleSale   = ref(null)
const settleForm   = ref({ payment_method: 'cash', amount_received: 0, notes: '' })
const settleError  = ref('')
const settling     = ref(false)

let timer = null
function debouncedFetch() { clearTimeout(timer); timer = setTimeout(() => { page.value = 1; fetchData() }, 400) }

async function fetchData() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/sales', {
      params: {
        page:        page.value,
        search:      search.value,
        date_from:   dateFrom.value,
        date_to:     dateTo.value,
        status:      statusFilter.value,
        sale_type:   typeFilter.value,
      },
    })
    sales.value = data
  } finally { loading.value = false }
}

function clearFilters() {
  search.value = ''; dateFrom.value = ''; dateTo.value = ''
  statusFilter.value = ''; typeFilter.value = ''
  page.value = 1; fetchData()
}

const totalRevenue = computed(() => {
  const sum = (sales.value.data ?? []).reduce((acc, s) => acc + Number(s.total), 0)
  return Number(sum).toLocaleString()
})
const paidCount = computed(() => (sales.value.data ?? []).filter(s => s.payment_status === 'paid').length)
const avgSale = computed(() => {
  const d = sales.value.data ?? []
  if (!d.length) return '0'
  return Number(d.reduce((a, s) => a + Number(s.total), 0) / d.length).toLocaleString('en-LK', { maximumFractionDigits: 0 })
})

function formatTime(d) {
  return new Date(d).toLocaleTimeString('en-LK', { hour: '2-digit', minute: '2-digit' })
}

function statusClass(s) {
  return {
    paid:     'bg-green-100 text-green-700',
    pending:  'bg-yellow-100 text-yellow-700',
    partial:  'bg-blue-100 text-blue-700',
    refunded: 'bg-red-100 text-red-700',
  }[s] ?? 'bg-gray-100 text-gray-700'
}
function methodClass(m) {
  return {
    cash:          'bg-green-50 text-green-600',
    card:          'bg-blue-50 text-blue-600',
    bank_transfer: 'bg-purple-50 text-purple-600',
    cheque:        'bg-orange-50 text-orange-600',
  }[m] ?? 'bg-gray-50 text-gray-600'
}

function deliveryClass(s) {
  return {
    delivered: 'bg-green-100 text-green-700',
    booked: 'bg-yellow-100 text-yellow-700',
    cancelled: 'bg-red-100 text-red-700',
  }[s] ?? 'bg-gray-100 text-gray-700'
}

function lkr(v) {
  return Number(v || 0).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const settleBalanceDue = computed(() =>
  Math.max(0, Number(settleSale.value?.total ?? 0) - Number(settleSale.value?.amount_paid ?? 0))
)
const settleRemaining = computed(() => lkr(settleBalanceDue.value))
const settleChange    = computed(() => Math.max(0, (settleForm.value.amount_received || 0) - settleBalanceDue.value))
const settleShort     = computed(() => Math.max(0, settleBalanceDue.value - (settleForm.value.amount_received || 0)))

function openSettle(sale) {
  settleSale.value = sale
  settleForm.value = {
    payment_method:  'cash',
    amount_received: Math.max(0, Number(sale.total) - Number(sale.amount_paid)),
    notes: '',
  }
  settleError.value = ''
  settleModal.value = true
}

async function submitSettle() {
  if (!settleSale.value) return
  settling.value = true
  settleError.value = ''
  try {
    const isBooking = settleSale.value.sale_type === 'booking' && settleSale.value.delivery_status === 'booked'
    const endpoint  = isBooking
      ? `/api/sales/${settleSale.value.id}/settle-booking`
      : `/api/sales/${settleSale.value.id}/settle`
    const payload = isBooking
      ? { payment_method: settleForm.value.payment_method, payment_amount: settleForm.value.amount_received, notes: settleForm.value.notes }
      : settleForm.value
    await axios.post(endpoint, payload)
    settleModal.value = false
    fetchData()
  } catch (e) {
    settleError.value = e.response?.data?.message
      ?? Object.values(e.response?.data?.errors ?? {}).flat().join(', ')
      ?? 'Failed to record payment'
  } finally {
    settling.value = false
  }
}

async function finalizeDraft(s) {
  if (!confirm(`Finalize draft ${s.invoice_number}?\nThis will deduct stock and post to the general ledger.`)) return
  try {
    await axios.post(`/api/sales/${s.id}/finalize`)
    fetchData()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to finalize draft.')
  }
}

async function del(s) {
  const msg = s.is_draft
    ? `Delete draft ${s.invoice_number}? This action cannot be undone.`
    : `Delete invoice ${s.invoice_number}?\nThis will restore stock. This action cannot be undone.`
  if (!confirm(msg)) return
  await axios.delete(`/api/sales/${s.id}`)
  fetchData()
}

onMounted(() => {
  if (route.query.status) statusFilter.value = route.query.status
  fetchData()
})
</script>
