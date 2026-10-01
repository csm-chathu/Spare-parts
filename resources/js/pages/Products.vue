<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <div class="flex items-center gap-2 flex-wrap">
        <input v-model="search" type="search" placeholder="Search parts…" class="form-input w-56" @input="debouncedFetch" />
        <SearchableSelect v-model="partCategoryFilter" :options="partCategories"
          placeholder="All categories" class="w-40" @update:modelValue="onFilterChange" />
        <SearchableSelect v-model="vehicleTypeFilter" :options="vehicleTypes"
          placeholder="All vehicles" class="w-32" @update:modelValue="onFilterChange" />
        <SearchableSelect v-model="brandFilter" :options="brands"
          placeholder="All brands" class="w-32" @update:modelValue="onFilterChange" />
        <SearchableSelect v-model="modelFilter" :options="vehicleModels"
          placeholder="All models" class="w-32" @update:modelValue="onFilterChange" />
        <label class="flex items-center gap-1 text-sm text-gray-600 cursor-pointer">
          <input type="checkbox" v-model="lowStockOnly" @change="onFilterChange" class="rounded text-blue-600" />
          Low stock only
        </label>
        <button v-if="hasActiveFilter" @click="clearFilters"
          class="text-xs text-gray-500 hover:text-red-600 underline whitespace-nowrap">Clear filters</button>
      </div>
      <button @click="openCreate" class="btn-primary flex items-center gap-2 shrink-0">
        <PlusIcon class="w-4 h-4" /> Add Part
      </button>
    </div>

    <!-- Table -->
    <div class="card p-0 overflow-hidden">
      <!-- Loader -->
      <div v-if="loading" class="flex items-center justify-center py-16">
        <svg class="w-8 h-8 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="table-th">Part Number</th>
              <th class="table-th">Part Name</th>
              <th class="table-th">Vehicle / Brand / Model</th>
              <th class="table-th">Rack</th>
              <th class="table-th">Stock</th>
              <th class="table-th">Price</th>
              <th class="table-th">Status</th>
              <th class="table-th">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <template v-if="loading">
              <tr v-for="n in 8" :key="n" class="animate-pulse">
                <td class="table-td"><div class="h-3 bg-gray-200 rounded w-20"></div></td>
                <td class="table-td">
                  <div class="space-y-1.5">
                    <div class="h-3 bg-gray-200 rounded w-36"></div>
                    <div class="h-2.5 bg-gray-100 rounded w-24"></div>
                  </div>
                </td>
                <td class="table-td"><div class="h-3 bg-gray-200 rounded w-28"></div></td>
                <td class="table-td"><div class="h-3 bg-gray-200 rounded w-12"></div></td>
                <td class="table-td"><div class="h-5 bg-gray-200 rounded-full w-10"></div></td>
                <td class="table-td">
                  <div class="space-y-1.5">
                    <div class="h-3 bg-gray-200 rounded w-20"></div>
                    <div class="h-2.5 bg-gray-100 rounded w-16"></div>
                  </div>
                </td>
                <td class="table-td"><div class="h-5 bg-gray-200 rounded-full w-14"></div></td>
                <td class="table-td"><div class="h-7 bg-gray-200 rounded w-32"></div></td>
              </tr>
            </template>
            <tr v-else v-for="p in products.data" :key="p.id" class="hover:bg-gray-50">
              <td class="table-td">
                <div class="font-mono text-xs">{{ p.part_number || '—' }}</div>
                <div v-if="p.part_category" class="text-[10px] text-gray-400 mt-0.5">{{ p.part_category.name }}</div>
              </td>
              <td class="table-td">
                <div>
                  <span class="font-medium line-clamp-2">{{ p.name }}</span>
                  <p v-if="p.image" class="text-xs text-gray-400 font-mono">{{ p.image.split('/').pop() }}</p>
                </div>
              </td>
              <td class="table-td text-xs text-gray-600">
                <div>
                  <span v-if="p.vehicle_type">{{ p.vehicle_type.name }}</span>
                  <span v-if="p.brand"> · {{ p.brand.name }}</span>
                  <span v-if="p.model"> · {{ p.model.name }}</span>
                  <span v-if="!p.vehicle_type && !p.brand && !p.model">—</span>
                </div>
                <div v-if="p.quality_type" class="text-[10px] text-gray-400 mt-0.5">{{ p.quality_type.name }}</div>
              </td>
              <td class="table-td text-xs font-mono text-gray-500">{{ p.rack_location || '—' }}</td>
              <td class="table-td">
                <span :class="p.stock_quantity <= p.min_stock_level ? 'badge bg-red-100 text-red-700' : 'badge bg-green-100 text-green-700'">
                  {{ p.stock_quantity }}
                </span>
              </td>
              <td class="table-td">
                <div class="font-semibold text-blue-700">{{ Number(p.selling_price).toLocaleString() }}</div>
                <div class="text-[10px] text-gray-400 mt-0.5">Buy: {{ Number(p.purchase_price).toLocaleString() }}</div>
              </td>
              <td class="table-td">
                <span :class="p.is_active ? 'badge bg-green-100 text-green-700' : 'badge bg-gray-100 text-gray-500'">
                  {{ p.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="table-td">
                <div class="flex items-center gap-2">
                  <div class="inline-flex items-center rounded-md overflow-hidden border border-emerald-200">
                    <input type="number" min="1" max="100"
                      :value="printQty[p.id] ?? 1"
                      @change="printQty[p.id] = Math.max(1, Math.min(100, Number($event.target.value)))"
                      class="w-10 text-center text-xs py-1 border-none outline-none bg-white text-gray-700 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                    <button @click="reprintBarcode(p)" :disabled="printingId === p.id"
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium bg-emerald-100 text-emerald-700 hover:bg-emerald-200 whitespace-nowrap disabled:opacity-60">
                      <svg v-if="printingId === p.id" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                      </svg>
                      <PrinterIcon v-else class="w-3.5 h-3.5" />
                      {{ printingId === p.id ? 'Printing…' : 'Print' }}
                    </button>
                  </div>
                  <button @click="openEdit(p)" title="Edit" class="inline-flex items-center justify-center p-1.5 rounded-md bg-blue-100 text-blue-700 hover:bg-blue-200">
                    <PencilSquareIcon class="w-4 h-4" />
                  </button>
                  <button @click="deleteProduct(p)" title="Delete" class="inline-flex items-center justify-center p-1.5 rounded-md bg-red-100 text-red-700 hover:bg-red-200">
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && !products.data?.length">
              <td colspan="10" class="table-td text-center text-gray-400 py-8">No parts found</td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="px-4 py-3 border-t border-gray-200 flex items-center justify-between text-sm text-gray-600">
        <span class="text-xs text-gray-400">{{ products.from }}–{{ products.to }} of {{ products.total }}</span>
        <div class="flex items-center gap-1">
          <button @click="goPage(1)" :disabled="page <= 1"
            class="px-2 py-1 rounded text-xs border border-gray-200 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">«</button>
          <button @click="goPage(page - 1)" :disabled="page <= 1"
            class="px-2 py-1 rounded text-xs border border-gray-200 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">‹</button>
          <template v-for="pg in pageNumbers" :key="pg">
            <span v-if="pg === '...'" class="px-1 text-gray-400 text-xs select-none">…</span>
            <button v-else @click="goPage(pg)"
              :class="pg === page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-200 hover:bg-gray-100 text-gray-700'"
              class="min-w-[28px] px-2 py-1 rounded text-xs border">{{ pg }}</button>
          </template>
          <button @click="goPage(page + 1)" :disabled="page >= products.last_page"
            class="px-2 py-1 rounded text-xs border border-gray-200 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">›</button>
          <button @click="goPage(products.last_page)" :disabled="page >= products.last_page"
            class="px-2 py-1 rounded text-xs border border-gray-200 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">»</button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <ProductModal
      v-if="showModal"
      :product="editing"
      :suppliers="suppliers"
      :vehicle-types="vehicleTypes"
      :brands="brands"
      :vehicle-models="vehicleModels"
      :part-categories="partCategories"
      :part-brands="partBrands"
      :quality-types="qualityTypes"
      @close="showModal = false"
      @saved="onSaved"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { PencilSquareIcon, PlusIcon, PrinterIcon, TrashIcon } from '@heroicons/vue/24/outline'
import JsBarcode from 'jsbarcode'
import ProductModal from '@/components/ProductModal.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'

const STORAGE_KEY = 'products_filters'

function loadFilters() {
  try { return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '{}') } catch { return {} }
}
function saveFilters() {
  sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
    search: search.value,
    partCategoryFilter: partCategoryFilter.value,
    vehicleTypeFilter: vehicleTypeFilter.value,
    brandFilter: brandFilter.value,
    modelFilter: modelFilter.value,
    lowStockOnly: lowStockOnly.value,
  }))
}

