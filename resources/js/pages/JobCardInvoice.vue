<template>
  <div>

    <!-- ── Screen toolbar (no-print) ── -->
    <div class="no-print flex flex-wrap items-center justify-between gap-3 mb-6">
      <div class="flex items-center gap-3">
        <a :href="`/job/${route.params.token}`"
          class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 transition-colors">
          <ArrowLeftIcon class="w-4 h-4" /> Back to Status
        </a>
        <span class="text-gray-300">/</span>
        <span class="text-sm font-medium text-gray-700">{{ card?.card_number }}</span>
        <span v-if="card" :class="statusClass(card.status)"
          class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full capitalize">
          {{ statusLabel(card.status) }}
        </span>
      </div>
      <button @click="printInvoice"
        class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-medium text-sm shadow-sm transition-colors">
        <PrinterIcon class="w-4 h-4" />
        Print Receipt
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20 text-gray-400">
      <div class="w-5 h-5 border-2 border-gray-300 border-t-gray-600 rounded-full animate-spin mr-2"></div>
      Loading…
    </div>

    <!-- Not found -->
    <div v-else-if="!card" class="text-center py-20 text-gray-400">
      <p>Job card not found.</p>
    </div>

    <template v-else>

      <!-- ── 80mm Thermal Receipt ── -->
      <div id="receipt-wrapper">
        <div id="receipt" class="receipt-paper">

          <!-- HEADER -->
          <div style="text-align:center; margin-bottom:6px;">
            <div style="font-size:17px; font-weight:bold; letter-spacing:1px; text-transform:uppercase;">
              {{ shop.shop_name || 'Siril Motors' }}
            </div>
            <div v-if="shop.address || shop.phone" style="font-size:12px; color:#555; margin-top:2px;">
              <span v-if="shop.address">{{ shop.address }}</span>
              <span v-if="shop.address && shop.phone"> | </span>
              <span v-if="shop.phone">{{ shop.phone }}</span>
            </div>
            <div v-if="shop.br_number" style="font-size:12px; color:#555;">BR: {{ shop.br_number }}</div>
            <div style="font-size:12px; margin-top:3px;">Service Invoice</div>
          </div>

          <hr class="receipt-divider-double" />

          <!-- JOB CARD META -->
          <div style="font-size:10px; margin-bottom:4px;">
            <div class="flex-row"><span>Job Card :</span><span style="font-weight:bold; float:right;">{{ card.card_number }}</span></div>
            <div class="flex-row"><span>Date     :</span><span style="float:right;">{{ fmtDate(card.created_at) }}</span></div>
            <div v-if="card.completed_at" class="flex-row"><span>Completed:</span><span style="float:right;">{{ fmtDate(card.completed_at) }}</span></div>
            <div class="flex-row">
              <span>Status   :</span>
              <span style="float:right; font-weight:bold; text-transform:capitalize;">{{ statusLabel(card.status) }}</span>
            </div>
            <div v-if="card.assigned_technician" class="flex-row">
              <span>Tech     :</span><span style="float:right;">{{ card.assigned_technician }}</span>
            </div>
          </div>

          <hr class="receipt-divider" />

          <!-- CUSTOMER & VEHICLE -->
          <div style="font-size:10px; margin-bottom:4px;">
            <div><strong>Customer:</strong> {{ card.customer_name || 'Walk-in' }}</div>
            <div v-if="card.vehicle_number">Vehicle: <strong>{{ card.vehicle_number }}</strong></div>
            <div v-if="card.vehicle_make || card.vehicle_model" style="color:#555;">
              {{ [card.vehicle_make, card.vehicle_model].filter(Boolean).join(' ') }}
            </div>
            <div v-if="card.mileage" style="color:#555;">Mileage: {{ Number(card.mileage).toLocaleString() }} km</div>
          </div>

          <div v-if="card.complaint" style="font-size:9px; color:#555; margin-bottom:4px;">
            Issue: {{ card.complaint }}
          </div>

          <hr class="receipt-divider" />

          <!-- ITEMS -->
          <div style="font-size:10px;">
            <div style="display:flex; font-weight:bold; border-bottom:1px solid #333; padding-bottom:3px; margin-bottom:3px;">
              <span style="flex:1;">Description</span>
              <span style="width:28px; text-align:center;">Qty</span>
              <span style="width:54px; text-align:right;">Price</span>
              <span style="width:58px; text-align:right;">Total</span>
            </div>
            <div v-for="item in card.items" :key="item.description + item.type" style="margin-bottom:5px;">
              <div style="display:flex; align-items:baseline;">
                <span style="flex:1; font-weight:bold; word-break:break-word; padding-right:4px;">{{ item.description }}</span>
                <span style="width:28px; text-align:center;">{{ item.quantity }}</span>
                <span style="width:54px; text-align:right;">{{ lkr(item.unit_price) }}</span>
                <span style="width:58px; text-align:right; font-weight:bold;">{{ lkr(item.total) }}</span>
              </div>
              <div style="color:#777; font-size:9px; padding-left:2px; text-transform:capitalize;">
                {{ item.type }}
              </div>
              <div v-if="Number(item.discount) > 0" style="font-size:9px; color:#555; padding-left:2px;">
                Disc: -{{ lkr(item.discount) }}
              </div>
            </div>
          </div>

          <hr class="receipt-divider-solid" />

          <!-- TOTAL -->
          <div style="font-size:11px;">
          </div>

          <hr class="receipt-divider-double" />

          <div style="display:flex; justify-content:space-between; font-size:14px; font-weight:bold; margin:4px 0;">
            <span>TOTAL</span><span>LKR {{ lkr(card.total) }}</span>
          </div>

          <hr class="receipt-divider" />

          <!-- FOOTER -->
          <div style="text-align:center; font-size:10px; line-height:1.6;">
            <div style="font-weight:bold;">*** Thank You! ***</div>
            <div>{{ shop.shop_name || 'Siril Motors' }}</div>
          </div>

        </div>
      </div>

    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { ArrowLeftIcon, PrinterIcon } from '@heroicons/vue/24/outline'

