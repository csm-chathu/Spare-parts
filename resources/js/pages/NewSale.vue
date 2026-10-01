<template>
  <!-- Full-height POS layout, -6 to offset the parent p-6 padding -->
  <div class="-m-6 flex flex-col" style="height: calc(100vh - 57px);">

    <!-- ── Top bar ── -->
    <div class="flex items-center justify-between px-6 py-3 bg-white border-b border-gray-200 shrink-0">
      <div class="flex items-center gap-3">
        <router-link to="/sales"
          class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 transition-colors">
          <ArrowLeftIcon class="w-4 h-4" /> Back to Sales
        </router-link>
        <span class="text-gray-300">/</span>
        <h2 class="text-base font-semibold text-gray-800">New Sale</h2>
      </div>
      <div class="flex items-center gap-2 text-xs text-gray-400">
        <span v-if="form.items.length" class="bg-blue-100 text-blue-700 font-semibold px-2 py-0.5 rounded-full">
          {{ form.items.length }} item{{ form.items.length !== 1 ? 's' : '' }}
        </span>
        <span class="font-medium text-gray-600">LKR {{ lkr(total) }}</span>
      </div>
    </div>

    <!-- ── Main 2-column body ── -->
    <div class="flex flex-1 min-h-0">

      <!-- ═══ LEFT: Cart ═══ -->
      <div class="flex-1 flex flex-col min-w-0 border-r border-gray-200 bg-gray-50">

        <!-- Barcode bar -->
        <div class="px-5 py-3 bg-white border-b border-gray-200 shrink-0">
          <div class="flex gap-2">
            <div class="relative flex-1">
              <QrCodeIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
              <input
                v-model="barcodeInput"
                type="text"
                placeholder="Scan barcode or type SKU and press Enter…"
                class="form-input pl-9 text-sm font-mono w-full"
                @keyup.enter="scanBarcode"
                @keyup.tab.prevent="scanBarcode"
              />
            </div>
            <button @click="addItem"
              class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition-colors shrink-0">
              <PlusIcon class="w-4 h-4" /> Add Item
            </button>
          </div>
          <p v-if="barcodeError" class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
            <ExclamationTriangleIcon class="w-3.5 h-3.5 shrink-0" /> {{ barcodeError }}
          </p>
        </div>

        <!-- Cart table -->
        <div class="flex-1 overflow-y-auto">
          <!-- Empty state -->
          <div v-if="!form.items.length"
            class="flex flex-col items-center justify-center h-full text-gray-400">
            <div class="w-20 h-20 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
              <ShoppingCartIcon class="w-10 h-10 text-gray-300" />
            </div>
            <p class="font-medium text-gray-500 mb-1">Cart is empty</p>
            <p class="text-sm">Scan a barcode or click Add Item to start</p>
            <button @click="addItem"
              class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition-colors">
              <PlusIcon class="w-4 h-4" /> Add First Item
            </button>
          </div>

          <!-- Items -->
          <div v-if="form.items.length">
            <!-- Table header -->
            <div class="flex items-center gap-2 px-5 py-2 bg-gray-100 border-b border-gray-200 text-[10px] font-bold text-gray-400 uppercase tracking-widest sticky top-0">
              <span class="w-6 shrink-0"></span>
              <span class="flex-1">Part / Description</span>
              <span class="w-20 shrink-0 text-center">Qty</span>
              <span class="w-28 shrink-0">Unit Price</span>
              <span class="w-24 shrink-0">Discount</span>
              <span class="w-24 shrink-0 text-right">Line Total</span>
              <span class="w-8 shrink-0"></span>
            </div>

            <div v-for="(item, i) in form.items" :key="i"
              class="flex items-start gap-2 px-5 py-3 border-b border-gray-100 bg-white hover:bg-blue-50/30 transition-colors">

              <!-- Row number -->
              <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center shrink-0 mt-1.5">
                {{ i + 1 }}
              </span>

              <!-- Product search -->
              <div class="relative flex-1 min-w-0">
                <input
                  :id="`item-search-${i}`"
                  v-model="item.product_search"
                  type="text"
                  class="form-input w-full font-medium text-sm"
                  placeholder="Type part name, SKU or barcode…"
                  @input="item.product_id = ''; item.product_ref = null; item.product_search?.trim() ? openProductDropdown(item) : (item.product_dropdown_open = false); item.product_dropdown_index = -1"
                  @focus="!item.product_id && item.product_search?.trim() ? openProductDropdown(item) : null"
                  @keydown.down.prevent="moveDropdown(item, 1)"
                  @keydown.up.prevent="moveDropdown(item, -1)"
                  @keydown.enter.prevent="confirmDropdown(item, i)"
                  @keydown.esc="item.product_dropdown_open = false"
                />
                <!-- Dropdown -->
                <div v-if="item.product_dropdown_open" :id="`dd-${i}`"
                  class="absolute left-0 right-0 top-full z-50 mt-1 max-h-64 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl">
                  <button
                    v-for="(p, pi) in searchProducts(item.product_search)"
                    :key="p.id" :id="`dd-${i}-${pi}`" type="button"
                    :class="pi === item.product_dropdown_index ? 'bg-blue-50' : 'hover:bg-gray-50'"
                    class="flex w-full items-center gap-3 px-3 py-2.5 text-left border-b border-gray-100 last:border-b-0"
                    @mousedown.prevent="selectProduct(item, p)">
                    <div class="shrink-0 w-9 h-9 rounded-lg bg-gray-100 overflow-hidden flex items-center justify-center">
                      <img v-if="p.image" :src="p.image" :alt="p.name" class="w-full h-full object-cover" />
                      <WrenchScrewdriverIcon v-else class="w-5 h-5 text-gray-300" />
                    </div>
                    <div class="min-w-0 flex-1">
                      <p class="text-sm font-semibold text-gray-800 truncate">{{ p.name }}</p>
                      <p class="text-[11px] text-gray-400 truncate">
                        <span v-if="p.part_number" class="font-mono text-gray-500">{{ p.part_number }}</span>
                        <span v-if="p.part_number"> · </span>SKU: {{ p.sku }}
                      </p>
                    </div>
                    <div class="shrink-0 text-right text-[11px]">
                      <p class="font-bold text-blue-700">LKR {{ Number(p.selling_price).toLocaleString() }}</p>
                      <p class="text-gray-400">Stock: {{ p.stock_quantity }}</p>
                    </div>
                  </button>
                  <div v-if="!searchProducts(item.product_search).length" class="px-3 py-3 text-sm text-gray-400 text-center">
                    No parts found
                  </div>
                </div>
                <!-- Part meta tags -->
                <div v-if="item.product_ref" class="flex items-center gap-1.5 mt-1 flex-wrap">
                  <span v-if="item.product_ref.part_number" class="text-[10px] font-mono bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded">{{ item.product_ref.part_number }}</span>
                  <span v-if="item.product_ref.brand" class="text-[10px] text-gray-400">{{ item.product_ref.brand.name }}</span>
                  <span v-if="item.product_ref.model" class="text-[10px] text-gray-400">· {{ item.product_ref.model.name }}</span>
                </div>
              </div>

              <!-- Qty -->
              <input :id="`item-qty-${i}`" v-model.number="item.quantity" type="number" min="1"
                class="form-input w-20 shrink-0 text-center font-bold mt-0.5 text-sm" @input="recalcItem(item)"
                @focus="$event.target.select()" @keydown.enter.prevent="focusField(`item-price-${i}`)" />

              <!-- Unit Price -->
              <input :id="`item-price-${i}`" v-model.number="item.unit_price" type="number" min="0"
                class="form-input w-28 shrink-0 mt-0.5 text-sm" @input="recalcItem(item)"
                @focus="$event.target.select()" @keydown.enter.prevent="focusField(`item-discount-${i}`)" />

              <!-- Discount -->
              <input :id="`item-discount-${i}`" v-model.number="item.discount" type="number" min="0"
                class="form-input w-24 shrink-0 mt-0.5 text-sm" @input="recalcItem(item)"
                @focus="$event.target.select()" @keydown.enter.prevent="addItemAndFocus()" />

              <!-- Line total -->
              <span class="w-24 shrink-0 text-right text-sm font-bold text-blue-700 mt-2">
                {{ item.unit_price > 0 ? lkr(item._lineTotal) : '—' }}
              </span>

              <button @click="removeItem(i)"
                class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-300 hover:bg-red-50 hover:text-red-500 transition-colors shrink-0 mt-0.5">
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>

        <!-- Notes bar -->
        <div class="px-5 py-3 bg-white border-t border-gray-200 shrink-0">
          <div class="flex items-center gap-2">
            <ChatBubbleLeftIcon class="w-4 h-4 text-gray-400 shrink-0" />
            <input v-model="form.notes" type="text"
              placeholder="Sale notes or special instructions (optional)…"
              class="form-input flex-1 text-sm" />
          </div>
        </div>
      </div>

      <!-- ═══ RIGHT: Panel ═══ -->
      <div class="w-80 xl:w-96 shrink-0 flex flex-col bg-white">
        <div class="flex-1 overflow-y-auto">

          <!-- Customer section -->
          <div class="px-5 py-4 border-b border-gray-100">
            <div class="flex items-center justify-between mb-3">
              <p class="text-xs font-bold text-gray-500 uppercase tracking-wider flex items-center gap-1.5">
                <UserIcon class="w-3.5 h-3.5" /> Customer
              </p>
              <button @click="showNewCustomer = !showNewCustomer" type="button"
                class="inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-md transition-colors"
                :class="showNewCustomer ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                <UserPlusIcon class="w-3 h-3" />
                {{ showNewCustomer ? 'Cancel' : 'New Customer' }}
              </button>
            </div>

            <div v-if="showNewCustomer" class="bg-blue-50 border border-blue-200 rounded-xl p-3 space-y-2 mb-2">
              <p class="text-xs font-semibold text-blue-700 flex items-center gap-1.5">
                <UserPlusIcon class="w-3.5 h-3.5" /> Quick Add Customer
              </p>
              <input v-model="newCustomer.name" type="text" placeholder="Full name *" class="form-input text-sm" @keyup.enter="saveNewCustomer" />
              <input v-model="newCustomer.phone" type="tel" placeholder="Phone number" class="form-input text-sm" @keyup.enter="saveNewCustomer" />
              <input v-model="newCustomer.vehicle_number" type="text" placeholder="Vehicle no. e.g. CAB-1234" class="form-input text-sm font-mono uppercase" @keyup.enter="saveNewCustomer" />
              <p v-if="newCustomerError" class="text-xs text-red-600">{{ newCustomerError }}</p>
              <button @click="saveNewCustomer" :disabled="savingCustomer || !newCustomer.name.trim()" type="button"
                class="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-lg text-sm font-semibold transition-colors">
                <ArrowPathIcon v-if="savingCustomer" class="w-3.5 h-3.5 animate-spin" />
                <CheckCircleIcon v-else class="w-3.5 h-3.5" />
                {{ savingCustomer ? 'Saving…' : 'Save & Select' }}
              </button>
            </div>

            <SearchableSelect v-if="!showNewCustomer" v-model="form.customer_id"
              :options="customerOptions" placeholder="Walk-in / No customer" />

            <div v-if="!showNewCustomer && selectedCustomer"
              class="flex items-center gap-2 mt-2 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2">
              <div class="w-7 h-7 rounded-full bg-blue-600 flex items-center justify-center text-white text-xs font-bold shrink-0">
                {{ selectedCustomer.name[0].toUpperCase() }}
              </div>
              <div class="min-w-0">
                <p class="text-xs font-semibold text-blue-800 truncate">{{ selectedCustomer.name }}</p>
                <p v-if="selectedCustomer.phone" class="text-xs text-blue-400">{{ selectedCustomer.phone }}</p>
              </div>
            </div>
          </div>

          <!-- Payment section -->
          <div class="px-5 py-4 border-b border-gray-100 space-y-3">
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider flex items-center gap-1.5">
              <CreditCardIcon class="w-3.5 h-3.5" /> Payment
            </p>

            <div class="grid grid-cols-3 gap-2">
              <div>
                <label class="text-[10px] font-semibold text-gray-400 uppercase mb-1 block">Type</label>
                <select v-model="form.sale_type" class="form-input text-sm" @change="onSaleTypeChange">
                  <option value="instant">Instant</option>
                  <option value="booking">Booking</option>
                </select>
              </div>
              <div>
                <label class="text-[10px] font-semibold text-gray-400 uppercase mb-1 block">Method</label>
                <select v-model="form.payment_method" class="form-input text-sm">
                  <option value="cash">Cash</option>
                  <option value="card">Card</option>
                  <option value="bank_transfer">Bank Tfr</option>
                  <option value="cheque">Cheque</option>
                </select>
              </div>
              <div>
                <label class="text-[10px] font-semibold text-gray-400 uppercase mb-1 block">Status</label>
                <select v-model="form.payment_status" class="form-input text-sm" @change="onPaymentStatusChange">
                  <option value="paid">Paid</option>
                  <option value="pending">Pending</option>
                  <option value="partial">Partial</option>
                </select>
              </div>
            </div>

            <div v-if="form.sale_type === 'booking'">
              <label class="text-[10px] font-semibold text-gray-400 uppercase mb-1 block">Booking Expiry</label>
              <input v-model="form.booking_expires_at" type="date" class="form-input text-sm" />
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="text-[10px] font-semibold text-gray-400 uppercase mb-1 block">Discount (LKR)</label>
                <input v-model.number="form.discount" type="number" min="0" class="form-input text-sm" @input="recalc" />
              </div>
              <div>
                <label class="text-[10px] font-semibold text-gray-400 uppercase mb-1 block">Service Charge</label>
                <input v-model.number="form.maintenance_amount" type="number" min="0" class="form-input text-sm" @input="recalc" />
              </div>
            </div>

            <!-- Tax -->
            <div class="bg-gray-50 rounded-xl p-3 space-y-2">
              <p class="text-[10px] font-bold text-gray-400 uppercase flex items-center gap-1.5">
                <CalculatorIcon class="w-3 h-3" /> Tax
              </p>
              <div class="grid grid-cols-3 gap-2">
                <div>
                  <label class="text-[10px] text-gray-400 mb-1 block">Preset</label>
                  <select v-model="selectedTaxId" class="form-input text-xs" @change="applyTax">
                    <option value="">No Tax</option>
                    <option v-for="t in taxes" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="text-[10px] text-gray-400 mb-1 block">Rate %</label>
                  <input v-model.number="form.tax_rate" type="number" min="0" step="0.01" class="form-input text-xs" @input="recalc" />
                </div>
                <div>
                  <label class="text-[10px] text-gray-400 mb-1 block">Amount</label>
                  <input v-model.number="form.tax" type="number" class="form-input text-xs bg-gray-100 text-gray-500" readonly />
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Pinned Order Summary -->
        <div class="shrink-0 text-white">
          <!-- Breakdown row -->
          <div class="px-5 py-3 bg-indigo-950 space-y-1.5 text-sm">
            <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
              <ReceiptPercentIcon class="w-3.5 h-3.5" /> Order Summary
            </p>
            <div class="flex justify-between text-indigo-300">
              <span>Subtotal</span>
              <span class="font-medium text-white">LKR {{ lkr(subtotal) }}</span>
            </div>
            <div v-if="form.discount > 0" class="flex justify-between">
              <span class="text-indigo-300">Discount</span>
              <span class="text-red-300 font-medium">−LKR {{ lkr(form.discount) }}</span>
            </div>
            <div v-if="form.tax > 0" class="flex justify-between">
              <span class="text-indigo-300">Tax ({{ form.tax_rate }}%)</span>
              <span class="text-blue-300 font-medium">+LKR {{ lkr(form.tax) }}</span>
            </div>
            <div v-if="form.maintenance_amount > 0" class="flex justify-between">
              <span class="text-indigo-300">Service Charge</span>
              <span class="text-yellow-300 font-medium">+LKR {{ lkr(form.maintenance_amount) }}</span>
            </div>
          </div>
          <!-- Grand total + amount paid row -->
          <div class="px-5 py-3 bg-indigo-900 space-y-2">
            <div class="flex justify-between items-center">
              <span class="text-sm text-indigo-300">Grand Total</span>
              <span class="text-xl font-black text-white">LKR {{ lkr(total) }}</span>
            </div>
            <div>
              <label class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-1 block">Amount Paid (LKR)</label>
              <input v-model.number="form.amount_paid" type="number" min="0"
                class="w-full bg-indigo-950 text-white border border-indigo-700 rounded-lg px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-amber-400" />
            </div>
            <div v-if="form.payment_status === 'partial' && form.amount_paid < total"
              class="text-xs text-yellow-300 bg-yellow-900/40 border border-yellow-800/40 rounded-lg px-3 py-2 flex items-center gap-1.5">
              <ExclamationTriangleIcon class="w-3.5 h-3.5 shrink-0" />
              Balance due: LKR {{ lkr(total - form.amount_paid) }}
            </div>
            <div v-if="form.payment_status === 'paid' && form.amount_paid > total"
              class="text-xs text-green-300 bg-green-900/40 border border-green-800/40 rounded-lg px-3 py-2 flex items-center gap-1.5">
              <CheckCircleIcon class="w-3.5 h-3.5 shrink-0" />
              Change to return: LKR {{ lkr(form.amount_paid - total) }}
            </div>
          </div>
        </div>

        <!-- Pinned action buttons -->
        <div class="px-5 py-4 border-t border-gray-200 bg-white space-y-2 shrink-0">
          <p v-if="error" class="text-xs text-red-600 bg-red-50 border border-red-200 px-3 py-2 rounded-lg flex items-center gap-1.5">
            <ExclamationTriangleIcon class="w-3.5 h-3.5 shrink-0" /> {{ error }}
          </p>
          <button @click="submit(false)" :disabled="saving || !form.items.length"
            class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl font-bold text-sm shadow-sm transition-colors">
            <CheckCircleIcon v-if="!saving" class="w-5 h-5" />
            <ArrowPathIcon v-else class="w-5 h-5 animate-spin" />
            {{ saving ? 'Processing…' : 'Complete Sale' }}
          </button>
          <button @click="submit(true)" :disabled="saving || !form.items.length"
            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-800 hover:bg-gray-900 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl font-semibold text-sm transition-colors">
            <DocumentTextIcon class="w-4 h-4" />
            {{ saving ? 'Saving…' : 'Save as Draft' }}
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import SearchableSelect from '@/components/SearchableSelect.vue'
import {
  ArrowLeftIcon, PlusIcon, XMarkIcon, TrashIcon,
  ShoppingCartIcon, UserIcon, UserPlusIcon, CreditCardIcon, CalculatorIcon,
  ReceiptPercentIcon, CheckCircleIcon, ArrowPathIcon,
  ExclamationTriangleIcon, ChatBubbleLeftIcon, QrCodeIcon, WrenchScrewdriverIcon,
  DocumentTextIcon,
} from '@heroicons/vue/24/outline'

