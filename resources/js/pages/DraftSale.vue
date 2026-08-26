<template>
  <div class="space-y-0">

    <!-- Top bar -->
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
        <router-link to="/sales"
          class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 transition-colors">
          <ArrowLeftIcon class="w-4 h-4" /> Back to Sales
        </router-link>
        <span class="text-gray-300">/</span>
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
          <span class="text-xs font-bold bg-yellow-400 text-yellow-900 px-2 py-0.5 rounded uppercase tracking-wide">Draft</span>
          {{ draft?.invoice_number }}
        </h2>
      </div>
      <div class="flex items-center gap-2 text-xs text-gray-400">
        <ClockIcon class="w-4 h-4" />
        Last saved: {{ lastSaved }}
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-24 text-gray-400">
      <ArrowPathIcon class="w-5 h-5 animate-spin mr-2" /> Loading draft…
    </div>

    <template v-else-if="draft">
      <!-- POS layout: Cart left | Summary right -->
      <div class="flex gap-5 items-start">

        <!-- ───── LEFT: Cart ───── -->
        <div class="flex-1 min-w-0 space-y-4">

          <!-- Cart header -->
          <div class="card">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                <WrenchScrewdriverIcon class="w-4 h-4 text-yellow-500" /> Parts / Items
                <span v-if="form.items.length" class="bg-yellow-100 text-yellow-700 text-xs font-bold px-2 py-0.5 rounded-full">
                  {{ form.items.length }}
                </span>
              </h3>
              <button @click="addItem"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors">
                <PlusIcon class="w-4 h-4" /> Add Part
              </button>
            </div>

            <!-- Barcode scanner -->
            <div class="mb-3">
              <div class="relative">
                <QrCodeIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                <input
                  v-model="barcodeInput"
                  type="text"
                  placeholder="Scan barcode / SKU and press Enter to add part…"
                  class="form-input pl-9 text-sm font-mono"
                  @keyup.enter="scanBarcode"
                  @keyup.tab.prevent="scanBarcode"
                />
              </div>
              <p v-if="barcodeError" class="mt-1 text-xs text-red-600 flex items-center gap-1">
                <ExclamationTriangleIcon class="w-3.5 h-3.5 shrink-0" /> {{ barcodeError }}
              </p>
            </div>

            <!-- Empty state -->
            <div v-if="!form.items.length"
              class="flex flex-col items-center justify-center py-10 text-gray-400 border-2 border-dashed border-gray-200 rounded-xl">
              <WrenchScrewdriverIcon class="w-12 h-12 opacity-20 mb-2" />
              <p class="text-sm">No parts added yet</p>
              <button @click="addItem" class="mt-2 text-blue-600 hover:underline text-sm font-medium">+ Add first part</button>
            </div>

            <!-- Cart item rows -->
            <div class="space-y-3">
              <div v-for="(item, i) in form.items" :key="i" class="border border-gray-200 rounded-xl">

                <!-- Item header -->
                <div class="bg-gray-50 px-4 py-2.5 flex items-start gap-3 border-b border-gray-100">
                  <span class="w-6 h-6 rounded-full bg-yellow-100 text-yellow-700 text-xs font-bold flex items-center justify-center shrink-0 mt-2">{{ i + 1 }}</span>
                  <div class="relative flex-1">
                    <input
                      v-model="item.product_search"
                      type="text"
                      class="form-input w-full font-medium"
                      placeholder="Type part name, SKU, or barcode…"
                      @input="item.product_search?.trim() ? openProductDropdown(item) : (item.product_dropdown_open = false)"
                      @focus="item.product_search?.trim() ? openProductDropdown(item) : null"
                      @keyup.enter.prevent="openProductDropdown(item)"
                    />
                    <div v-if="item.product_dropdown_open" class="absolute left-0 right-0 top-full z-50 mt-1 max-h-64 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                      <button
                        v-for="p in searchProducts(item.product_search)"
                        :key="p.id"
                        type="button"
                        class="flex w-full items-start justify-between gap-3 px-3 py-2 text-left hover:bg-blue-50 border-b border-gray-100 last:border-b-0"
                        @mousedown.prevent="selectProduct(item, p)"
                      >
                        <div class="shrink-0 w-10 h-10 rounded-md bg-gray-100 overflow-hidden flex items-center justify-center">
                          <img v-if="p.image" :src="p.image" :alt="p.name" class="w-full h-full object-cover" />
                          <WrenchScrewdriverIcon v-else class="w-5 h-5 text-gray-300" />
                        </div>
                        <div class="min-w-0 flex-1">
                          <p class="text-sm font-medium text-gray-800 truncate">{{ p.name }}</p>
                          <p class="text-[11px] text-gray-500 truncate">
                            SKU: {{ p.sku }}<span v-if="p.barcode"> · {{ p.barcode }}</span>
                          </p>
                        </div>
                        <div class="shrink-0 text-right text-[11px] text-gray-500">
                          <p class="font-medium text-blue-700">LKR {{ Number(p.selling_price).toLocaleString() }}</p>
                          <p>Stock: {{ p.stock_quantity }}</p>
                        </div>
                      </button>
                      <div v-if="!searchProducts(item.product_search).length" class="px-3 py-2 text-sm text-gray-400">No parts found</div>
                    </div>
                  </div>
                  <button @click="removeItem(i)"
                    class="w-7 h-7 flex items-center justify-center rounded-full text-red-400 hover:bg-red-50 hover:text-red-600 transition-colors shrink-0 mt-1">
                    <XMarkIcon class="w-4 h-4" />
                  </button>
                </div>

                <!-- Item fields -->
                <div class="px-4 py-3 space-y-3">
                  <div class="grid grid-cols-3 gap-3">
                    <div>
                      <label class="text-xs font-medium text-gray-500 mb-1 block">Quantity</label>
                      <input v-model.number="item.quantity" type="number" min="1" class="form-input text-center font-semibold" @input="recalcItem(item)" />
                    </div>
                    <div>
                      <label class="text-xs font-medium text-gray-500 mb-1 block">Unit Price (LKR)</label>
                      <input v-model.number="item.unit_price" type="number" min="0" class="form-input" @input="recalcItem(item)" />
                    </div>
                    <div>
                      <label class="text-xs font-medium text-gray-500 mb-1 block">Item Discount (LKR)</label>
                      <input v-model.number="item.discount" type="number" min="0" class="form-input" @input="recalcItem(item)" />
                    </div>
                  </div>
                  <div v-if="item.unit_price > 0" class="text-right text-sm text-gray-500">
                    Line Total: <strong class="text-blue-700">LKR {{ lkr(item._lineTotal) }}</strong>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="card">
            <label class="form-label flex items-center gap-1.5 mb-2">
              <ChatBubbleLeftIcon class="w-4 h-4 text-gray-400" /> Job Notes (optional)
            </label>
            <textarea v-model="form.notes" rows="2" placeholder="e.g. Replace brake pads, oil change, alignment…" class="form-input resize-none"></textarea>
          </div>
        </div>

        <!-- ───── RIGHT: Summary ───── -->
        <div class="w-80 xl:w-96 shrink-0 space-y-4 sticky top-4">

          <!-- Vehicle / Customer -->
          <div class="card space-y-2">
            <h3 class="font-semibold text-gray-700 flex items-center gap-2 mb-1">
              <UserIcon class="w-4 h-4 text-blue-500" /> Customer
            </h3>
            <SearchableSelect v-model="form.customer_id" :options="customerOptions" placeholder="Walk-in / No customer" />
            <div v-if="selectedCustomer" class="flex items-center gap-2 bg-blue-50 border border-blue-100 rounded-lg px-2.5 py-1.5">
              <div class="w-6 h-6 rounded-full bg-blue-200 flex items-center justify-center text-blue-700 text-xs font-bold shrink-0">
                {{ selectedCustomer.name[0].toUpperCase() }}
              </div>
              <div class="min-w-0">
                <p class="text-xs font-semibold text-blue-800 truncate">{{ selectedCustomer.name }}</p>
                <p v-if="selectedCustomer.vehicle_number" class="text-xs text-blue-600 font-mono">{{ selectedCustomer.vehicle_number }}</p>
                <p v-if="selectedCustomer.phone" class="text-xs text-blue-400">{{ selectedCustomer.phone }}</p>
              </div>
            </div>
          </div>

          <!-- Payment -->
          <div class="card space-y-3">
            <h3 class="font-semibold text-gray-700 flex items-center gap-2 mb-1">
              <CreditCardIcon class="w-4 h-4 text-green-500" /> Payment
            </h3>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="form-label">Method</label>
                <select v-model="form.payment_method" class="form-input">
                  <option value="cash">Cash</option>
                  <option value="card">Card</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="cheque">Cheque</option>
                </select>
              </div>
              <div>
                <label class="form-label">Status</label>
                <select v-model="form.payment_status" class="form-input">
                  <option value="paid">Paid</option>
                  <option value="pending">Pending</option>
                  <option value="partial">Partial</option>
                </select>
              </div>
            </div>
            <div>
              <label class="form-label">Overall Discount (LKR)</label>
              <input v-model.number="form.discount" type="number" min="0" class="form-input" @input="recalc" />
            </div>
            <div>
              <label class="form-label flex items-center gap-1.5">
                <WrenchScrewdriverIcon class="w-3.5 h-3.5 text-gray-400" /> Maintenance / Service Charge (LKR)
              </label>
              <input v-model.number="form.maintenance_amount" type="number" min="0" class="form-input" @input="recalc" />
            </div>
          </div>

          <!-- Order total -->
          <div class="card bg-gray-800 text-white">
            <h3 class="text-xs uppercase tracking-wider text-gray-400 mb-3 flex items-center gap-1.5">
              <ReceiptPercentIcon class="w-3.5 h-3.5" /> Repair Bill Summary
            </h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-400">Parts Subtotal</span>
                <span class="font-medium">LKR {{ lkr(subtotal) }}</span>
              </div>
              <div v-if="form.discount > 0" class="flex justify-between">
                <span class="text-gray-400">Discount</span>
                <span class="text-red-400">-LKR {{ lkr(form.discount) }}</span>
              </div>
              <div v-if="form.maintenance_amount > 0" class="flex justify-between">
                <span class="text-gray-400">Maintenance</span>
                <span class="text-yellow-300">+LKR {{ lkr(form.maintenance_amount) }}</span>
              </div>
              <div class="border-t border-gray-600 pt-2 flex justify-between text-lg font-bold">
                <span>Total</span>
                <span class="text-yellow-400">LKR {{ lkr(total) }}</span>
              </div>
            </div>

            <div class="mt-3 pt-3 border-t border-gray-600">
              <label class="text-xs text-gray-400 mb-1 block">Amount Paid (LKR)</label>
              <input v-model.number="form.amount_paid" type="number" min="0"
                class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400" />
            </div>
          </div>

          <!-- Draft info -->
          <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 text-xs text-yellow-800 space-y-1">
            <p class="font-semibold flex items-center gap-1.5"><ClockIcon class="w-3.5 h-3.5" /> Draft — Not yet finalized</p>
            <p>Stock is not deducted and no accounting entry is posted until you finalize.</p>
          </div>

          <!-- Error -->
          <p v-if="error" class="text-sm text-red-600 bg-red-50 border border-red-200 px-3 py-2 rounded-lg flex items-center gap-2">
            <ExclamationTriangleIcon class="w-4 h-4 shrink-0" /> {{ error }}
          </p>

          <!-- Buttons -->
          <button @click="saveDraft" :disabled="saving || !form.items.length"
            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-yellow-500 hover:bg-yellow-600 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl font-semibold text-sm shadow transition-colors">
            <DocumentTextIcon v-if="!saving" class="w-4 h-4" />
            <ArrowPathIcon v-else class="w-4 h-4 animate-spin" />
            {{ saving ? 'Saving…' : 'Save Draft' }}
          </button>

          <button @click="finalize" :disabled="finalizing || !form.items.length"
            class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl font-bold text-base shadow-md transition-colors">
            <CheckCircleIcon v-if="!finalizing" class="w-5 h-5" />
            <ArrowPathIcon v-else class="w-5 h-5 animate-spin" />
            {{ finalizing ? 'Finalizing…' : 'Finalize & Print Invoice' }}
          </button>

          <p class="text-center text-xs text-gray-400">
            {{ form.items.length }} part{{ form.items.length !== 1 ? 's' : '' }} · Total: LKR {{ lkr(total) }}
          </p>
        </div>
      </div>
    </template>

    <div v-else-if="!loading" class="text-center py-20 text-gray-400">
      <p>Draft not found.</p>
      <router-link to="/sales" class="text-blue-600 hover:underline text-sm mt-2 inline-block">← Back to Sales</router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'
