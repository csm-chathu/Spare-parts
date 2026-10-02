<template>
  <!-- Full-height POS layout, -6 to offset the parent p-6 padding -->
  <div class="-m-6 flex flex-col" style="height: calc(100vh - 57px);">

    <!-- ── Top bar ── -->
    <div class="flex items-center justify-between px-6 py-3 bg-white border-b border-gray-200 shrink-0">
      <div class="flex items-center gap-3">
        <router-link to="/sales"
          class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 transition-colors">
          <ArrowLeftIcon class="w-4 h-4" /> Sales
        </router-link>
        <span class="text-gray-300">/</span>
        <h2 class="text-base font-semibold text-gray-800">New Sale</h2>
        <span v-if="form.items.length" class="bg-blue-100 text-blue-700 font-semibold text-xs px-2 py-0.5 rounded-full">
          {{ form.items.length }} item{{ form.items.length !== 1 ? 's' : '' }}
        </span>
      </div>
    </div>

    <!-- ── Main 2-column body ── -->
    <div class="flex flex-1 min-h-0">

      <!-- ═══ LEFT: Cart ═══ -->
      <div class="flex-1 flex flex-col min-w-0 bg-white">

        <!-- ADD ITEM bar -->
        <div class="px-5 py-3 border-b border-gray-200 shrink-0 bg-gray-50">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
            <PlusCircleIcon class="w-3.5 h-3.5" /> Add Item
          </p>
          <div class="flex items-end gap-3">
            <!-- Type -->
            <div class="w-32 shrink-0">
              <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Type</label>
              <select v-model="newItem.type" class="form-input text-sm" @change="onNewItemTypeChange">
                <option value="part">Part</option>
                <option value="labour">Labour</option>
                <option value="other">Other</option>
              </select>
            </div>
            <!-- Description / product search -->
            <div class="relative flex-1 min-w-48">
              <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Description</label>
              <input v-if="newItem.type !== 'part'" v-model="newItem.description"
                class="form-input text-sm" placeholder="e.g. Engine oil change" />
              <div v-else class="relative">
                <input v-model="newItemSearch" class="form-input text-sm" placeholder="Search part name or SKU…"
                  @input="newItem.product_id = ''; newItem.description = newItemSearch; showNewItemDd = !!newItemSearch.trim()"
                  @focus="showNewItemDd = !!newItemSearch.trim()"
                  @keydown.esc="showNewItemDd = false" />
                <div v-if="showNewItemDd" class="absolute left-0 right-0 top-full z-50 mt-1 max-h-60 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl">
                  <button v-for="p in searchProducts(newItemSearch)" :key="p.id" type="button"
                    class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-blue-50 border-b border-gray-100 last:border-b-0"
                    @mousedown.prevent="pickNewItemProduct(p)">
                    <div class="min-w-0 flex-1">
                      <p class="text-sm font-semibold text-gray-800 truncate">{{ p.name }}</p>
                      <p class="text-[11px] text-gray-400">SKU: {{ p.sku }} · Stock: {{ p.stock_quantity }}</p>
                    </div>
                    <span class="font-bold text-blue-700 text-sm shrink-0">LKR {{ Number(p.selling_price).toLocaleString() }}</span>
                  </button>
                  <div v-if="!searchProducts(newItemSearch).length" class="px-3 py-3 text-sm text-gray-400 text-center">No parts found</div>
                </div>
              </div>
            </div>
            <!-- Qty -->
            <div class="w-20 shrink-0">
              <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Qty</label>
              <input v-model.number="newItem.quantity" type="number" min="1" step="1"
                @focus="$event.target.select()" class="form-input text-sm text-center" />
            </div>
            <!-- Unit Price -->
            <div class="w-28 shrink-0">
              <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Unit Price</label>
              <input v-model.number="newItem.unit_price" type="number" min="0" step="1"
                @focus="$event.target.select()" class="form-input text-sm" />
            </div>
            <!-- Discount -->
            <div class="w-24 shrink-0">
              <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Discount</label>
              <input v-model.number="newItem.discount" type="number" min="0" step="1"
                @focus="$event.target.select()" class="form-input text-sm" />
            </div>
            <!-- Line total -->
            <div class="w-32 shrink-0">
              <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Line Total</label>
              <div class="form-input text-sm font-bold text-blue-700 bg-blue-50 border-blue-200 text-right">
                LKR {{ lkr(Math.max(0, newItem.quantity * newItem.unit_price - (newItem.discount || 0))) }}
              </div>
            </div>
            <!-- Add button -->
            <button @click="addNewItem"
              class="shrink-0 inline-flex items-center gap-1.5 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold transition-colors">
              <PlusIcon class="w-4 h-4" /> Add
            </button>
          </div>
          <p v-if="addItemError" class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
            <ExclamationTriangleIcon class="w-3.5 h-3.5 shrink-0" /> {{ addItemError }}
          </p>
        </div>

        <!-- Items table -->
        <div class="flex-1 overflow-y-auto">

          <!-- Table header -->
          <div v-if="form.items.length" class="flex items-center px-5 py-2 bg-gray-100 border-b border-gray-200 text-[10px] font-bold text-gray-400 uppercase tracking-widest sticky top-0">
            <span class="w-16 shrink-0">Type</span>
            <span class="flex-1">Description</span>
            <span class="w-24 shrink-0 text-center">Qty</span>
            <span class="w-28 shrink-0 text-right">Unit Price</span>
            <span class="w-24 shrink-0 text-right">Discount</span>
            <span class="w-28 shrink-0 text-right">Total</span>
            <span class="w-8 shrink-0"></span>
          </div>

          <!-- Empty state -->
          <div v-if="!form.items.length"
            class="flex flex-col items-center justify-center h-full text-gray-400">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mb-3">
              <ShoppingCartIcon class="w-8 h-8 text-gray-300" />
            </div>
            <p class="font-medium text-gray-500 mb-1">Cart is empty</p>
            <p class="text-sm">Add items using the form above</p>
          </div>

          <!-- Item rows -->
          <div v-for="(item, i) in form.items" :key="i"
            class="flex items-center px-5 py-3 border-b border-gray-100 hover:bg-gray-50 transition-colors">
            <!-- Type badge -->
            <div class="w-16 shrink-0">
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full capitalize"
                :class="item.type === 'part' ? 'bg-blue-100 text-blue-700' : item.type === 'labour' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                {{ item.type }}
              </span>
            </div>
            <!-- Description -->
            <div class="flex-1 min-w-0">
              <p class="font-medium text-gray-900 text-sm truncate">{{ item.description || '—' }}</p>
              <p v-if="item.product_ref?.sku" class="text-[10px] text-gray-400">SKU: {{ item.product_ref.sku }}</p>
            </div>
            <!-- Qty -->
            <div class="w-24 shrink-0 flex items-center gap-1">
              <button @click="changeQty(i, -1)"
                class="w-6 h-6 flex items-center justify-center rounded bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold text-sm transition-colors shrink-0">−</button>
              <span class="flex-1 text-center text-sm font-semibold text-gray-800">{{ item.quantity }}</span>
              <button @click="changeQty(i, 1)"
                class="w-6 h-6 flex items-center justify-center rounded bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold text-sm transition-colors shrink-0">+</button>
            </div>
            <!-- Unit price -->
            <span class="w-28 shrink-0 text-right text-sm text-gray-700">{{ lkr(item.unit_price) }}</span>
            <!-- Discount -->
            <div class="w-24 shrink-0">
              <input v-model.number="item.discount" type="number" min="0"
                @input="updateItemDiscount(i)"
                @focus="$event.target.select()"
                class="w-full text-right text-sm rounded px-1.5 py-0.5 border border-transparent focus:border-gray-300 focus:outline-none bg-transparent hover:bg-gray-100 focus:bg-white transition-colors"
                :class="item.discount > 0 ? 'text-red-500 font-medium' : 'text-gray-400'" />
            </div>
            <!-- Total -->
            <span class="w-28 shrink-0 text-right text-sm font-bold text-blue-700">LKR {{ lkr(item._lineTotal) }}</span>
            <!-- Delete -->
            <button @click="removeItem(i)"
              class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-300 hover:bg-red-50 hover:text-red-500 transition-colors shrink-0">
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Notes bar -->
        <div class="px-5 py-2.5 bg-white border-t border-gray-200 shrink-0">
          <div class="flex items-center gap-2">
            <ChatBubbleLeftIcon class="w-4 h-4 text-gray-400 shrink-0" />
            <input v-model="form.notes" type="text"
              placeholder="Sale notes or special instructions (optional)…"
              class="form-input flex-1 text-sm" />
          </div>
        </div>

        <!-- Pinned Order Summary (indigo, matches Job Card total bar) -->
        <div class="shrink-0 text-white">
          <!-- Breakdown row -->
          <div class="flex items-center gap-6 px-6 py-3 bg-indigo-950 text-sm flex-wrap">
            <span class="text-indigo-300 ml-auto">Subtotal</span>
            <span class="text-white font-medium w-32 text-right">LKR {{ lkr(subtotal) }}</span>
            <template v-if="form.discount > 0">
              <span class="text-indigo-300">Discount</span>
              <span class="text-red-300 font-medium w-32 text-right">−LKR {{ lkr(form.discount) }}</span>
            </template>
            <template v-if="form.tax > 0">
              <span class="text-indigo-300">Tax ({{ form.tax_rate }}%)</span>
              <span class="text-blue-300 font-medium w-32 text-right">+LKR {{ lkr(form.tax) }}</span>
            </template>
          </div>
          <!-- Grand total row -->
          <div class="flex items-center gap-4 px-6 py-3 bg-indigo-900">
            <div class="flex-1"></div>
            <span class="text-indigo-300 text-sm">Amount Paid (LKR)</span>
            <input v-model.number="form.amount_paid" type="number" min="0" @focus="$event.target.select()"
              class="w-32 bg-black border border-indigo-700 text-white text-sm rounded-lg px-2 py-1 text-right focus:outline-none focus:border-amber-400" />
            <span class="text-indigo-300 text-sm ml-4">Grand Total</span>
            <span class="text-xl font-black text-white w-40 text-right">LKR {{ lkr(total) }}</span>
            <div class="w-2"></div>
          </div>
          <div v-if="form.payment_status === 'partial' && form.amount_paid < total"
            class="px-6 py-2 bg-yellow-900/60 text-xs text-yellow-300 flex items-center gap-1.5">
            <ExclamationTriangleIcon class="w-3.5 h-3.5 shrink-0" />
            Balance due: LKR {{ lkr(total - form.amount_paid) }}
          </div>
          <div v-if="form.payment_status === 'paid' && form.amount_paid > total"
            class="px-6 py-2 bg-green-900/60 text-xs text-green-300 flex items-center gap-1.5">
            <CheckCircleIcon class="w-3.5 h-3.5 shrink-0" />
            Change to return: LKR {{ lkr(form.amount_paid - total) }}
          </div>
        </div>
      </div>

      <!-- ═══ RIGHT: Panel ═══ -->
      <div class="w-72 xl:w-80 shrink-0 flex flex-col bg-white border-l border-gray-200">
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

            <div class="grid grid-cols-2 gap-2">
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

            <div>
              <label class="text-[10px] font-semibold text-gray-400 uppercase mb-1 block">Discount (LKR)</label>
              <input v-model.number="form.discount" type="number" min="0" class="form-input text-sm" @input="recalc" @focus="$event.target.select()" />
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

        <!-- Action buttons pinned at bottom of right panel -->
        <div class="shrink-0 px-4 py-3 border-t border-gray-200 bg-white space-y-2">
          <p v-if="error" class="text-xs text-red-600 text-center">{{ error }}</p>
          <button @click="submit(false)" :disabled="saving || !form.items.length"
            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white rounded-xl text-sm font-bold transition-colors shadow-sm">
            <CheckCircleIcon v-if="!saving" class="w-4 h-4" />
            <ArrowPathIcon v-else class="w-4 h-4 animate-spin" />
            {{ saving ? 'Processing…' : 'Complete Sale' }}
          </button>
          <button @click="submit(true)" :disabled="saving || !form.items.length"
            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 disabled:opacity-40 text-gray-700 rounded-xl text-sm font-semibold transition-colors">
            <DocumentTextIcon class="w-4 h-4" />
            {{ saving ? 'Saving…' : 'Save Draft' }}
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import SearchableSelect from '@/components/SearchableSelect.vue'
import {
  ArrowLeftIcon, PlusIcon, PlusCircleIcon, TrashIcon,
  ShoppingCartIcon, UserIcon, UserPlusIcon, CreditCardIcon, CalculatorIcon,
  CheckCircleIcon, ArrowPathIcon,
  ExclamationTriangleIcon, ChatBubbleLeftIcon,
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

// ADD ITEM bar state
const newItemSearch  = ref('')
const showNewItemDd  = ref(false)
const addItemError   = ref('')
const newItem = reactive({ type: 'part', description: '', product_id: '', quantity: 1, unit_price: 0, discount: 0 })

function onNewItemTypeChange() {
  newItem.product_id  = ''
  newItem.description = ''
  newItemSearch.value = ''
  showNewItemDd.value = false
  newItem.unit_price  = 0
  newItem.discount    = 0
  newItem.quantity    = 1
}

function pickNewItemProduct(p) {
  newItem.product_id  = p.id
  newItem.description = p.name
  newItem.unit_price  = p.selling_price
  newItemSearch.value = p.name
  showNewItemDd.value = false
}

function addNewItem() {
  addItemError.value = ''
  if (newItem.type === 'part' && !newItem.product_id) {
    addItemError.value = 'Please select a part from the dropdown.'
    return
  }
  if (newItem.type !== 'part' && !newItem.description.trim()) {
    addItemError.value = 'Please enter a description.'
    return
  }
  if (!newItem.unit_price) {
    addItemError.value = 'Unit price is required.'
    return
  }
  const lineTotal = (newItem.unit_price * newItem.quantity) - (newItem.discount || 0)
  const p = newItem.type === 'part' ? products.value.find(x => x.id == newItem.product_id) : null
  form.items.push({
    type:        newItem.type,
    description: newItem.description,
    product_id:  newItem.product_id || null,
    product_ref: p ?? null,
    quantity:    newItem.quantity,
    unit_price:  newItem.unit_price,
    discount:    newItem.discount || 0,
    _lineTotal:  lineTotal,
  })
  recalc()
  // Reset bar
  newItem.product_id  = ''
  newItem.description = ''
  newItem.unit_price  = 0
  newItem.discount    = 0
  newItem.quantity    = 1
  newItemSearch.value = ''
  showNewItemDd.value = false
}

const form = reactive({
  customer_id: '', payment_method: 'cash', payment_status: 'paid',
  discount: 0, tax: 0, tax_rate: 0, amount_paid: 0, notes: '',
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

function removeItem(i) { form.items.splice(i, 1); recalc() }

function changeQty(i, delta) {
  const item = form.items[i]
  const next = item.quantity + delta
  if (next < 1) return
  item.quantity = next
  item._lineTotal = (item.unit_price * item.quantity) - (item.discount || 0)
  recalc()
}

function updateItemDiscount(i) {
  const item = form.items[i]
  item.discount = Math.max(0, item.discount || 0)
  item._lineTotal = (item.unit_price * item.quantity) - item.discount
  recalc()
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

const subtotal = computed(() => form.items.reduce((s, i) => s + (i._lineTotal || 0), 0))
const total    = computed(() => Math.max(0, subtotal.value - (form.discount || 0) + (form.tax || 0)))

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

function applyTax() {
  const t = taxes.value.find(x => x.id == selectedTaxId.value)
  if (t) { form.tax_rate = t.rate; recalc() }
  else   { form.tax_rate = 0; form.tax = 0 }
}

async function submit(asDraft = false) {
  saving.value = true; error.value = ''
  try {
    const { data } = await axios.post('/api/sales', {
      customer_id:    form.customer_id || null,
      payment_method: form.payment_method,
      payment_status: form.payment_status,
      sale_type:      'instant',
      discount:       form.discount,
      tax:            form.tax,
      tax_rate:       form.tax_rate,
      amount_paid:    form.amount_paid,
      notes:          form.notes,
      total:          total.value,
      subtotal:       subtotal.value,
      is_draft:       asDraft,
      items: form.items.map(i => ({
        product_id:  i.product_id  || null,
        type:        i.type,
        description: i.description,
        quantity:    i.quantity,
        unit_price:  i.unit_price,
        discount:    i.discount,
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
