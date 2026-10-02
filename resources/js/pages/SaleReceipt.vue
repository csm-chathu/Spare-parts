<template>
  <div>

    <!-- ── Screen toolbar (no-print) ── -->
    <div class="no-print flex flex-wrap items-center justify-between gap-3 mb-6">
      <div class="flex items-center gap-3">
        <router-link to="/sales"
          class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 transition-colors">
          <ArrowLeftIcon class="w-4 h-4" /> Back to Sales
        </router-link>
        <span class="text-gray-300">/</span>
        <span class="text-sm font-medium text-gray-700">{{ sale?.invoice_number }}</span>
        <span v-if="sale?.is_draft" class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800 border border-yellow-300 uppercase tracking-wide">
          Draft
        </span>
        <span v-else-if="sale?.sale_type === 'booking'" class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full"
          :class="sale.delivery_status === 'booked' ? 'bg-purple-100 text-purple-700' : 'bg-green-100 text-green-700'">
          {{ sale.delivery_status === 'booked' ? 'Booking — Pending Collection' : 'Booking — Collected' }}
        </span>
      </div>

      <!-- Draft actions -->
      <div v-if="sale?.is_draft" class="flex flex-wrap gap-2 items-center">
        <router-link :to="`/sales/${sale.id}/edit`"
          class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg font-medium text-sm shadow-sm transition-colors">
          <PencilSquareIcon class="w-4 h-4" /> Edit Draft
        </router-link>
        <button @click="finalizeDraft"
          :disabled="finalizing"
          class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white rounded-lg font-medium text-sm shadow-sm transition-colors">
          <ArrowPathIcon v-if="finalizing" class="w-4 h-4 animate-spin" />
          <CheckCircleIcon v-else class="w-4 h-4" />
          {{ finalizing ? 'Finalizing…' : 'Finalize & Print' }}
        </button>
      </div>

      <!-- Normal sale actions -->
      <div v-else class="flex flex-wrap gap-2 items-center">
        <!-- Settle shortcut for pending bookings -->
        <button v-if="sale?.sale_type === 'booking' && sale?.delivery_status === 'booked'"
          @click="settleModal = true"
          class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium text-sm shadow-sm transition-colors">
          <CheckCircleIcon class="w-4 h-4" /> Settle &amp; Deliver
        </button>


    

        <!-- Send SMS -->
        <button v-if="sale" @click="openSmsModal"
          class="inline-flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium text-sm shadow-sm transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
          </svg>
          Send SMS
        </button>

        <!-- Standard Print -->
        <button @click="printReceipt" :disabled="loading || !sale || printing"
          class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-white rounded-lg font-medium text-sm shadow-sm transition-colors">
          <svg v-if="printing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          <PrinterIcon v-else class="w-4 h-4" />
          {{ printing ? 'Printing…' : 'Print Receipt' }}
        </button>
      </div>
    </div>

    <!-- Draft banner -->
    <div v-if="sale?.is_draft" class="no-print mb-6 bg-yellow-50 border border-yellow-300 rounded-xl p-4 flex items-start gap-3">
      <div class="w-9 h-9 rounded-full bg-yellow-200 flex items-center justify-center shrink-0 text-yellow-700 font-bold text-lg">!</div>
      <div>
        <p class="font-semibold text-yellow-900">This is a draft — not yet invoiced</p>
        <p class="text-sm text-yellow-700 mt-0.5">Stock has not been deducted and no accounting entry has been posted. Add parts as they are picked from the shop, then click <strong>Finalize &amp; Print</strong> when the repair is complete.</p>
      </div>
    </div>

    <div v-if="directPrintError" class="no-print mb-4 px-3 py-2 rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm">
      {{ directPrintError }}
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20 text-gray-400">
      <ArrowPathIcon class="w-5 h-5 animate-spin mr-2" /> Loadingâ€¦
    </div>

    <template v-else-if="sale">

      <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           POS / 80mm Thermal Receipt (POS-80)
      â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
      <div id="receipt-wrapper">
        <div id="receipt" class="receipt-paper">

          <!-- HEADER -->
          <div style="text-align:center; margin-bottom:6px;">
            <img v-if="shop.logo_url" :src="shop.logo_url" alt="logo"
              style="max-height:40px; max-width:160px; object-fit:contain; display:block; margin:0 auto 4px;" />
            <div style="font-size:17px; font-weight:bold; letter-spacing:1px; text-transform:uppercase;">
              {{ shop.shop_name || appName }}
            </div>
            <div v-if="shop.address || shop.phone" style="font-size:12px; color:#555; margin-top:2px;">
              <span v-if="shop.address">{{ shop.address }}</span><span v-if="shop.address && shop.phone"> | </span><span v-if="shop.phone">{{ shop.phone }}</span>
            </div>
            <div v-if="shop.br_number" style="font-size:12px; color:#555;">BR: {{ shop.br_number }}</div>
            <div style="font-size:12px; margin-top:3px;">Sales Receipt</div>
          </div>

          <hr class="receipt-divider-double" />

          <!-- INVOICE META -->
          <div style="font-size:10px; margin-bottom:4px;">
            <div v-if="sale.sale_type === 'booking'" style="text-align:center; font-weight:bold; font-size:11px; background:#111; color:#fff; padding:2px 0; margin-bottom:4px; letter-spacing:1px;">&#9733; BOOKING ADVANCE &#9733;</div>
            <div class="flex-row"><span>Invoice :</span><span style="font-weight:bold; float:right;">{{ sale.invoice_number }}</span></div>
            <div class="flex-row"><span>Date    :</span><span style="float:right;">{{ fmtDate(sale.sold_at) }}</span></div>
            <div class="flex-row"><span>Time    :</span><span style="float:right;">{{ formatTime(sale.sold_at) }}</span></div>
            <div class="flex-row"><span>Cashier :</span><span style="float:right;">{{ sale.user?.name ?? '&mdash;' }}</span></div>
            <div class="flex-row"><span>Payment :</span><span style="float:right; text-transform:capitalize;">{{ sale.payment_method?.replace('_',' ') }}</span></div>
            <div class="flex-row">
              <span>Status  :</span>
              <span style="float:right; font-weight:bold; text-transform:capitalize;">{{ sale.payment_status?.toUpperCase() }}</span>
            </div>
            <div v-if="sale.sale_type === 'booking' && sale.booking_expires_at" class="flex-row" style="color:#b91c1c; font-weight:bold;">
              <span>Expires :</span><span style="float:right;">{{ fmtDate(sale.booking_expires_at) }}</span>
            </div>
          </div>

          <hr class="receipt-divider" />

          <!-- CUSTOMER -->
          <div style="font-size:10px; margin-bottom:4px;">
            <div><strong>Customer:</strong> {{ sale.customer?.name ?? 'Walk-in' }}</div>
            <div v-if="sale.customer?.phone">Phone: {{ sale.customer.phone }}</div>
            <div v-if="sale.customer?.vehicle_number">Vehicle: {{ sale.customer.vehicle_number }}</div>
          </div>

          <hr class="receipt-divider" />

          <!-- ITEMS -->
          <div style="font-size:10px;">
            <div style="display:flex; font-weight:bold; border-bottom:1px solid #333; padding-bottom:3px; margin-bottom:3px;">
              <span style="flex:1;">Item</span>
              <span style="width:28px; text-align:center;">Qty</span>
              <span style="width:54px; text-align:right;">Price</span>
              <span style="width:58px; text-align:right;">Total</span>
            </div>
            <div v-for="item in sale.items" :key="item.id" style="margin-bottom:5px;">
              <div style="display:flex; align-items:baseline;">
                <span style="flex:1; font-weight:bold; word-break:break-word; padding-right:4px;">{{ item.description || item.product?.name || 'Item' }}</span>
                <span style="width:28px; text-align:center;">{{ item.quantity }}</span>
                <span style="width:54px; text-align:right;">{{ lkr(item.unit_price) }}</span>
                <span style="width:58px; text-align:right; font-weight:bold;">{{ lkr(item.total) }}</span>
              </div>
              <div style="color:#555; font-size:9px; padding-left:2px; line-height:1.3;">
                <span v-if="item.product?.sku">{{ item.product.sku }}</span>
              </div>
              <div v-if="Number(item.discount) > 0" style="font-size:9px; color:#555; padding-left:2px;">
                Item Disc: -{{ lkr(item.discount) }}
              </div>
            </div>
          </div>

          <hr class="receipt-divider-solid" />

          <!-- TOTALS -->
          <div style="font-size:11px;">
            <div v-if="Number(sale.subtotal) !== Number(sale.total)" style="display:flex; justify-content:space-between; margin-bottom:2px;">
              <span>Subtotal</span><span>LKR {{ lkr(sale.subtotal) }}</span>
            </div>
            <div v-if="Number(sale.discount) > 0" style="display:flex; justify-content:space-between; margin-bottom:2px;">
              <span>Discount</span><span>-LKR {{ lkr(sale.discount) }}</span>
            </div>
            <div v-if="Number(sale.tax) > 0" style="display:flex; justify-content:space-between; margin-bottom:2px;">
              <span>Tax ({{ sale.tax_rate }}%)</span><span>+LKR {{ lkr(sale.tax) }}</span>
            </div>
            <div v-if="Number(sale.maintenance_amount) > 0" style="display:flex; justify-content:space-between; margin-bottom:2px;">
              <span>Maintenance</span><span>+LKR {{ lkr(sale.maintenance_amount) }}</span>
            </div>
          </div>

          <hr class="receipt-divider-double" />

          <div style="display:flex; justify-content:space-between; font-size:14px; font-weight:bold; margin:4px 0;">
            <span>TOTAL</span><span>LKR {{ lkr(sale.total) }}</span>
          </div>

          <hr class="receipt-divider" />

          <div style="font-size:11px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
              <span>Paid</span><span>LKR {{ lkr(sale.amount_paid) }}</span>
            </div>
            <div v-if="Number(sale.amount_paid) > Number(sale.total)" style="display:flex; justify-content:space-between; font-weight:bold;">
              <span>Change</span><span>LKR {{ lkr(Number(sale.amount_paid) - Number(sale.total)) }}</span>
            </div>
            <div v-if="Number(sale.amount_paid) < Number(sale.total)" style="display:flex; justify-content:space-between; font-weight:bold;">
              <span>Balance Due</span><span>LKR {{ lkr(Number(sale.total) - Number(sale.amount_paid)) }}</span>
            </div>
          </div>

          <!-- NOTES -->
          <div v-if="sale.notes" style="margin-top:6px; font-size:9px; color:#555;">
            <hr class="receipt-divider" />
            Note: {{ sale.notes }}
          </div>

          <hr class="receipt-divider" />

          <!-- FOOTER -->
          <div style="text-align:center; font-size:10px; line-height:1.6;">
            <div style="font-weight:bold;">*** Thank You! Come Again ***</div>
          </div>

          <!-- QR CODE -->
          <div style="display:flex; flex-direction:column; align-items:center; margin:6px 0 2px;">
            <img v-if="qrDataUrl" :src="qrDataUrl" style="width:64px; height:64px;" />
            <div style="font-size:10px; font-weight:bold; letter-spacing:0.5px; margin-top:4px;">lumac.lk | 076 464 3050</div>
          </div>

        </div>
      </div>


    </template>

    <!-- Error -->
    <div v-if="!loading && !sale" class="text-center py-20 text-gray-400">
      <p>Sale not found.</p>
      <router-link to="/sales" class="text-amber-600 hover:underline text-sm mt-2 inline-block">â† Back to Sales</router-link>
    </div>

    <!-- Settle Modal (accessible from receipt page for pending bookings) -->
    <teleport to="body">
      <div v-if="settleModal" class="fixed inset-0 z-50 bg-black/40 p-4 flex items-center justify-center no-print">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
          <h3 class="font-semibold text-gray-800 flex items-center gap-2">
            <CheckCircleIcon class="w-5 h-5 text-purple-600" /> Settle Booking &amp; Deliver
          </h3>
          <div class="bg-purple-50 border border-purple-200 rounded-lg p-3 text-sm">
            <div class="font-medium text-purple-900">{{ sale?.invoice_number }}</div>
            <div class="text-purple-700 mt-1">Remaining: <strong>LKR {{ settleRemaining }}</strong></div>
          </div>
          <div>
            <label class="form-label">Payment Method</label>
            <select v-model="settleForm.payment_method" class="form-input">
              <option value="cash">Cash</option>
              <option value="card">Card</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="cheque">Cheque</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div>
            <label class="form-label">Payment Amount (LKR)</label>
            <input v-model.number="settleForm.payment_amount" type="number" min="0" step="0.01" class="form-input" />
          </div>
          <div>
            <label class="form-label">Delivered At</label>
            <input v-model="settleForm.delivered_at" type="datetime-local" class="form-input" />
          </div>
          <p v-if="settleError" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded">{{ settleError }}</p>
          <div class="flex justify-end gap-2">
            <button @click="settleModal = false" class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <button @click="submitSettle" :disabled="settling" class="px-4 py-2 text-sm bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50 font-medium">
              {{ settling ? 'Posting…' : 'Settle & Deliver' }}
            </button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- SMS Modal -->
    <teleport to="body">
      <div v-if="smsModal" class="fixed inset-0 z-50 bg-black/40 p-4 flex items-center justify-center no-print">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
          <h3 class="font-semibold text-gray-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            Send Receipt SMS
          </h3>

          <!-- Recipient -->
          <div>
            <label class="form-label">Recipient Phone Number</label>
            <input v-model="smsPhone" type="tel" placeholder="e.g. 0771234567"
              class="form-input" />
            <p v-if="sale?.customer?.name" class="text-xs text-gray-400 mt-1">
              Customer: {{ sale.customer.name }}
            </p>
          </div>

          <!-- Message -->
          <div>
            <label class="form-label">Message</label>
            <textarea v-model="smsMessage" rows="4"
              class="form-input resize-none text-sm font-mono"></textarea>
            <p class="text-xs text-gray-400 mt-1">{{ smsMessage.length }} characters</p>
          </div>

          <p v-if="smsError" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded">{{ smsError }}</p>
          <p v-if="smsSent" class="text-sm text-green-700 bg-green-50 px-3 py-2 rounded flex items-center gap-1.5">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            SMS sent successfully!
          </p>
          <div class="flex justify-end gap-2">
            <button @click="smsModal = false; smsSent = false" class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Close</button>
            <button @click="submitSms" :disabled="sendingSms || smsSent || !smsPhone.trim()"
              class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 font-medium flex items-center gap-2">
              <svg v-if="sendingSms" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
              </svg>
              {{ sendingSms ? 'Sending…' : smsSent ? 'Sent' : 'Send SMS' }}
            </button>
          </div>
        </div>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import QRCode from 'qrcode'