import SearchableSelect from '@/components/SearchableSelect.vue'
import {
  ArrowLeftIcon, PlusIcon, XMarkIcon, WrenchScrewdriverIcon,
  UserIcon, CreditCardIcon, ReceiptPercentIcon, CheckCircleIcon,
  ArrowPathIcon, ExclamationTriangleIcon, ChatBubbleLeftIcon,
  QrCodeIcon, DocumentTextIcon, ClockIcon,
} from '@heroicons/vue/24/outline'

const router  = useRouter()
const route   = useRoute()
const loading = ref(true)
const saving  = ref(false)
const finalizing = ref(false)
const error   = ref('')
const draft   = ref(null)
const lastSaved = ref('—')
const products  = ref([])
const customers = ref([])

const customerOptions = computed(() =>
  customers.value.map(c => ({ id: c.id, name: c.name, sub: c.vehicle_number || c.phone || '' }))
)
const selectedCustomer = computed(() =>
  customers.value.find(c => c.id == form.customer_id) ?? null
)

const form = reactive({
  customer_id: '',
  payment_method: 'cash',
  payment_status: 'pending',
  discount: 0,
  maintenance_amount: 0,
  amount_paid: 0,
  notes: '',
  items: [],
})

// Barcode
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

function normalizeText(v) { return String(v ?? '').toLowerCase().trim() }

