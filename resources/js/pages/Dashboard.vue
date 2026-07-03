<template>
  <div class="space-y-6">
    <!-- Stat cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <template v-if="!loaded">
        <div v-for="n in 4" :key="n" class="card animate-pulse">
          <div class="h-3 bg-gray-200 rounded w-1/2 mb-3"></div>
          <div class="h-7 bg-gray-200 rounded w-2/3"></div>
        </div>
      </template>
      <StatCard v-else v-for="s in stats" :key="s.label" v-bind="s" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Revenue chart -->
      <div class="lg:col-span-2 card">
        <h3 class="font-semibold text-gray-700 mb-4">Revenue — Last 30 Days</h3>
        <div v-if="!loaded" class="h-64 animate-pulse space-y-2 pt-4">
          <div class="flex items-end gap-1 h-48">
            <div v-for="n in 14" :key="n" class="flex-1 bg-gray-200 rounded-t" :style="`height:${30 + (n * 13) % 70}%`"></div>
          </div>
          <div class="h-3 bg-gray-200 rounded w-full"></div>
        </div>
        <Line v-else-if="chartData" :data="chartData" :options="chartOptions" class="max-h-64" />
        <div v-else class="h-64 flex flex-col items-center justify-center text-gray-400 gap-2">
          <svg class="w-10 h-10 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 17l6-6 4 4 8-8" /></svg>
          <span class="text-sm">No sales in the last 30 days</span>
        </div>
      </div>

      <!-- Top products -->
      <div class="card">
        <h3 class="font-semibold text-gray-700 mb-4">Top Products (This Month)</h3>
        <ul class="space-y-3">
          <template v-if="!loaded">
            <li v-for="n in 5" :key="n" class="flex items-center gap-3 animate-pulse">
              <div class="w-6 h-6 rounded-full bg-gray-200 shrink-0"></div>
              <div class="flex-1 space-y-1">
                <div class="h-3 bg-gray-200 rounded w-3/4"></div>
                <div class="h-2.5 bg-gray-100 rounded w-1/3"></div>
              </div>
            </li>
          </template>
          <template v-else>
            <li v-for="(p, i) in data.top_products" :key="p.id" class="flex items-center gap-3">
              <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold">{{ i+1 }}</span>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">{{ p.name }}</p>
                <p class="text-xs text-gray-400">{{ p.total_sold }} sold</p>
              </div>
            </li>
            <li v-if="!data.top_products?.length" class="text-sm text-gray-400">No sales this month</li>
          </template>
        </ul>
      </div>
    </div>

    <!-- Cheque Reminders -->
    <div v-if="chequeReminders.length" class="card border-l-4 border-l-amber-400 p-0 overflow-hidden">
      <div class="flex items-center justify-between px-5 py-3 bg-amber-50 border-b border-amber-100">
        <div class="flex items-center gap-2">
          <span class="text-amber-500">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
          </span>
          <h3 class="font-semibold text-amber-800">Cheque Reminders</h3>
          <span class="ml-1 text-xs font-bold bg-amber-200 text-amber-800 rounded-full px-2 py-0.5">{{ chequeReminders.length }}</span>
        </div>
        <router-link to="/purchases" class="text-xs text-amber-600 hover:underline font-medium">View all →</router-link>
      </div>
      <div class="divide-y divide-gray-100">
        <div v-for="c in chequeReminders" :key="c.id"
          class="flex items-center justify-between px-5 py-3 hover:bg-gray-50"
          :class="chequeBg(c)">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
              :class="chequeIconBg(c)">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0121 9.414V19a2 2 0 01-2 2z"/>
              </svg>
            </div>
            <div>
              <p class="text-sm font-semibold text-gray-800">{{ c.purchase_number }}
                <span class="font-normal text-gray-500 ml-1">· {{ c.supplier?.name }}</span>
              </p>
              <p class="text-xs text-gray-500">Cheque #{{ c.cheque_number }} · {{ c.cheque_bank_name }}</p>
            </div>
          </div>
          <div class="text-right shrink-0 ml-4">
            <p class="text-sm font-bold text-gray-800">LKR {{ Number(c.total).toLocaleString() }}</p>
            <p class="text-xs font-semibold mt-0.5" :class="chequeDueColor(c)">{{ chequeDueLabel(c) }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Low stock -->
      <div class="card">
        <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-red-500"></span>
          Low Stock Alerts
        </h3>
        <div v-if="!loaded" class="animate-pulse space-y-2">
          <div class="h-3 bg-gray-200 rounded w-full"></div>
          <div v-for="n in 5" :key="n" class="flex gap-3 py-2 border-b border-gray-100">
            <div class="h-3 bg-gray-200 rounded w-1/5"></div>
            <div class="h-3 bg-gray-200 rounded flex-1"></div>
            <div class="h-3 bg-gray-200 rounded w-10"></div>
            <div class="h-3 bg-gray-200 rounded w-8"></div>
          </div>
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead><tr>
              <th class="table-th">SKU</th>
              <th class="table-th">Name</th>
              <th class="table-th">Stock</th>
              <th class="table-th">Min</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="p in data.low_stock" :key="p.id">
                <td class="table-td font-mono text-xs">{{ p.sku }}</td>
                <td class="table-td">{{ p.name }}</td>
                <td class="table-td"><span class="badge bg-red-100 text-red-700">{{ p.stock_quantity }}</span></td>
                <td class="table-td text-gray-400">{{ p.min_stock_level }}</td>
              </tr>
              <tr v-if="!data.low_stock?.length">
                <td colspan="4" class="table-td text-center text-gray-400">All items are well-stocked ✓</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent sales -->
      <div class="card">
        <h3 class="font-semibold text-gray-700 mb-4">Recent Sales</h3>
        <div v-if="!loaded" class="animate-pulse space-y-3">
          <div v-for="n in 5" :key="n" class="flex items-center justify-between py-2 border-b border-gray-100">
            <div class="space-y-1.5">
              <div class="h-3 bg-gray-200 rounded w-28"></div>
              <div class="h-2.5 bg-gray-100 rounded w-20"></div>
            </div>
            <div class="space-y-1.5 text-right">
              <div class="h-3 bg-gray-200 rounded w-24 ml-auto"></div>
              <div class="h-2.5 bg-gray-100 rounded w-14 ml-auto"></div>
            </div>
          </div>
        </div>
        <div v-else class="space-y-3">
          <div v-for="sale in data.recent_sales" :key="sale.id"
            class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
            <div>
              <p class="text-sm font-medium text-gray-800">{{ sale.invoice_number }}</p>
              <p class="text-xs text-gray-400">{{ sale.customer?.name ?? 'Walk-in' }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-semibold text-gray-800">LKR {{ Number(sale.total).toLocaleString() }}</p>
              <span :class="statusClass(sale.payment_status)" class="badge">{{ sale.payment_status }}</span>
            </div>
          </div>
          <p v-if="!data.recent_sales?.length" class="text-sm text-gray-400 text-center">No sales yet</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS, CategoryScale, LinearScale,
  PointElement, LineElement, Title, Tooltip, Legend, Filler,
} from 'chart.js'
import StatCard from '@/components/StatCard.vue'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler)