const router        = useRouter()
const products      = ref([])
const customers     = ref([])
const customerOptions = computed(() =>
  customers.value.map(c => ({ id: c.id, name: c.name, sub: c.phone || '' }))
)
const taxes         = ref([])
const saving        = ref(false)
const error         = ref('')
const selectedTaxId    = ref('')
const showNewCustomer  = ref(false)
const savingCustomer   = ref(false)
const newCustomerError = ref('')
const newCustomer      = reactive({ name: '', phone: '', vehicle_number: '' })

// Barcode scanner
const barcodeInput = ref('')
const barcodeError = ref('')
let barcodeClearTimer = null

function scanBarcode() {
  const code = barcodeInput.value.trim()
  barcodeInput.value = ''
  if (!code) return

  const product = products.value.find(p =>
    p.barcode?.toLowerCase() === code.toLowerCase() ||
    p.sku?.toLowerCase() === code.toLowerCase()
  )
  if (!product) {
    barcodeError.value = `Barcode/SKU "${code}" not found`
    clearTimeout(barcodeClearTimer)
    barcodeClearTimer = setTimeout(() => { barcodeError.value = '' }, 3000)
    return
  }
  if (product.stock_quantity < 1) {
    barcodeError.value = `"${product.name}" is out of stock`
    clearTimeout(barcodeClearTimer)
    barcodeClearTimer = setTimeout(() => { barcodeError.value = '' }, 3000)
    return
  }

  const existing = form.items.find(i => i.product_id == product.id)
  if (existing) {
    existing.quantity++
    recalcItem(existing)
  } else {
    const item = newItem()
    item.product_id = product.id
    form.items.push(item)
    fillProduct(item)
  }
  barcodeError.value = ''
}

