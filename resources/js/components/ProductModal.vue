<template>
  <teleport to="body">
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-5xl max-h-[92vh] flex flex-col">

      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b shrink-0">
        <h3 class="text-lg font-semibold">{{ product ? 'Edit Part' : 'Add Part' }}</h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
      </div>

      <form @submit.prevent="submit" class="overflow-y-auto flex-1">
        <div class="flex min-h-0">

          <!-- LEFT: Part identification -->
          <div class="flex-1 px-6 py-5 space-y-4 border-r border-gray-100">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Part Info</p>

            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="form-label"><span class="text-red-500 mr-0.5">*</span> Part Name</label>
                <input v-model="form.name" required class="form-input" placeholder="e.g. Water Pump" />
              </div>
              <div>
                <label class="form-label">Part Number</label>
                <input v-model="form.part_number" class="form-input font-mono" placeholder="e.g. WP-1234" />
              </div>
              <div>
                <label class="form-label">Barcode <span class="text-xs font-normal text-gray-400">(optional)</span></label>
                <input v-model="form.barcode" class="form-input font-mono" placeholder="e.g. 8901234567890" />
              </div>
              <div>
                <label class="form-label">Part Category</label>
                <SearchableSelect v-model="form.part_category_id" :options="partCategories" placeholder="Search category…" />
              </div>
              <div>
                <label class="form-label">Part Brand</label>
                <SearchableSelect v-model="form.part_brand_id" :options="partBrands" placeholder="Search part brand…" />
              </div>
              <div>
                <label class="form-label">Quality Grade</label>
                <SearchableSelect v-model="form.quality_type_id" :options="qualityTypes" placeholder="Search quality grade…" />
              </div>
              <div>
                <label class="form-label">Supplier</label>
                <SearchableSelect v-model="form.supplier_id" :options="suppliers" placeholder="Search supplier…" />
              </div>
            </div>

            <div>
              <label class="form-label">Description</label>
              <textarea v-model="form.description" rows="2" class="form-input"></textarea>
            </div>

            <SmartImageUploader
              ref="productImageUploader"
              v-model="productImages"
              label="Part Image"
              :multiple="false"
              :max-items="1"
              folder="spare-parts/products"
              :tags="['product']"
            />

            <div class="flex items-center gap-2">
              <input id="active" type="checkbox" v-model="form.is_active" class="rounded text-blue-600" />
              <label for="active" class="text-sm text-gray-700">Active</label>
            </div>
          </div>

          <!-- RIGHT: Vehicle + Stock & Pricing -->
          <div class="w-80 xl:w-96 shrink-0 px-6 py-5 space-y-5">

            <div class="space-y-4">
              <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Vehicle</p>
              <div>
                <label class="form-label">Vehicle Type</label>
                <SearchableSelect v-model="form.vehicle_type_id" :options="vehicleTypes" placeholder="Search vehicle type…" @update:modelValue="onVehicleTypeChange" />
              </div>
              <div>
                <label class="form-label">Brand</label>
                <SearchableSelect v-model="form.brand_id" :options="filteredBrands" placeholder="Search brand…" @update:modelValue="onBrandChange" />
              </div>
              <div>
                <label class="form-label">Vehicle Model</label>
                <SearchableSelect v-model="form.model_id" :options="filteredModels" placeholder="Search model…" />
              </div>
            </div>

            <div class="border-t border-gray-100 pt-5 space-y-4">
              <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Stock & Pricing</p>
              <div>
                <label class="form-label">Rack / Shelf Location</label>
                <input v-model="form.rack_location" class="form-input" placeholder="e.g. A-12, Shelf 3" />
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="form-label"><span class="text-red-500 mr-0.5">*</span> Purchase Price (LKR)</label>
                  <input v-model="form.purchase_price" type="number" step="0.01" min="0" required class="form-input" />
                </div>
                <div>
                  <label class="form-label"><span class="text-red-500 mr-0.5">*</span> Selling Price (LKR)</label>
                  <input v-model="form.selling_price" type="number" step="0.01" min="0" required class="form-input" />
                </div>
                <div>
                  <label class="form-label"><span class="text-red-500 mr-0.5">*</span> Stock Quantity</label>
                  <input v-model="form.stock_quantity" type="number" min="0" required class="form-input" />
                </div>
                <div>
                  <label class="form-label">Min Stock Alert</label>
                  <input v-model="form.min_stock_level" type="number" min="0" class="form-input" />
                </div>
              </div>
            </div>

          </div>
        </div>

        <p v-if="error" class="mx-6 mb-4 text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ error }}</p>
      </form>

      <div class="flex justify-end gap-3 px-6 py-4 border-t shrink-0">
        <button type="button" @click="$emit('close')" class="btn-secondary">Cancel</button>
        <button @click="submit" :disabled="saving" class="btn-primary">{{ saving ? 'Saving…' : 'Save' }}</button>
      </div>
    </div>
  </div>
  </teleport>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue'
import axios from 'axios'
import SmartImageUploader from '@/components/SmartImageUploader.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'

const props = defineProps({
  product:       Object,
  suppliers:     { type: Array, default: () => [] },
  vehicleTypes:  { type: Array, default: () => [] },
  brands:        { type: Array, default: () => [] },
  vehicleModels: { type: Array, default: () => [] },
  partCategories:{ type: Array, default: () => [] },
  partBrands:    { type: Array, default: () => [] },
  qualityTypes:  { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'saved'])

const form = reactive({
  name: '', part_number: '', description: '', barcode: '',
  part_category_id: '', part_brand_id: '', quality_type_id: '',
  vehicle_type_id: '', brand_id: '', model_id: '',
  supplier_id: '', rack_location: '',
  purchase_price: '', selling_price: '',
  stock_quantity: 0, min_stock_level: 5,
  is_active: true,
})

const saving = ref(false)
const error  = ref('')
const productImages = ref([])
const productImageUploader = ref(null)

const filteredBrands = computed(() => {
  if (!form.vehicle_type_id) return props.brands
  return props.brands.filter(b =>
    b.models?.some(m => m.vehicle_type_id == form.vehicle_type_id)
  )
})

const filteredModels = computed(() => {
  return props.vehicleModels.filter(m => {
    if (form.brand_id && m.brand_id != form.brand_id) return false
    if (form.vehicle_type_id && m.vehicle_type_id != form.vehicle_type_id) return false
    return true
  })
})

function onVehicleTypeChange() {
  form.brand_id = ''
  form.model_id = ''
}

function onBrandChange() {
  form.model_id = ''
}

onMounted(() => {
  if (props.product) {
    Object.assign(form, props.product)
    productImages.value = props.product.image
      ? [{ url: props.product.image, public_id: props.product.image_public_id || null }]
      : []
  } else {
    productImages.value = []
  }
})

async function submit() {
  saving.value = true
  error.value  = ''
  try {
    if (productImageUploader.value) {
      await productImageUploader.value.uploadPending()
    }

    const imageMeta = productImages.value[0] || null
    form.image_url       = imageMeta?.url || null
    form.image_public_id = imageMeta?.public_id || null

    let savedProduct
    if (props.product) {
      const { data } = await axios.put(`/api/products/${props.product.id}`, form)
      savedProduct = data
    } else {
      const { data } = await axios.post('/api/products', form)
      savedProduct = data
    }
    emit('saved', { product: savedProduct, isNew: !props.product })
  } catch (e) {
    const errs = e.response?.data?.errors
    error.value = errs ? Object.values(errs).flat().join(', ') : (e.response?.data?.message ?? 'Error saving')
  } finally {
    saving.value = false
  }
}
</script>