function searchProducts(term) {
  const q = normalizeText(term)
  return products.value
    .filter(p => !q || [p.name, p.sku, p.barcode, p.part_category?.name].some(f => normalizeText(f).includes(q)))
    .slice(0, 20)
}

function openProductDropdown(item) { item.product_dropdown_open = true }

function selectProduct(item, product) {
  item.product_id = product.id
  item.product_search = [product.name, product.sku ? `SKU: ${product.sku}` : null].filter(Boolean).join(' · ')
  item.product_dropdown_open = false
  fillProduct(item)
}

function fillProduct(item) {
  const p = products.value.find(x => x.id == item.product_id)
  if (!p) return
  item.product_search = [p.name, p.sku ? `SKU: ${p.sku}` : null].filter(Boolean).join(' · ')
  item.unit_price = p.selling_price
  recalcItem(item)
}

function newItem() {
  return { product_id: '', product_search: '', product_dropdown_open: false, quantity: 1, unit_price: 0, discount: 0, _lineTotal: 0 }
}

function addItem()     { form.items.push(newItem()) }
function removeItem(i) { form.items.splice(i, 1); recalc() }

function recalcItem(item) {
  item._lineTotal = (item.unit_price * item.quantity) - (item.discount || 0)
  recalc()
}

const subtotal = computed(() => form.items.reduce((s, i) => s + (i._lineTotal || 0), 0))
const total    = computed(() => Math.max(0, subtotal.value - (form.discount || 0) + (form.maintenance_amount || 0)))