const data = ref({})
const loaded = ref(false)

const stats = computed(() => [
  { label: 'Total Products',    value: data.value.totals?.products        ?? '—', color: 'blue',   icon: '📦' },
  { label: 'Customers',         value: data.value.totals?.customers       ?? '—', color: 'purple', icon: '👥' },
  { label: "Today's Revenue",   value: 'LKR ' + Number(data.value.totals?.revenue_today   ?? 0).toLocaleString(), color: 'green', icon: '💰' },
  { label: 'Low Stock Items',   value: data.value.totals?.low_stock_count ?? '—', color: 'red',    icon: '⚠️' },
])

const chartData = computed(() => {
  const sales = data.value.sales_chart
  if (!sales?.length) return null
  return {
    labels: sales.map(s => s.date),
    datasets: [{
      label: 'Revenue',
      data: sales.map(s => s.revenue),
      borderColor: '#d97706',
      backgroundColor: 'rgba(217,119,6,0.1)',
      fill: true,
      tension: 0.4,
    }],
  }
})

const chartOptions = {
  responsive: true,
  plugins: { legend: { display: false } },
  scales: { y: { beginAtZero: true } },
}

const chequeReminders = computed(() => data.value.cheque_reminders ?? [])

function chequeDays(c) {
  const today = new Date(); today.setHours(0,0,0,0)
  const due   = new Date(c.cheque_date); due.setHours(0,0,0,0)
  return Math.round((due - today) / 86400000)
}
function chequeDueLabel(c) {
  const d = chequeDays(c)
  if (d < 0)  return `Overdue by ${Math.abs(d)} day${Math.abs(d)>1?'s':''}`
  if (d === 0) return 'Due today!'
  if (d === 1) return 'Due tomorrow'
  return `Due in ${d} days  (${c.cheque_date})`
}
function chequeDueColor(c) {
  const d = chequeDays(c)
  if (d < 0)  return 'text-red-600'
  if (d <= 1) return 'text-red-500'
  if (d <= 3) return 'text-amber-600'
  return 'text-gray-500'
}
function chequeBg(c) {
  const d = chequeDays(c)
  if (d < 0)  return 'bg-red-50'
  if (d <= 1) return 'bg-amber-50'
  return ''
}
function chequeIconBg(c) {
  const d = chequeDays(c)
  if (d < 0)  return 'bg-red-100 text-red-600'
  if (d <= 1) return 'bg-amber-100 text-amber-600'
  return 'bg-gray-100 text-gray-500'
}

function statusClass(s) {
  return {
    paid:     'bg-green-100 text-green-700',
    pending:  'bg-yellow-100 text-yellow-700',
    partial:  'bg-blue-100 text-blue-700',
    refunded: 'bg-red-100 text-red-700',
  }[s] ?? 'bg-gray-100 text-gray-700'
}

onMounted(async () => {
  try {
    const { data: d } = await axios.get('/api/dashboard')
    data.value = d
  } finally {
    loaded.value = true
  }
})
</script>