const saved = loadFilters()

const products         = ref({ data: [] })
const loading          = ref(false)
const suppliers        = ref([])
const vehicleTypes     = ref([])
const brands           = ref([])
const vehicleModels    = ref([])
const partCategories   = ref([])
const partBrands       = ref([])
const qualityTypes     = ref([])
const search             = ref(saved.search            ?? '')
const partCategoryFilter = ref(saved.partCategoryFilter ?? '')
const vehicleTypeFilter  = ref(saved.vehicleTypeFilter  ?? '')
const brandFilter        = ref(saved.brandFilter        ?? '')
const modelFilter        = ref(saved.modelFilter        ?? '')
const lowStockOnly       = ref(saved.lowStockOnly       ?? false)
const page             = ref(1)
const showModal        = ref(false)
const printingId       = ref(null)
const printQty         = ref({})
const editing          = ref(null)

const hasActiveFilter = computed(() =>
  search.value || partCategoryFilter.value || vehicleTypeFilter.value ||
  brandFilter.value || modelFilter.value || lowStockOnly.value
)

const pageNumbers = computed(() => {
  const last = products.value.last_page || 1
  const cur  = page.value
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)
  const pages = []
  pages.push(1)
  if (cur > 3) pages.push('...')
  for (let i = Math.max(2, cur - 1); i <= Math.min(last - 1, cur + 1); i++) pages.push(i)
  if (cur < last - 2) pages.push('...')
  pages.push(last)
  return pages
})