import { ArrowLeftIcon, PrinterIcon, ArrowPathIcon, CheckCircleIcon, PencilSquareIcon } from '@heroicons/vue/24/outline'
import { fmtDate } from '../utils/date.js'

const WHATSAPP_URL = 'https://wa.me/94764643050'

const route           = useRoute()
const sale            = ref(null)
const loading         = ref(true)
const printing        = ref(false)
const qrDataUrl       = ref('')
const appName         = import.meta.env.VITE_APP_NAME ?? 'Siril Motors'
const preferredPrinter = import.meta.env.VITE_THERMAL_PRINTER ?? ''
const directPrinting  = ref(false)
const directPrintError = ref('')

// Finalize draft
const finalizing = ref(false)
async function finalizeDraft() {
  if (!sale.value) return
  finalizing.value = true
  try {
    const { data } = await axios.post(`/api/sales/${sale.value.id}/finalize`)
    sale.value = data
    await nextTick()
    await generateQr()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Could not finalize draft.')
  } finally {
    finalizing.value = false
  }
}

// Settle booking
const settleModal  = ref(false)
const settling     = ref(false)
const settleError  = ref('')
const settleForm   = ref({ payment_method: 'cash', payment_amount: 0, delivered_at: '' })

// SMS
const smsModal    = ref(false)
const sendingSms  = ref(false)
const smsSent     = ref(false)
const smsError    = ref('')
const smsMessage  = ref('')
const smsPhone    = ref('')

