<template>
  <div class="flex h-screen bg-gray-100 overflow-hidden">
    <!-- Sidebar -->
    <aside class="w-64 bg-gray-900 text-white flex flex-col shrink-0">
      <!-- Logo -->
      <div class="flex items-center gap-3 px-6 py-5 border-b border-gray-800">
        <img
          v-if="branding.logo_url"
          :src="branding.logo_url"
          alt="Shop logo"
          class="h-9 w-9 object-contain rounded"
        />
        <span v-else class="text-2xl">🔧</span>
        <div>
          <p class="font-bold text-blue-400 text-sm leading-tight truncate">{{ branding.shop_name }}</p>
          <p class="text-xs text-gray-400">Management System</p>
        </div>
      </div>

      <!-- Nav (DB-driven, grouped by feature.group) -->
      <nav class="flex-1 py-4 overflow-y-auto">
        <template v-for="group in navGroups" :key="group.key">
          <template v-if="group.items.length">
            <div class="px-4 mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider"
              :class="group.key !== 'general' ? 'mt-4' : ''">
              {{ group.label }}
            </div>
            <router-link v-for="item in group.items" :key="item.route" :to="item.route"
              class="flex items-center gap-3 px-4 py-2.5 mx-2 rounded-lg text-sm transition-colors"
              :class="isNavActive(item.route)
                ? 'bg-blue-600 text-white hover:bg-blue-700'
                : 'text-gray-300 hover:bg-gray-800 hover:text-white'">
              <component :is="iconMap[item.icon]" class="w-5 h-5 shrink-0" />
              {{ item.label }}
            </router-link>
          </template>
        </template>
      </nav>

      <!-- User info -->
      <div class="px-4 py-4 border-t border-gray-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-sm font-bold">
            {{ auth.user?.name?.charAt(0) }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-white truncate">{{ auth.user?.name }}</p>
            <p class="text-xs text-gray-400 truncate">{{ auth.user?.email }}</p>
          </div>
          <button @click="doLogout" title="Logout"
            class="p-1 rounded text-gray-400 hover:text-white hover:bg-gray-700 transition-colors">
            <ArrowRightOnRectangleIcon class="w-5 h-5" />
          </button>
        </div>
      </div>
    </aside>

    <!-- Main area -->
    <div class="flex-1 flex flex-col min-h-0 min-w-0">
      <!-- Top bar -->
      <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex items-center gap-3 text-sm text-gray-500">
          <router-link to="/getting-started"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100 font-medium text-xs transition-colors">
            <QuestionMarkCircleIcon class="w-4 h-4" />
            Getting Started
          </router-link>
          <span>{{ currentDate }}</span>
        </div>
      </header>

      <!-- Page -->
      <main class="flex-1 overflow-auto p-6">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import {
  HomeIcon, CubeIcon, UsersIcon, CircleStackIcon,
  TruckIcon, ShoppingCartIcon, ArchiveBoxIcon,
  ArrowRightOnRectangleIcon, SparklesIcon,
  UserGroupIcon, ChartBarIcon, ClipboardDocumentCheckIcon,
  ClipboardDocumentListIcon, CurrencyDollarIcon, FireIcon,
  ScaleIcon, BookOpenIcon, DocumentTextIcon, PresentationChartBarIcon,
  BanknotesIcon, BuildingLibraryIcon, HomeModernIcon,
  ReceiptPercentIcon, Cog6ToothIcon, DevicePhoneMobileIcon, LockClosedIcon,
  WrenchScrewdriverIcon, SquaresPlusIcon,
  ClipboardDocumentIcon, InboxArrowDownIcon, DocumentCurrencyDollarIcon,
  ArrowUturnLeftIcon, CreditCardIcon, ChartBarSquareIcon, QuestionMarkCircleIcon,
} from '@heroicons/vue/24/outline'

const auth   = useAuthStore()
const router = useRouter()
const route  = useRoute()
const branding = ref({
  shop_name: import.meta.env.VITE_APP_NAME ?? 'Siril Motors',
  logo_url: '',
})

// Icon name → component map (keeps icons tree-shakeable)
const iconMap = {
  HomeIcon, CubeIcon, UsersIcon, CircleStackIcon, TruckIcon, ShoppingCartIcon,
  ClipboardDocumentIcon, InboxArrowDownIcon, DocumentCurrencyDollarIcon,
  CreditCardIcon, ArrowUturnLeftIcon, ChartBarSquareIcon,
  ChartBarIcon, ClipboardDocumentCheckIcon, ClipboardDocumentListIcon,
  UserGroupIcon, Cog6ToothIcon, ReceiptPercentIcon, DevicePhoneMobileIcon,
  BanknotesIcon, BuildingLibraryIcon, CurrencyDollarIcon, HomeModernIcon,
  ScaleIcon, BookOpenIcon, DocumentTextIcon, PresentationChartBarIcon,
  WrenchScrewdriverIcon,
}

const GROUP_LABELS = {
  general:    'Main',
  purchasing: 'Purchasing',
  admin:      'Admin',
  hr:         'Human Resources',
  finance:    'Finance',
  accounting: 'Accounting',
}

const GROUP_ORDER = ['general', 'purchasing', 'admin', 'hr', 'finance', 'accounting']

const navGroups = computed(() => {
  const features = auth.features ?? []
  return GROUP_ORDER.map(key => ({
    key,
    label: GROUP_LABELS[key],
    items: features.filter(f => f.group === key),
  }))
})

const pageTitles = {
  dashboard:              'Dashboard',
  products:               'Parts Inventory',
  'master-data':          'Master Data',
  customers:              'Customers',
  suppliers:              'Suppliers',
  sales:                  'Sales',
  'sales.new':            'New Sale',
  'sales.edit':           'Edit Draft',
  purchases:              'Purchases',
  'purchases.new':        'New Purchase',
  'purchase-orders':      'Purchase Orders',
  grn:                    'Goods Received Notes',
  'goods-invoices':       'Goods Invoices',
  'supplier-payments':    'Supplier Payments',
  'purchase-returns':     'Purchase Returns',
  'stock-ledger':         'Stock Ledger',
  users:                  'User Management',
  'shop-settings':        'Shop Settings',
  'day-end':              'Day-End Reconciliation',
  'audit-log':            'Audit Log',
  reports:                'Reports',
  expenses:               'Expense Management',
  sms:                    'SMS Centre',
  accounts:               'Chart of Accounts',
  'journal-entries':      'Journal Entries',
  'general-ledger':       'General Ledger',
  employees:              'Employees',
  'salary-payments':      'Salary Payments',
  loans:                  'Business Loans',
  rentals:                'Monthly Rentals',
  'customer-investments': 'Owner Investments',
  'getting-started':      'Getting Started',
  'job-cards':            'Job Cards',
  'job-cards.detail':     'Job Card Detail',
}

const pageTitle  = computed(() => pageTitles[route.name] ?? 'Siril Motors')
const currentDate = computed(() => new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }))

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/shop-branding')
    if (data?.shop_name) branding.value.shop_name = data.shop_name
    if (data?.logo_url) {
      branding.value.logo_url = data.logo_url
      const favicon = document.getElementById('app-favicon')
      if (favicon) favicon.href = data.logo_url
    }
  } catch {
    // Keep fallback branding if API is unavailable.
  }

  // Refresh features on page reload (already cached in localStorage, but sync with server)
  if (auth.token) {
    auth.fetchFeatures().catch(() => {})
  }
})

async function doLogout() {
  await auth.logout()
  router.push('/login')
}

function isNavActive(targetPath) {
  if (targetPath === '/') {
    return route.path === '/'
  }
  return route.path === targetPath || route.path.startsWith(`${targetPath}/`)
}
</script>