function goPage(p) {
  page.value = p
  fetchProducts()
}

function clearFilters() {
  search.value = ''; partCategoryFilter.value = ''; vehicleTypeFilter.value = ''
  brandFilter.value = ''; modelFilter.value = ''; lowStockOnly.value = false
  page.value = 1; saveFilters(); fetchProducts()
}

function onFilterChange() {
  page.value = 1; saveFilters(); fetchProducts()
}

let debounceTimer = null
function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; saveFilters(); fetchProducts() }, 400)
}

async function fetchProducts() {
  loading.value = true
  const params = {
    page: page.value,
    search: search.value,
    part_category_id: partCategoryFilter.value,
    vehicle_type_id:  vehicleTypeFilter.value,
    brand_id:         brandFilter.value,
    model_id:         modelFilter.value,
  }
  if (lowStockOnly.value) params.low_stock = 1
  const { data } = await axios.get('/api/products', { params })
  products.value = data
  loading.value = false
}

async function fetchRefs() {
  const [s, vt, b, vm, pc, pb, qt] = await Promise.all([
    axios.get('/api/suppliers/all'),
    axios.get('/api/vehicle-types'),
    axios.get('/api/brands'),
    axios.get('/api/vehicle-models'),
    axios.get('/api/part-categories'),
    axios.get('/api/part-brands'),
    axios.get('/api/quality-types'),
  ])
  suppliers.value      = s.data
  vehicleTypes.value   = vt.data
  brands.value         = b.data
  vehicleModels.value  = vm.data
  partCategories.value = pc.data
  partBrands.value     = pb.data
  qualityTypes.value   = qt.data
}