function addMonths(date, months) {
  const d = new Date(date)
  d.setMonth(d.getMonth() + months)
  return d.toISOString().slice(0, 10)
}

const form = reactive({
  customer_id: '', payment_method: 'cash', payment_status: 'paid',
  discount: 0, tax: 0, tax_rate: 0, maintenance_amount: 0, amount_paid: 0, notes: '',
  sale_type: 'instant', booking_expires_at: addMonths(new Date(), 3),
  items: [],
})

function normalizeText(value) {
  return String(value ?? '').toLowerCase().trim()
}

function searchProducts(term) {
  const query = normalizeText(term)
  return products.value
    .filter(p => {
      if (!query) return true
      return [p.name, p.sku, p.barcode, p.part_category?.name, p.brand?.name, p.model?.name]
        .some(f => normalizeText(f).includes(query))
    })
    .slice(0, 20)
}

function openProductDropdown(item) {
  item.product_dropdown_open = true
}

function focusField(id) {
  nextTick(() => { document.getElementById(id)?.focus() })
}

function addItemAndFocus() {
  form.items.push(newItem())
  const i = form.items.length - 1
  nextTick(() => { document.getElementById(`item-search-${i}`)?.focus() })
}

function moveDropdown(item, dir) {
  if (!item.product_dropdown_open) { item.product_dropdown_open = true; return }
  const results = searchProducts(item.product_search)
  item.product_dropdown_index = Math.max(0, Math.min(results.length - 1, item.product_dropdown_index + dir))
  const el = document.getElementById(`dd-${form.items.indexOf(item)}-${item.product_dropdown_index}`)
  el?.scrollIntoView({ block: 'nearest' })
}