const route   = useRoute()
const card    = ref(null)
const loading = ref(true)
const shop    = ref({ shop_name: '', address: '', phone: '', br_number: '' })

function printInvoice() {
  document.querySelector('#dyn-page-style')?.remove()
  const s = document.createElement('style')
  s.id = 'dyn-page-style'
  s.textContent = `@media print { @page { size: 80mm auto; margin: 0; } }`
  document.head.appendChild(s)
  window.print()
}

function statusLabel(s) {
  return { received: 'Received', in_progress: 'In Progress', completed: 'Completed', delivered: 'Delivered', cancelled: 'Cancelled' }[s] ?? s
}
function statusClass(s) {
  return {
    received: 'bg-blue-100 text-blue-700', in_progress: 'bg-amber-100 text-amber-700',
    completed: 'bg-green-100 text-green-700', delivered: 'bg-purple-100 text-purple-700',
    cancelled: 'bg-red-100 text-red-700',
  }[s] ?? 'bg-gray-100 text-gray-600'
}

function lkr(v) { return Number(v || 0).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function fmtDate(d) {
  return d ? new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'
}

onMounted(async () => {
  try {
    const [cardRes, settingsRes] = await Promise.all([
      axios.get(`/api/job-cards/public/${route.params.token}`),
      axios.get('/api/shop-settings').catch(() => ({ data: {} })),
    ])
    card.value = cardRes.data
    const s = settingsRes.data
    Object.keys(shop.value).forEach(k => { if (s[k] != null) shop.value[k] = s[k] })
  } catch {
    card.value = null
  } finally {
    loading.value = false
  }
})
</script>

<style>
/* ── Screen: POS preview ── */
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
.flex-row               { display: flex; justify-content: space-between; }

@media print {
  .no-print, aside, nav, header, footer { display: none !important; }
  html, body { margin:0 !important; padding:0 !important; height:auto !important; overflow:visible !important; background:#fff !important; }
  #app, #app > div, #app main { width:auto !important; min-width:0 !important; height:auto !important; min-height:0 !important; overflow:visible !important; padding:0 !important; margin:0 !important; background:#fff !important; }
  #receipt-wrapper { display:block !important; position:static !important; width:80mm !important; padding:0 !important; margin:0 !important; overflow:visible !important; }
  .receipt-paper { width:80mm !important; max-width:80mm !important; margin:0 !important; padding:3mm 8mm 3mm 4mm !important; box-shadow:none !important; border-radius:0 !important; font-size:16pt !important; font-weight:600 !important; font-family:'Courier New',Courier,monospace !important; color:#000 !important; background:#fff !important; }
  #receipt-wrapper, #receipt-wrapper * { color:#000 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; background:transparent !important; }
}
</style>