function recalc() {}

function lkr(val) {
  return Number(val || 0).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function buildPayload() {
  return {
    customer_id:        form.customer_id || null,
    payment_method:     form.payment_method,
    payment_status:     form.payment_status,
    discount:           form.discount,
    maintenance_amount: form.maintenance_amount,
    amount_paid:        form.amount_paid,
    notes:              form.notes,
    items: form.items.filter(i => i.product_id).map(i => ({
      product_id: i.product_id,
      quantity:   i.quantity,
      unit_price: i.unit_price,
      discount:   i.discount,
    })),
  }
}

async function saveDraft() {
  if (!form.items.length) return
  saving.value = true; error.value = ''
  try {
    await axios.put(`/api/sales/${route.params.id}`, buildPayload())
    router.push({ name: 'sales' })
  } catch (e) {
    error.value = e.response?.data?.message
      ?? Object.values(e.response?.data?.errors ?? {}).flat().join(', ')
      ?? 'Could not save draft'
  } finally { saving.value = false }
}

async function finalize() {
  if (!form.items.length) return
  finalizing.value = true; error.value = ''
  try {
    // Save latest changes first, then finalize
    await axios.put(`/api/sales/${route.params.id}`, buildPayload())
    const { data } = await axios.post(`/api/sales/${route.params.id}/finalize`)
    router.push(`/sales/${data.id}`)
  } catch (e) {
    error.value = e.response?.data?.message
      ?? Object.values(e.response?.data?.errors ?? {}).flat().join(', ')
      ?? 'Could not finalize draft'
  } finally { finalizing.value = false }
}

onMounted(async () => {
  try {
    const [saleRes, prodRes, custRes] = await Promise.all([
      axios.get(`/api/sales/${route.params.id}`),
      axios.get('/api/products', { params: { per_page: 500 } }),
      axios.get('/api/customers/all'),
    ])
    const s = saleRes.data
    if (!s.is_draft) {
      router.replace(`/sales/${s.id}`)
      return
    }
    draft.value = s
    products.value  = prodRes.data.data
    customers.value = custRes.data

    // Populate form from draft
    form.customer_id        = s.customer_id ?? ''
    form.payment_method     = s.payment_method ?? 'cash'
    form.payment_status     = s.payment_status ?? 'pending'
    form.discount           = Number(s.discount) || 0
    form.maintenance_amount = Number(s.maintenance_amount) || 0
    form.amount_paid        = Number(s.amount_paid) || 0
    form.notes              = s.notes ?? ''

    form.items = (s.items ?? []).map(si => {
      const lineTotal = (Number(si.unit_price) * si.quantity) - (Number(si.discount) || 0)
      return {
        product_id:           si.product_id,
        product_search:       si.product?.name ? [si.product.name, si.product.sku ? `SKU: ${si.product.sku}` : null].filter(Boolean).join(' · ') : '',
        product_dropdown_open: false,
        quantity:             si.quantity,
        unit_price:           Number(si.unit_price),
        discount:             Number(si.discount) || 0,
        _lineTotal:           lineTotal,
      }
    })
  } catch {
    draft.value = null
  } finally {
    loading.value = false
  }
})
</script>