function openSmsModal() {
  if (!sale.value) return
  smsSent.value = false
  smsError.value = ''
  smsPhone.value = sale.value.customer?.phone ?? ''
  const appUrl = window.location.origin
  const link = `${appUrl}/receipt/${sale.value.view_token}`
  const type = sale.value.sale_type === 'booking' ? 'Booking' : 'Invoice'
  const name = sale.value.customer?.name ?? 'Customer'
  smsMessage.value = `Dear ${name}, your ${type} ${sale.value.invoice_number} of LKR ${Number(sale.value.total).toLocaleString('en-LK', { minimumFractionDigits: 2 })} has been created. View receipt: ${link} . Thank you!`
  smsModal.value = true
}

async function submitSms() {
  sendingSms.value = true; smsError.value = ''
  try {
    await axios.post(`/api/sales/${sale.value.id}/send-sms`, {
      message: smsMessage.value,
      phone:   smsPhone.value.trim(),
    })
    smsSent.value = true
  } catch (e) {
    smsError.value = e.response?.data?.message ?? 'Failed to send SMS'
  } finally {
    sendingSms.value = false
  }
}

const settleRemaining = computed(() => {
  if (!sale.value) return '0.00'
  return Number(Math.max(0, Number(sale.value.total) - Number(sale.value.amount_paid)))
    .toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
})