function confirmDropdown(item, i) {
  const results = searchProducts(item.product_search)
  const idx = item.product_dropdown_index
  if (item.product_dropdown_open && idx >= 0 && results[idx]) {
    selectProduct(item, results[idx], i)
  } else {
    openProductDropdown(item)
  }
}

function selectProduct(item, product, i) {
  item.product_id = product.id
  item.product_search = [product.name, product.sku ? `SKU: ${product.sku}` : null, product.barcode ? `Barcode: ${product.barcode}` : null]
    .filter(Boolean).join(' · ')
  item.product_dropdown_open = false
  fillProduct(item)
  const idx = i ?? form.items.indexOf(item)
  focusField(`item-qty-${idx}`)
}

const selectedCustomer = computed(() =>
  customers.value.find(c => c.id == form.customer_id) ?? null
)

async function saveNewCustomer() {
  if (!newCustomer.name.trim()) return
  savingCustomer.value = true; newCustomerError.value = ''
  try {
    const { data } = await axios.post('/api/customers', {
      name:           newCustomer.name.trim(),
      phone:          newCustomer.phone.trim() || null,
      vehicle_number: newCustomer.vehicle_number.trim() || null,
    })
    customers.value.unshift(data)
    form.customer_id = data.id
    showNewCustomer.value = false
    newCustomer.name = ''; newCustomer.phone = ''; newCustomer.vehicle_number = ''
  } catch (e) {
    newCustomerError.value = e.response?.data?.message
      ?? Object.values(e.response?.data?.errors ?? {}).flat().join(', ')
      ?? 'Could not save customer'
  } finally { savingCustomer.value = false }
}