function openCreate() { editing.value = null; showModal.value = true }
function openEdit(p)   { editing.value = p;    showModal.value = true }

function createBarcodeSvg(value) {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
  JsBarcode(svg, value, {
    format: 'CODE128',
    width: 2,
    height: 60,
    margin: 0,
    marginLeft: 3,
    marginRight: 3,
    displayValue: false,
  })
  svg.setAttribute('preserveAspectRatio', 'none')
  return svg.outerHTML
}

function printProductBarcode(product, qty = 1) {
  if (!product?.sku) return
  const barcodeValue = product.barcode?.trim() || product.sku
  const barcodeSvg   = createBarcodeSvg(barcodeValue)
  const safeName     = (product.name ?? '').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  const safeBarcode  = barcodeValue.replace(/</g, '&lt;').replace(/>/g, '&gt;')
  const safeVehicleType = (product.vehicle_type?.name ?? '').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  const safeModel       = (product.model?.name ?? '').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  const brandModel      = [safeVehicleType, safeModel].filter(Boolean).join(' · ')
  const safePrice    = Number(product.selling_price).toLocaleString('en-LK', { minimumFractionDigits: 2 })

  const labelHtml = `
  <div class="label">
    <div class="name">${safeName}</div>
    ${brandModel ? `<div class="brand-model">${brandModel}</div>` : ''}
    ${barcodeSvg}
    <div class="sku">${safeBarcode}</div>
    <div class="price">LKR ${safePrice}</div>
  </div>`

  const html = `<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @media print { @page { size: 1.181in 0.787in landscape; margin: 0; } }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { width: 30mm; background: #fff;
      font-family: Arial, Helvetica, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .label { width: 30mm; height: 20mm; padding: 2mm 1.5mm 0.5mm;
      display: flex; flex-direction: column; align-items: center; justify-content: space-between; overflow: hidden;
      page-break-after: always; }
    .name { font-size: 6.5pt; font-weight: 700; width: 100%; text-align: center; line-height: 1.2; flex-shrink: 0; word-break: break-word; }
    .brand-model { font-size: 5.5pt; color: #555; white-space: nowrap; overflow: hidden;
      text-overflow: ellipsis; width: 100%; text-align: center; line-height: 1.2; flex-shrink: 0; }
    svg { width: 80%; height: 7mm; display: block; flex-shrink: 0; margin: 0 auto; }
    .sku { font-size: 7pt; font-weight: 700; letter-spacing: 1px; text-align: center; margin-top: 0.3mm; line-height: 1; }
    .price { font-size: 7pt; font-weight: 700; text-align: center; line-height: 1; margin-top: 0.5mm; }
  </style>
</head>
<body>
  ${Array(qty).fill(labelHtml).join('')}
</body>
</html>`

  if (window.electronAPI?.printBarcode) {
    window.electronAPI.printBarcode(html)
    return
  }

  const iframe = document.createElement('iframe')
  iframe.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;border:none;'
  document.body.appendChild(iframe)
  iframe.contentDocument.open()
  iframe.contentDocument.write(html)
  iframe.contentDocument.close()
  iframe.contentWindow.addEventListener('load', () => {
    iframe.contentWindow.print()
    setTimeout(() => document.body.removeChild(iframe), 2000)
  })
}

function reprintBarcode(product) {
  printingId.value = product.id
  setTimeout(() => { printingId.value = null }, 3000)
  printProductBarcode(product, printQty.value[product.id] ?? 1)
}

async function deleteProduct(p) {
  if (!confirm(`Delete "${p.name}"?`)) return
  await axios.delete(`/api/products/${p.id}`)
  fetchProducts()
}

async function onSaved(payload) {
  showModal.value = false
  await fetchProducts()
  if (payload?.isNew && payload?.product) {
    printProductBarcode(payload.product)
  }
}

onMounted(() => { fetchProducts(); fetchRefs() })
</script>
