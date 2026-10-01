<template>
  <teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 no-print"
      @click.self="$emit('close')">
      <div class="bg-white rounded-2xl shadow-2xl flex flex-col max-h-[92vh] w-full max-w-sm overflow-hidden">

        <!-- Modal toolbar -->
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-200 shrink-0">
          <div class="flex items-center gap-2">
            <span class="font-mono font-bold text-blue-700 text-sm">{{ card?.card_number }}</span>
            <span v-if="card" :class="statusClass(card.status)"
              class="text-[10px] font-bold px-2 py-0.5 rounded-full capitalize">
              {{ statusLabel(card.status) }}
            </span>
          </div>
          <div class="flex items-center gap-2">
            <button @click="doPrint" :disabled="loading || !card"
              class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white rounded-lg text-sm font-bold transition-colors shadow-sm">
              <PrinterIcon class="w-4 h-4" />
              Print
            </button>
            <button @click="$emit('close')"
              class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 transition-colors">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
        </div>

        <!-- Receipt preview -->
        <div class="overflow-y-auto flex-1 bg-gray-100 p-4 flex justify-center">

          <!-- Loading -->
          <div v-if="loading" class="flex items-center gap-2 text-gray-400 py-12">
            <div class="w-5 h-5 border-2 border-gray-300 border-t-gray-600 rounded-full animate-spin"></div>
            Loading receipt…
          </div>

          <!-- Error -->
          <div v-else-if="!card" class="text-center py-12 text-gray-400 text-sm">
            Could not load receipt.
          </div>

          <!-- Receipt paper -->
          <div v-else id="jc-receipt" class="receipt-paper">

            <!-- HEADER -->
            <div style="text-align:center; margin-bottom:6px;">
              <div style="font-size:1.2em; font-weight:bold; letter-spacing:1px; text-transform:uppercase;">
                {{ shop.shop_name || 'Siril Motors' }}
              </div>
              <div v-if="shop.address || shop.phone" style="color:#555; margin-top:2px;">
                <span v-if="shop.address">{{ shop.address }}</span>
                <span v-if="shop.address && shop.phone"> | </span>
                <span v-if="shop.phone">{{ shop.phone }}</span>
              </div>
              <div v-if="shop.br_number" style="color:#555;">BR: {{ shop.br_number }}</div>
              <div style="margin-top:3px;">Service Invoice</div>
            </div>

            <hr class="receipt-divider-double" />

            <!-- META -->
            <div style="margin-bottom:4px;">
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
            <div style="margin-bottom:4px;">
              <div><strong>Customer:</strong> {{ card.customer_name || 'Walk-in' }}</div>
              <div v-if="card.vehicle_number">Vehicle: <strong>{{ card.vehicle_number }}</strong></div>
              <div v-if="card.vehicle_make || card.vehicle_model" style="color:#555;">
                {{ [card.vehicle_make, card.vehicle_model].filter(Boolean).join(' ') }}
              </div>
              <div v-if="card.mileage" style="color:#555;">Mileage: {{ Number(card.mileage).toLocaleString() }} km</div>
            </div>

            <div v-if="card.complaint" style="color:#555; margin-bottom:4px;">
              Issue: {{ card.complaint }}
            </div>

            <hr class="receipt-divider" />

            <!-- ITEMS -->
            <div>
              <!-- Header -->
              <div style="display:flex; justify-content:space-between; font-weight:bold; border-bottom:2px solid #333; padding-bottom:3px; margin-bottom:5px;">
                <span style="flex:1;">Description</span>
                <span style="min-width:60px; text-align:right;">Total</span>
              </div>
              <div v-for="item in card.items" :key="item.description + item.type" style="margin-bottom:7px; border-bottom:1px dashed #ccc; padding-bottom:5px;">
                <!-- Name line -->
                <div style="font-weight:bold; word-break:break-word; margin-bottom:2px;">{{ item.description }}</div>
                <!-- Type + qty × price + total -->
                <div style="display:flex; align-items:baseline; justify-content:space-between;">
                  <span style="text-transform:capitalize; color:#777; min-width:40px;">{{ item.type }}</span>
                  <span style="flex:1; text-align:center; color:#555;">{{ item.quantity }} × {{ lkr(item.unit_price) }}</span>
                  <span style="font-weight:bold; min-width:60px; text-align:right;">{{ lkr(item.total) }}</span>
                </div>
                <!-- Discount line -->
                <div v-if="Number(item.discount) > 0" style="display:flex; justify-content:space-between; color:#c00; margin-top:1px;">
                  <span>Discount</span>
                  <span>-{{ lkr(item.discount) }}</span>
                </div>
              </div>
            </div>

            <hr class="receipt-divider-solid" />

            <div v-if="card.bill_discount > 0" style="margin-bottom:2px;">
              <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                <span>Subtotal</span><span>LKR {{ lkr(card.total) }}</span>
              </div>
              <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                <span>Bill Discount</span><span>-LKR {{ lkr(card.bill_discount) }}</span>
              </div>
            </div>

            <hr class="receipt-divider-double" />

            <div style="display:flex; justify-content:space-between; font-size:1.2em; font-weight:bold; margin:4px 0;">
              <span>TOTAL</span><span>LKR {{ lkr((card.net_total ?? card.total)) }}</span>
            </div>

            <hr class="receipt-divider" />

            <div style="text-align:center; line-height:1.6;">
              <div style="font-weight:bold;">*** Thank You! ***</div>
              <div>{{ shop.shop_name || 'Siril Motors' }}</div>
            </div>

          </div>
        </div>

      </div>
    </div>
  </teleport>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { PrinterIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ token: { type: String, required: true } })