function lkr(val) {
  return Number(val || 0).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function newItem() {
  return {
    product_id: '', product_search: '', product_dropdown_open: false, product_dropdown_index: -1,
    quantity: 1, unit_price: 0, discount: 0,
    product_ref: null, _lineTotal: 0,
  }
}

function addItem()     { form.items.push(newItem()) }
function removeItem(i) { form.items.splice(i, 1); recalc() }

function fillProduct(item) {
  const p = products.value.find(x => x.id == item.product_id)
  if (!p) { item.product_ref = null; return }
  item.product_ref   = p
  item.product_search = [p.name, p.sku ? `SKU: ${p.sku}` : null, p.barcode ? `Barcode: ${p.barcode}` : null]
    .filter(Boolean).join(' · ')
  item.unit_price = p.selling_price
  recalcItem(item)
}

function recalcItem(item) {
  item._lineTotal = (item.unit_price * item.quantity) - (item.discount || 0)
  recalc()
}

const subtotal = computed(() => form.items.reduce((s, i) => s + (i._lineTotal || 0), 0))
const total    = computed(() => Math.max(0, subtotal.value - (form.discount || 0) + (form.tax || 0) + (form.maintenance_amount || 0)))

function recalc() {
  if (form.tax_rate > 0) {
    form.tax = Math.round(subtotal.value * (form.tax_rate / 100) * 100) / 100
  }
  if (form.payment_status === 'paid') {
    form.amount_paid = total.value
  }
}

function onPaymentStatusChange() {
  if (form.payment_status === 'paid') form.amount_paid = total.value
  if (form.payment_status === 'pending') form.amount_paid = 0
}

function onSaleTypeChange() {
  if (form.sale_type === 'booking') {
    if (!form.customer_id) form.payment_status = 'partial'
    if (!form.booking_expires_at) form.booking_expires_at = addMonths(new Date(), 3)
  } else {
    form.payment_status = 'paid'
    form.amount_paid = total.value
  }
}

function applyTax() {
  const t = taxes.value.find(x => x.id == selectedTaxId.value)
  if (t) { form.tax_rate = t.rate; recalc() }
  else   { form.tax_rate = 0; form.tax = 0 }
}

async function submit(asDraft = false) {
  saving.value = true; error.value = ''
  try {
    const { data } = await axios.post('/api/sales', {
      customer_id:        form.customer_id || null,
      payment_method:     form.payment_method,
      payment_status:     form.payment_status,
      sale_type:          form.sale_type,
      booking_expires_at: form.sale_type === 'booking' ? form.booking_expires_at : null,
      discount:           form.discount,
      tax:                form.tax,
      tax_rate:           form.tax_rate,
      maintenance_amount: form.maintenance_amount,
      amount_paid:        form.amount_paid,
      notes:              form.notes,
      total:              total.value,
      subtotal:           subtotal.value,
      is_draft:           asDraft,
      items: form.items.filter(i => i.product_id).map(i => ({
        product_id: i.product_id,
        quantity:   i.quantity,
        unit_price: i.unit_price,
        discount:   i.discount,
      })),
    })
    router.push(`/sales/${data.id}`)
  } catch (e) {
    error.value = e.response?.data?.message
      ?? Object.values(e.response?.data?.errors ?? {}).flat().join(', ')
      ?? 'An error occurred. Please try again.'
  } finally { saving.value = false }
}

onMounted(async () => {
  const [p, c, t] = await Promise.all([
    axios.get('/api/products', { params: { per_page: 500 } }),
    axios.get('/api/customers/all'),
    axios.get('/api/tax-settings'),
  ])
  products.value  = p.data.data
  customers.value = c.data
  taxes.value     = t.data.filter(x => x.is_active)
})
</script>
