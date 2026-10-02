<template>
  <div v-if="card" class="-m-6 flex flex-col" style="height: calc(100vh - 57px);">

    <!-- ── Top bar ── -->
    <div class="flex items-center justify-between px-6 py-3 bg-white border-b border-gray-200 shrink-0">
      <div class="flex items-center gap-3">
        <router-link to="/job-cards"
          class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 transition-colors">
          <ArrowLeftIcon class="w-4 h-4" /> Job Cards
        </router-link>
        <span class="text-gray-300">/</span>
        <span class="font-mono font-bold text-blue-700 text-sm">{{ card.card_number }}</span>
        <span :class="statusClass(card.status)" class="badge capitalize text-xs px-2.5 py-1">
          {{ statusLabel(card.status) }}
        </span>
      </div>
      <div class="flex items-center gap-2">
        <!-- Status selector -->
        <select v-model="statusForm" class="form-input text-sm py-1.5 w-40"
          @change="updateStatus" :disabled="['completed','delivered','cancelled'].includes(card.status)">
          <option value="received">Received</option>
          <option value="in_progress">In Progress</option>
          <option value="delivered">Delivered</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <!-- Complete button -->
        <button v-if="!['completed','delivered','cancelled'].includes(card.status)"
          @click="completeJob" :disabled="completing"
          class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white rounded-lg text-sm font-bold transition-colors">
          <CheckCircleIcon class="w-4 h-4" />
          {{ completing ? 'Sending…' : 'Complete & Send SMS' }}
        </button>
        <button v-if="card.public_token" @click="showReceipt = true"
          class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-bold shadow-sm transition-colors">
          <PrinterIcon class="w-4 h-4" /> Print Bill
        </button>
        <button @click="showEdit = true"
          class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-200 text-gray-600 hover:bg-gray-50 rounded-lg text-sm font-medium transition-colors">
          <PencilSquareIcon class="w-4 h-4" /> Edit
        </button>
        <button @click="deleteCard"
          class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
          <TrashIcon class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- ── Body ── -->
    <div class="flex flex-1 min-h-0">

      <!-- LEFT: Items workspace -->
      <div class="flex-1 flex flex-col min-w-0 bg-gray-50">

        <!-- Add item form -->
        <div v-if="!['completed','delivered','cancelled'].includes(card.status)"
          class="bg-white border-b border-gray-200 px-6 py-4 shrink-0">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
            <PlusCircleIcon class="w-3.5 h-3.5" /> Add Item
          </p>
          <div class="flex gap-3 items-end flex-wrap">
            <!-- Type -->
            <div class="w-32 shrink-0">
              <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Type</label>
              <select v-model="itemForm.type" class="form-input text-sm" @change="onTypeChange">
                <option value="part">Part</option>
                <option value="labour">Labour</option>
                <option value="other">Other</option>
              </select>
            </div>

            <!-- Description / Product search -->
            <div class="relative flex-1 min-w-48">
              <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Description</label>
              <input v-if="itemForm.type !== 'part'" v-model="itemForm.description"
                class="form-input text-sm" placeholder="e.g. Engine oil change" />
              <div v-else class="relative">
                <input v-model="productSearch"
                  class="form-input text-sm" placeholder="Search part name or SKU…"
                  @input="itemForm.product_id = null; itemForm.description = productSearch"
                  @focus="showProductDd = true"
                  @keydown.esc="showProductDd = false" />
                <div v-if="showProductDd && productResults.length"
                  class="absolute left-0 right-0 top-full z-50 mt-1 max-h-56 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl">
                  <button v-for="p in productResults" :key="p.id" type="button"
                    class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-blue-50 border-b border-gray-100 last:border-b-0"
                    @mousedown.prevent="selectProduct(p)">
                    <div class="min-w-0 flex-1">
                      <p class="text-sm font-semibold text-gray-800 truncate">{{ p.name }}</p>
                      <p class="text-xs text-gray-400">SKU: {{ p.sku }}</p>
                    </div>
                    <div class="text-right text-xs shrink-0">
                      <p class="font-bold text-blue-700">LKR {{ lkr(p.selling_price) }}</p>
                      <p class="text-gray-400">Stock: {{ p.stock_quantity }}</p>
                    </div>
                  </button>
                </div>
              </div>
            </div>

            <!-- Qty -->
            <div class="w-20 shrink-0">
              <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Qty</label>
              <input v-model.number="itemForm.quantity" type="number" min="1" step="1"
                @input="itemForm.quantity = Math.floor(itemForm.quantity)" @focus="$event.target.select()"
                class="form-input text-sm text-center" />
            </div>
            <!-- Price -->
            <div class="w-28 shrink-0">
              <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Unit Price</label>
              <input v-model.number="itemForm.unit_price" type="number" min="0" step="1"
                @input="itemForm.unit_price = Math.floor(itemForm.unit_price)" @focus="$event.target.select()"
                class="form-input text-sm" />
            </div>
            <!-- Discount -->
            <div class="w-24 shrink-0">
              <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Discount</label>
              <input v-model.number="itemForm.discount" type="number" min="0" step="1"
                @input="itemForm.discount = Math.floor(itemForm.discount)" @focus="$event.target.select()"
                class="form-input text-sm" />
            </div>
            <!-- Total preview -->
            <div class="w-28 shrink-0">
              <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Line Total</label>
              <div class="form-input text-sm font-bold text-blue-700 bg-blue-50 border-blue-200">
                LKR {{ lkr(itemLineTotal) }}
              </div>
            </div>
            <!-- Add button -->
            <button @click="addItem" :disabled="addingItem"
              class="inline-flex items-center gap-1.5 px-5 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-lg text-sm font-bold transition-colors shrink-0">
              <ArrowPathIcon v-if="addingItem" class="w-4 h-4 animate-spin" />
              <PlusIcon v-else class="w-4 h-4" />
              Add
            </button>
          </div>
          <p v-if="itemError" class="mt-2 text-xs text-red-600 bg-red-50 px-3 py-1.5 rounded-lg">{{ itemError }}</p>
        </div>

        <!-- Items list -->
        <!-- Scrollable items -->
        <div class="flex-1 overflow-y-auto">
          <!-- Empty -->
          <div v-if="!card.items?.length"
            class="flex flex-col items-center justify-center h-full text-gray-400">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mb-3">
              <ClipboardDocumentListIcon class="w-8 h-8 text-gray-300" />
            </div>
            <p class="font-medium text-gray-500 mb-1">No items yet</p>
            <p class="text-sm">Add parts or labour charges above</p>
          </div>

          <!-- Table -->
          <div v-else>
            <div class="sticky top-0 flex items-center gap-2 px-6 py-2.5 bg-gray-100 border-b border-gray-200 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
              <span class="w-20 shrink-0">Type</span>
              <span class="flex-1">Description</span>
              <span class="w-16 shrink-0 text-right">Qty</span>
              <span class="w-28 shrink-0 text-right">Unit Price</span>
              <span class="w-24 shrink-0 text-right">Discount</span>
              <span class="w-28 shrink-0 text-right">Total</span>
              <span class="w-8 shrink-0"></span>
            </div>

            <div v-for="item in card.items" :key="item.id"
              class="flex items-center gap-2 px-6 py-4 bg-white border-b border-gray-100 hover:bg-blue-50/30 transition-colors">
              <span class="w-20 shrink-0">
                <span :class="{
                  'bg-blue-100 text-blue-700': item.type === 'part',
                  'bg-green-100 text-green-700': item.type === 'labour',
                  'bg-gray-100 text-gray-600': item.type === 'other',
                }" class="badge capitalize text-xs">{{ item.type }}</span>
              </span>
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-800 text-sm truncate">{{ item.description }}</p>
                <p v-if="item.product" class="text-xs text-gray-400">SKU: {{ item.product.sku }}</p>
              </div>
              <span class="w-16 shrink-0 text-right text-sm text-gray-600">{{ item.quantity }}</span>
              <span class="w-28 shrink-0 text-right text-sm text-gray-600">{{ lkr(item.unit_price) }}</span>
              <span class="w-24 shrink-0 text-right text-sm text-red-400">
                {{ item.discount > 0 ? `−${lkr(item.discount)}` : '—' }}
              </span>
              <span class="w-28 shrink-0 text-right font-bold text-blue-700 text-sm">LKR {{ lkr(item.total) }}</span>
              <div class="w-8 shrink-0 flex justify-end">
                <button v-if="!['completed','delivered','cancelled'].includes(card.status)"
                  @click="removeItem(item)"
                  class="p-1.5 rounded-lg text-gray-300 hover:bg-red-50 hover:text-red-500 transition-colors">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Pinned total bar -->
        <div class="shrink-0 text-white">
          <!-- Bill discount row -->
          <div v-if="card.items?.length && !['completed','delivered','cancelled'].includes(card.status)"
            class="flex items-center gap-3 px-6 py-3 bg-indigo-950">
            <span class="text-sm text-indigo-300 ml-auto mr-2">Subtotal</span>
            <span class="text-sm text-white w-32 text-right font-medium">LKR {{ lkr(card.total) }}</span>
            <span class="text-sm text-indigo-300 ml-6 mr-2">Bill Discount</span>
            <div class="flex items-center gap-1">
              <span class="text-indigo-400 text-sm">LKR</span>
              <input v-model.number="billDiscountInput" type="number" min="0" step="1"
                @change="saveBillDiscount" @focus="$event.target.select()"
                class="w-28 bg-black border border-gray-700 text-white text-sm rounded-lg px-2 py-1 text-right focus:outline-none focus:border-amber-400" />
            </div>
          </div>
          <!-- Grand total -->
          <div class="flex items-center gap-2 px-6 py-4 bg-indigo-900">
            <div class="flex-1"></div>
            <div v-if="card.bill_discount > 0" class="flex items-center gap-3 mr-4 text-sm text-indigo-300">
              <span>Discount</span>
              <span class="text-red-300 font-medium">−LKR {{ lkr(card.bill_discount) }}</span>
            </div>
            <span class="text-sm text-indigo-300 mr-4">Grand Total</span>
            <span class="text-xl font-black text-white">LKR {{ lkr(netTotal) }}</span>
            <div class="w-8"></div>
          </div>
        </div>
      </div>

      <!-- RIGHT: Info panel -->
      <div class="w-72 xl:w-80 shrink-0 bg-white border-l border-gray-200 flex flex-col overflow-y-auto">

        <!-- Customer -->
        <div class="px-5 py-4 border-b border-gray-100">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
            <UserIcon class="w-3.5 h-3.5" /> Customer
          </p>
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold shrink-0">
              {{ (card.customer_name || card.customer?.name || '?')[0].toUpperCase() }}
            </div>
            <div class="min-w-0">
              <p class="font-bold text-gray-900 truncate">{{ card.customer_name || card.customer?.name || '—' }}</p>
              <p class="text-sm text-gray-400">{{ card.customer_phone || card.customer?.phone || 'No phone' }}</p>
            </div>
          </div>
        </div>

        <!-- Vehicle -->
        <div class="px-5 py-4 border-b border-gray-100">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
            <TruckIcon class="w-3.5 h-3.5" /> Vehicle
          </p>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-400">Plate</span>
              <span class="font-mono font-bold text-gray-900">{{ card.vehicle_number || '—' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Make / Model</span>
              <span class="font-medium text-gray-800 text-right">{{ [card.vehicle_make, card.vehicle_model].filter(Boolean).join(' ') || '—' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Mileage</span>
              <span class="font-medium text-gray-800">{{ card.mileage ? `${Number(card.mileage).toLocaleString()} km` : '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Service -->
        <div class="px-5 py-4 border-b border-gray-100">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
            <WrenchScrewdriverIcon class="w-3.5 h-3.5" /> Service
          </p>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-400">Technician</span>
              <span class="font-medium text-gray-800">{{ card.assigned_technician || '—' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Est. Completion</span>
              <span class="font-medium text-gray-800">{{ card.estimated_completion ? fmtDate(card.estimated_completion) : '—' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Created By</span>
              <span class="font-medium text-gray-800">{{ card.created_by?.name || '—' }}</span>
            </div>
            <div v-if="card.completed_at" class="flex justify-between">
              <span class="text-gray-400">Completed</span>
              <span class="font-medium text-green-700">{{ fmtDate(card.completed_at) }}</span>
            </div>
          </div>
        </div>

        <!-- Complaint -->
        <div v-if="card.complaint" class="px-5 py-4 border-b border-gray-100">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Complaint</p>
          <div class="bg-amber-50 border border-amber-200 rounded-xl p-3">
            <p class="text-sm text-amber-900 leading-relaxed">{{ card.complaint }}</p>
          </div>
        </div>

        <!-- Notes -->
        <div v-if="card.notes" class="px-5 py-4 border-b border-gray-100">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Internal Notes</p>
          <div class="bg-gray-50 border border-gray-200 rounded-xl p-3">
            <p class="text-sm text-gray-700 leading-relaxed">{{ card.notes }}</p>
          </div>
        </div>

        <!-- Tracking link -->
        <div v-if="card.public_token" class="px-5 py-4 border-b border-gray-100">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
            <LinkIcon class="w-3.5 h-3.5" /> Customer Links
          </p>
          <div class="space-y-2">
            <a :href="`/job/${card.public_token}`" target="_blank"
              class="flex items-center gap-2 text-xs text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 rounded-lg px-3 py-2 transition-colors">
              <EyeIcon class="w-3.5 h-3.5 shrink-0" />
              <span class="truncate">Track Status Page</span>
              <ArrowTopRightOnSquareIcon class="w-3 h-3 shrink-0 ml-auto" />
            </a>
            <a :href="`/job/${card.public_token}/invoice`" target="_blank"
              class="flex items-center gap-2 text-xs text-gray-600 hover:text-gray-800 bg-gray-50 hover:bg-gray-100 rounded-lg px-3 py-2 transition-colors">
              <DocumentTextIcon class="w-3.5 h-3.5 shrink-0" />
              <span class="truncate">Print Invoice</span>
              <ArrowTopRightOnSquareIcon class="w-3 h-3 shrink-0 ml-auto" />
            </a>
            <button @click="copyLink"
              class="w-full flex items-center gap-2 text-xs text-gray-500 hover:text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-lg px-3 py-2 transition-colors text-left">
              <ClipboardDocumentIcon class="w-3.5 h-3.5 shrink-0" />
              {{ copied ? 'Link copied!' : 'Copy tracking link' }}
            </button>
          </div>
        </div>

        <!-- Complete CTA -->
        <div class="px-5 py-4 mt-auto">
          <div v-if="!['completed','delivered','cancelled'].includes(card.status)"
            class="bg-green-600 rounded-2xl p-4 text-white">
            <p class="font-bold mb-1">Ready to complete?</p>
            <p class="text-xs text-green-100 mb-3">SMS with invoice link will be sent to customer.</p>
            <button @click="completeJob" :disabled="completing"
              class="w-full flex items-center justify-center gap-2 py-2.5 bg-white text-green-700 hover:bg-green-50 rounded-xl font-bold text-sm transition-colors disabled:opacity-50">
              <CheckCircleIcon class="w-4 h-4" />
              {{ completing ? 'Completing…' : 'Complete & Send SMS' }}
            </button>
          </div>
          <div v-else-if="card.status === 'completed'"
            class="bg-green-50 border border-green-200 rounded-2xl p-4 flex items-center gap-3">
            <CheckCircleIcon class="w-8 h-8 text-green-500 shrink-0" />
            <div>
              <p class="font-bold text-green-800">Completed</p>
              <p class="text-xs text-green-600">SMS sent to customer</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Receipt print modal -->
  <JobCardReceiptModal v-if="showReceipt && card?.public_token"
    :token="card.public_token" @close="showReceipt = false" />

  <!-- Loading -->
  <div v-if="!card" class="flex items-center justify-center py-24 text-gray-400">
    <ArrowPathIcon class="w-6 h-6 animate-spin mr-2" /> Loading…
  </div>

  <!-- ── Edit Modal ── -->
  <div v-if="showEdit" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col">
      <div class="flex items-center justify-between px-7 py-5 border-b shrink-0">
        <h3 class="text-lg font-bold text-gray-900">Edit Job Card</h3>
        <button @click="showEdit = false" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 transition-colors">
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>
      <div class="overflow-y-auto flex-1 px-7 py-5">
        <div class="grid grid-cols-2 gap-4">
          <div><label class="form-label">Customer Name</label>
            <input v-model="editForm.customer_name" class="form-input" /></div>
          <div><label class="form-label">Phone</label>
            <input v-model="editForm.customer_phone" class="form-input" /></div>
          <div><label class="form-label">Vehicle Number</label>
            <input v-model="editForm.vehicle_number" class="form-input font-mono uppercase" /></div>
          <div><label class="form-label">Mileage (km)</label>
            <input v-model.number="editForm.mileage" type="number" class="form-input" /></div>
          <div><label class="form-label">Make</label>
            <input v-model="editForm.vehicle_make" class="form-input" /></div>
          <div><label class="form-label">Model</label>
            <input v-model="editForm.vehicle_model" class="form-input" /></div>
          <div><label class="form-label">Technician</label>
            <select v-model="editForm.assigned_technician" class="form-input">
              <option value="">— Unassigned —</option>
              <option v-for="emp in employees" :key="emp.id" :value="emp.name">
                {{ emp.name }}<template v-if="emp.designation"> — {{ emp.designation }}</template>
              </option>
            </select>
          </div>
          <div><label class="form-label">Est. Completion</label>
            <input v-model="editForm.estimated_completion" type="date" class="form-input" /></div>
          <div class="col-span-2"><label class="form-label">Complaint</label>
            <textarea v-model="editForm.complaint" rows="3" class="form-input resize-none"></textarea></div>
          <div class="col-span-2"><label class="form-label">Internal Notes</label>
            <textarea v-model="editForm.notes" rows="2" class="form-input resize-none"></textarea></div>
        </div>
        <p v-if="editError" class="mt-3 text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ editError }}</p>
      </div>
      <div class="px-7 py-5 border-t shrink-0 flex gap-3">
        <button @click="showEdit = false" class="btn-secondary flex-1">Cancel</button>
        <button @click="saveEdit" :disabled="savingEdit" class="btn-primary flex-1 flex items-center justify-center gap-2">
          <ArrowPathIcon v-if="savingEdit" class="w-4 h-4 animate-spin" />
          {{ savingEdit ? 'Saving…' : 'Save Changes' }}
        </button>
      </div>
    </div>
  </div>

  <!-- ── Confirm Modal ── -->
  <div v-if="confirmDialog.show"
    class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
      <div class="flex items-start gap-4 mb-5">
        <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
          :class="confirmDialog.danger ? 'bg-red-100' : 'bg-amber-100'">
          <component :is="confirmDialog.danger ? TrashIcon : CheckCircleIcon"
            class="w-5 h-5" :class="confirmDialog.danger ? 'text-red-600' : 'text-amber-600'" />
        </div>
        <div>
          <p class="font-bold text-gray-900">{{ confirmDialog.title }}</p>
          <p class="text-sm text-gray-500 mt-1">{{ confirmDialog.message }}</p>
        </div>
      </div>
      <div class="flex gap-3">
        <button @click="confirmDialog.show = false"
          class="flex-1 px-4 py-2 border border-gray-200 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-50 transition-colors">
          Cancel
        </button>
        <button @click="confirmDialog.onConfirm(); confirmDialog.show = false"
          class="flex-1 px-4 py-2 rounded-xl text-sm font-bold text-white transition-colors"
          :class="confirmDialog.danger ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'">
          {{ confirmDialog.confirmLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import {
  ArrowLeftIcon, PlusIcon, TrashIcon, CheckCircleIcon,
  ArrowPathIcon, UserIcon, WrenchScrewdriverIcon, TruckIcon,
  PlusCircleIcon, ClipboardDocumentListIcon, PencilSquareIcon, XMarkIcon,
  LinkIcon, EyeIcon, ArrowTopRightOnSquareIcon, DocumentTextIcon, ClipboardDocumentIcon,
  PrinterIcon,
} from '@heroicons/vue/24/outline'
import JobCardReceiptModal from '@/components/JobCardReceiptModal.vue'

const route      = useRoute()
const router     = useRouter()
const card             = ref(null)
const completing       = ref(false)
const copied           = ref(false)
const showReceipt      = ref(false)
const billDiscountInput = ref(0)

const netTotal = computed(() =>
  Math.max(0, (card.value?.total ?? 0) - (card.value?.bill_discount ?? 0))
)

const confirmDialog = reactive({
  show: false, danger: false, title: '', message: '', confirmLabel: 'Confirm',
  onConfirm: () => {},
})

function askConfirm({ title, message, confirmLabel = 'Confirm', danger = false, onConfirm }) {
  Object.assign(confirmDialog, { show: true, title, message, confirmLabel, danger, onConfirm })
}
const addingItem = ref(false)
const itemError  = ref('')
const statusForm = ref('')
const showEdit   = ref(false)
const savingEdit = ref(false)
const editError  = ref('')

const editForm = reactive({
  customer_name: '', customer_phone: '', vehicle_number: '',
  vehicle_make: '', vehicle_model: '', mileage: '',
  assigned_technician: '', estimated_completion: '',
  complaint: '', notes: '',
})

// Product search
const products      = ref([])
const employees     = ref([])
const productSearch = ref('')
const showProductDd = ref(false)
const productResults = computed(() => {
  const q = productSearch.value.trim().toLowerCase()
  if (!q) return []
  return products.value.filter(p =>
    [p.name, p.sku, p.barcode].some(f => f?.toLowerCase().includes(q))
  ).slice(0, 10)
})

const itemForm = reactive({
  type: 'part', description: '', product_id: null,
  quantity: 1, unit_price: 0, discount: 0,
})

const itemLineTotal = computed(() =>
  Math.max(0, (itemForm.quantity * itemForm.unit_price) - (itemForm.discount || 0))
)

function onTypeChange() {
  itemForm.description = ''
  itemForm.product_id  = null
  productSearch.value  = ''
}

function selectProduct(p) {
  itemForm.product_id  = p.id
  itemForm.description = p.name
  itemForm.unit_price  = p.selling_price
  productSearch.value  = p.name
  showProductDd.value  = false
}

async function load() {
  const { data } = await axios.get(`/api/job-cards/${route.params.id}`)
  card.value           = data
  statusForm.value     = data.status
  billDiscountInput.value = data.bill_discount ?? 0
}

async function saveBillDiscount() {
  const discount = Math.max(0, Math.floor(billDiscountInput.value || 0))
  billDiscountInput.value = discount
  await axios.put(`/api/job-cards/${card.value.id}`, { bill_discount: discount })
  await load()
}

async function addItem() {
  if (!itemForm.description?.trim()) { itemError.value = 'Description is required'; return }
  addingItem.value = true; itemError.value = ''
  try {
    await axios.post(`/api/job-cards/${card.value.id}/items`, itemForm)
    await load()
    Object.assign(itemForm, { type: 'part', description: '', product_id: null, quantity: 1, unit_price: 0, discount: 0 })
    productSearch.value = ''
  } catch (e) {
    itemError.value = e.response?.data?.message ?? 'Failed to add item'
  } finally { addingItem.value = false }
}

function removeItem(item) {
  askConfirm({
    title: 'Remove Item',
    message: `Remove "${item.description}" from this job card?`,
    confirmLabel: 'Remove', danger: true,
    onConfirm: async () => {
      await axios.delete(`/api/job-cards/${card.value.id}/items/${item.id}`)
      await load()
    },
  })
}

async function updateStatus() {
  await axios.put(`/api/job-cards/${card.value.id}`, { status: statusForm.value })
  await load()
}

async function completeJob() {
  askConfirm({
    title: 'Complete Job Card',
    message: 'Mark this job as complete? An SMS with the invoice link will be sent to the customer.',
    confirmLabel: 'Complete & Send SMS', danger: false,
    onConfirm: async () => {
      completing.value = true
      try {
        await axios.post(`/api/job-cards/${card.value.id}/complete`)
        await load()
      } finally { completing.value = false }
    },
  })
}

async function saveEdit() {
  savingEdit.value = true; editError.value = ''
  try {
    await axios.put(`/api/job-cards/${card.value.id}`, editForm)
    await load()
    showEdit.value = false
  } catch (e) {
    editError.value = e.response?.data?.message ?? 'Failed to save'
  } finally { savingEdit.value = false }
}

async function copyLink() {
  const url = `${window.location.origin}/job/${card.value.public_token}`
  await navigator.clipboard.writeText(url)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2500)
}

async function deleteCard() {
  askConfirm({
    title: 'Delete Job Card',
    message: `Delete ${card.value.card_number}? This cannot be undone.`,
    confirmLabel: 'Delete', danger: true,
    onConfirm: async () => {
      await axios.delete(`/api/job-cards/${card.value.id}`)
      router.push('/job-cards')
    },
  })
}

function openEdit() {
  Object.assign(editForm, {
    customer_name: card.value.customer_name ?? '',
    customer_phone: card.value.customer_phone ?? '',
    vehicle_number: card.value.vehicle_number ?? '',
    vehicle_make: card.value.vehicle_make ?? '',
    vehicle_model: card.value.vehicle_model ?? '',
    mileage: card.value.mileage ?? '',
    assigned_technician: card.value.assigned_technician ?? '',
    estimated_completion: card.value.estimated_completion ?? '',
    complaint: card.value.complaint ?? '',
    notes: card.value.notes ?? '',
  })
  showEdit.value = true
}

// Watch showEdit to populate form
import { watch } from 'vue'
watch(showEdit, (v) => { if (v && card.value) openEdit() })

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

onMounted(async () => {
  await load()
  const [productsRes, employeesRes] = await Promise.all([
    axios.get('/api/products', { params: { per_page: 500 } }),
    axios.get('/api/employees/all').catch(() => ({ data: [] })),
  ])
  products.value  = productsRes.data.data
  employees.value = employeesRes.data
})
</script>