defineEmits(['close'])

const card    = ref(null)
const loading = ref(true)
const shop    = ref({ shop_name: '', address: '', phone: '', br_number: '' })

function doPrint() {
  const receipt = document.getElementById('jc-receipt')
  if (!receipt) return

  const win = window.open('', '_blank', 'width=340,height=800')
  win.document.write(`<!doctype html><html><head><meta charset="utf-8">
<style>
  html,body{margin:0;padding:0;background:#fff;width:80mm;}
  @page{size:80mm auto;margin:0;}
  .receipt-paper{width:80mm;max-width:80mm;margin:0;padding:3mm 6mm 3mm 4mm;box-sizing:border-box;
    font-family:'Courier New',Courier,monospace;font-size:10pt;font-weight:600;line-height:1.6;color:#000;background:#fff;}
  .receipt-divider{border:none;border-top:1px dashed #aaa;margin:5px 0;}
  .receipt-divider-solid{border:none;border-top:1px solid #555;margin:5px 0;}
  .receipt-divider-double{border:none;border-top:3px double #333;margin:5px 0;}
  .flex-row{display:flex;justify-content:space-between;}
</style></head><body>${receipt.outerHTML}<script>window.onload=function(){window.print();window.onafterprint=function(){window.close()};}<\/script></body></html>`)
  win.document.close()
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
      axios.get(`/api/job-cards/public/${props.token}`),
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

<style scoped>
.receipt-paper {
  width: 287px;
  padding: 16px 18px 16px 14px;
  background: #fff;
  box-shadow: 0 0 0 1px #e5e7eb, 0 4px 24px rgba(0,0,0,0.08);
  border-radius: 4px;
  font-family: 'Courier New', Courier, monospace;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.55;
  color: #111;
}
.receipt-divider        { border: none; border-top: 1px dashed #aaa; margin: 6px 0; }
.receipt-divider-solid  { border: none; border-top: 1px solid #555; margin: 6px 0; }
.receipt-divider-double { border: none; border-top: 3px double #333; margin: 6px 0; }
.flex-row               { display: flex; justify-content: space-between; }
</style>