watch(settleModal, (open) => {
  if (open && sale.value) {
    settleForm.value = {
      payment_method: 'cash',
      payment_amount: Math.max(0, Number(sale.value.total) - Number(sale.value.amount_paid)),
      delivered_at: new Date().toISOString().slice(0, 16),
    }
    settleError.value = ''
  }
})

async function submitSettle() {
  settling.value = true
  settleError.value = ''
  try {
    const { data } = await axios.post(`/api/sales/${sale.value.id}/settle-booking`, settleForm.value)
    sale.value = data
    settleModal.value = false
    await nextTick()
    await generateQr()
  } catch (e) {
    settleError.value = e.response?.data?.message
      ?? Object.values(e.response?.data?.errors ?? {}).flat().join(', ')
      ?? 'Failed to settle booking'
  } finally {
    settling.value = false
  }
}

const shop = ref({ shop_name: '', address: '', phone: '', br_number: '', logo_url: '' })

function lkr(val) {
  return Number(val || 0).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function formatTime(d) {
  return new Date(d).toLocaleTimeString('en-LK', { hour: '2-digit', minute: '2-digit' })
}

async function generateQr() {
  qrDataUrl.value = await QRCode.toDataURL(WHATSAPP_URL, { width: 128, margin: 1 })
}

async function printReceipt() {
  printing.value = true
  setTimeout(() => { printing.value = false }, 3000)
  document.querySelector('#dyn-page-style')?.remove()
  const s = document.createElement('style')
  s.id = 'dyn-page-style'
  s.textContent = `@media print { @page { size: 80mm auto; margin: 0; } }`
  document.head.appendChild(s)
  if (window.electronAPI?.printReceipt) {
    await window.electronAPI.printReceipt('pos')
    return
  }
  window.print()
}

function buildDirectPrintHtml() {
  const receipt = document.getElementById('receipt')
  if (!receipt) return ''
  return `<!doctype html><html><head><meta charset="utf-8">
<style>
  html,body{margin:0;padding:0;background:#fff;}
  .receipt-paper{width:80mm;max-width:80mm;margin:0;padding:3mm 4mm;box-sizing:border-box;
    font-family:'Courier New',Courier,monospace;font-size:11pt;line-height:1.45;color:#000;background:#fff;}
  .receipt-divider{border:none;border-top:1px dashed #aaa;margin:6px 0;}
  .receipt-divider-solid{border:none;border-top:1px solid #555;margin:6px 0;}
  .receipt-divider-double{border:none;border-top:3px double #333;margin:6px 0;}
  canvas{max-width:100%;}
</style></head><body>${receipt.outerHTML}</body></html>`
}

function qzScriptLoaded() { return typeof window !== 'undefined' && !!window.qz }

function loadQzScript() {
  return new Promise((resolve, reject) => {
    if (qzScriptLoaded()) return resolve()
    const existing = document.querySelector('script[data-qz-tray="1"]')
    if (existing) {
      existing.addEventListener('load', resolve, { once: true })
      existing.addEventListener('error', () => reject(new Error('Failed to load QZ Tray library.')), { once: true })
      return
    }
    const script = document.createElement('script')
    script.src = 'https://cdn.jsdelivr.net/npm/qz-tray@2.2.4/qz-tray.js'
    script.async = true; script.dataset.qzTray = '1'
    script.onload = resolve
    script.onerror = () => reject(new Error('Failed to load QZ Tray library.'))
    document.head.appendChild(script)
  })
}

async function ensureQzConnected() {
  await loadQzScript()
  const qz = window.qz
  if (!qz) throw new Error('QZ Tray library is not available.')
  qz.api.setPromiseType(Promise)
  qz.security.setCertificatePromise(() => Promise.resolve(null))
  qz.security.setSignaturePromise(() => Promise.resolve(''))
  if (!qz.websocket.isActive()) await qz.websocket.connect({ retries: 2, delay: 0.5 })
  return qz
}

async function resolvePrinter(qz) {
  if (preferredPrinter) {
    const exact = await qz.printers.find(preferredPrinter).catch(() => null)
    if (exact) return exact
  }
  const fallback = await qz.printers.getDefault().catch(() => null)
  if (fallback) return fallback
  throw new Error('No printer found. Set a default printer or configure VITE_THERMAL_PRINTER.')
}

async function directPrint() {
  if (!sale.value) return
  directPrintError.value = ''; directPrinting.value = true
  try {
    const html = buildDirectPrintHtml()
    if (!html) throw new Error('Receipt content is not ready yet.')
    const qz = await ensureQzConnected()
    const printer = await resolvePrinter(qz)
    const config = qz.configs.create(printer, {
      units: 'mm', copies: 1, scaleContent: true, rasterize: true,
      jobName: `Sale-${sale.value.invoice_number ?? sale.value.id}`,
    })
    await qz.print(config, [{ type: 'html', format: 'plain', data: html }])
  } catch (err) {
    directPrintError.value = `${err?.message || 'Direct print failed.'} Install/open QZ Tray, trust this site, then try again.`
  } finally {
    directPrinting.value = false
  }
}

onMounted(async () => {
  try {
    const [saleRes, settingsRes] = await Promise.all([
      axios.get(`/api/sales/${route.params.id}`),
      axios.get('/api/shop-settings').catch(() => ({ data: {} })),
    ])
    sale.value = saleRes.data
    const s = settingsRes.data
    Object.keys(shop.value).forEach(k => { if (s[k] != null) shop.value[k] = s[k] })
    await generateQr()
  } catch {
    sale.value = null
  } finally {
    loading.value = false
  }
})
</script>

<style>
/* â"€â"€ Screen: POS preview â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€ */
.receipt-paper {
  width: 287px;
  padding: 16px 18px 16px 14px;
  margin: 0 auto 32px;
  background: #fff;
  box-shadow: 0 0 0 1px #e5e7eb, 0 4px 24px rgba(0,0,0,0.08);
  border-radius: 4px;
  font-family: 'Courier New', Courier, monospace;
  font-size: 17px;
  font-weight: 600;
  line-height: 1.5;
  color: #111;
}
.receipt-divider        { border: none; border-top: 1px dashed #aaa; margin: 6px 0; }
.receipt-divider-solid  { border: none; border-top: 1px solid #555; margin: 6px 0; }
.receipt-divider-double { border: none; border-top: 3px double #333; margin: 6px 0; }

@media print {
  .no-print, aside, nav, header, footer { display: none !important; }
  html, body { margin:0 !important; padding:0 !important; height:auto !important; overflow:visible !important; background:#fff !important; }
  #app, #app > div, #app main { width:auto !important; min-width:0 !important; height:auto !important; min-height:0 !important; overflow:visible !important; padding:0 !important; margin:0 !important; background:#fff !important; }
  #receipt-wrapper { display:block !important; position:static !important; width:80mm !important; padding:0 !important; margin:0 !important; overflow:visible !important; }
  .receipt-paper { width:80mm !important; max-width:80mm !important; margin:0 !important; padding:3mm 8mm 3mm 4mm !important; box-shadow:none !important; border-radius:0 !important; font-size:16pt !important; font-weight:600 !important; font-family:'Courier New',Courier,monospace !important; color:#000 !important; background:#fff !important; }
  #receipt-wrapper, #receipt-wrapper * { color:#000 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; background:transparent !important; }
}
</style>
