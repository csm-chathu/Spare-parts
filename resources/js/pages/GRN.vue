<template>
  <div class="space-y-4">

    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex gap-2">
        <button v-for="t in tabs" :key="t.value"
          @click="activeTab = t.value"
          class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors"
          :class="activeTab === t.value
            ? 'bg-blue-600 text-white border-blue-600'
            : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'">
          {{ t.label }}
        </button>
      </div>
      <button @click="openDirectGRN" class="btn-primary flex items-center gap-2">
        <span class="text-lg leading-none">+</span> New Direct GRN
      </button>
    </div>

    <!-- ─── GRN RECORDS TAB ─── -->
    <div v-if="activeTab === 'grn'" class="card overflow-hidden p-0">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">GRN / PO Number</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Supplier</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Received Date</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-600">Items</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-600">Received Value (LKR)</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-600">Status</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-600">Credit Due</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-600">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="loadingGRN"><td colspan="8" class="py-8 text-center text-gray-400">Loading…</td></tr>
            <tr v-else-if="!grns.length"><td colspan="8" class="py-8 text-center text-gray-400">No GRN records found.</td></tr>
            <tr v-for="grn in grns" :key="grn.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-mono text-green-700 font-semibold">{{ grn.purchase_number }}</td>
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800">{{ grn.supplier?.name ?? '—' }}</div>
                <div v-if="grn.supplier_ref" class="text-xs text-gray-400">Ref: {{ grn.supplier_ref }}</div>
              </td>
              <td class="px-4 py-3 text-gray-600">{{ fmtDate(grn.purchased_at) }}</td>
              <td class="px-4 py-3 text-right text-gray-700">{{ grn.items_count ?? '—' }}</td>
              <td class="px-4 py-3 text-right font-semibold text-gray-800">{{ numFmt(grn.total) }}</td>
              <td class="px-4 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusClass(grn.status)">
                  {{ grn.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-center text-xs">
                <template v-if="grn.payment_method === 'credit'">
                  <span v-if="grn.credit_settled_at" class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-semibold">
                    ✓ Settled
                  </span>
                  <span v-else-if="grn.credit_due_date" :class="isOverdue(grn.credit_due_date)
                    ? 'px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-semibold'
                    : 'px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-semibold'">
                    {{ fmtDate(grn.credit_due_date) }}
                    <span v-if="isOverdue(grn.credit_due_date)"> !</span>
                  </span>
                  <span v-else class="text-gray-400">—</span>
                </template>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-4 py-3 text-center">
                <button @click="viewGRN(grn)" class="text-xs text-blue-600 hover:underline">View GRN</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="grnMeta.last_page > 1" class="px-4 py-3 flex justify-between items-center border-t text-sm text-gray-500">
        <span>Page {{ grnMeta.current_page }} of {{ grnMeta.last_page }}</span>
        <div class="flex gap-2">
          <button :disabled="grnMeta.current_page <= 1" @click="grnPage--; fetchGRNs()"
            class="px-3 py-1 rounded border disabled:opacity-40">Prev</button>
          <button :disabled="grnMeta.current_page >= grnMeta.last_page" @click="grnPage++; fetchGRNs()"
            class="px-3 py-1 rounded border disabled:opacity-40">Next</button>
        </div>
      </div>
    </div>

    <!-- ─── PENDING POs TAB ─── -->
    <div v-if="activeTab === 'pending'" class="card overflow-hidden p-0">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">PO Number</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Supplier</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Order Date</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">Expected Delivery</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-600">Ordered Value (LKR)</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-600">Status</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-600">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="loadingPending"><td colspan="7" class="py-8 text-center text-gray-400">Loading…</td></tr>
            <tr v-else-if="!pendingPOs.length"><td colspan="7" class="py-8 text-center text-gray-400">No pending purchase orders.</td></tr>
            <tr v-for="po in pendingPOs" :key="po.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-mono text-blue-700 font-semibold">{{ po.purchase_number }}</td>
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800">{{ po.supplier?.name ?? '—' }}</div>
                <div v-if="po.supplier_ref" class="text-xs text-gray-400">Ref: {{ po.supplier_ref }}</div>
              </td>
              <td class="px-4 py-3 text-gray-600">{{ fmtDate(po.purchased_at) }}</td>
              <td class="px-4 py-3">
                <span v-if="po.expected_delivery" :class="isOverdue(po.expected_delivery) ? 'text-red-600 font-semibold' : 'text-gray-600'">
                  {{ fmtDate(po.expected_delivery) }}
                  <span v-if="isOverdue(po.expected_delivery)" class="text-xs ml-1">(Overdue)</span>
                </span>
                <span v-else class="text-gray-400">—</span>
              </td>
              <td class="px-4 py-3 text-right font-semibold text-gray-800">{{ numFmt(po.total) }}</td>
              <td class="px-4 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusClass(po.status)">
                  {{ po.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <button @click="openReceive(po)"
                  class="text-xs bg-green-600 text-white px-3 py-1 rounded-full hover:bg-green-700 font-semibold">
                  Receive Goods
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── VIEW GRN DETAIL MODAL ── -->
    <div v-if="viewModal.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 overflow-y-auto py-8">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl mx-4">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <div>
            <h3 class="text-lg font-semibold">GRN — {{ viewModal.grn?.purchase_number }}</h3>
            <p class="text-sm text-gray-500">{{ viewModal.grn?.supplier?.name }}</p>
          </div>
          <button @click="viewModal.open = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
        </div>

        <div v-if="viewModal.grn" class="p-6 space-y-5">

          <!-- Summary cards -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-500 uppercase font-semibold mb-1">GRN Date</p>
              <p class="font-medium">{{ fmtDate(viewModal.grn.purchased_at) }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Status</p>
              <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusClass(viewModal.grn.status)">
                {{ viewModal.grn.status }}
              </span>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Payment</p>
              <p class="capitalize font-medium">{{ viewModal.grn.payment_method ?? '—' }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Total Value</p>
              <p class="font-bold text-green-700">LKR {{ numFmt(viewModal.grn.total) }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Journal Entry</p>
              <p class="font-mono text-xs">{{ viewModal.grn.journal_entry?.entry_number ?? 'Not posted' }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Supplier Ref</p>
              <p>{{ viewModal.grn.supplier_ref ?? '—' }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Received By</p>
              <p>{{ viewModal.grn.user?.name ?? '—' }}</p>
            </div>
            <div v-if="viewModal.grn.payment_method === 'credit'" class="bg-amber-50 border border-amber-200 rounded-lg p-3">
              <p class="text-xs text-amber-600 uppercase font-semibold mb-1">Payment Due</p>
              <p class="font-semibold" :class="viewModal.grn.credit_due_date && isOverdue(viewModal.grn.credit_due_date) ? 'text-red-600' : 'text-amber-700'">
                {{ fmtDate(viewModal.grn.credit_due_date) }}
                <span v-if="viewModal.grn.credit_due_date && isOverdue(viewModal.grn.credit_due_date)" class="text-xs ml-1">(Overdue)</span>
              </p>
            </div>
            <div v-if="viewModal.grn.payment_method === 'cheque'" class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Cheque</p>
              <p class="text-xs">{{ viewModal.grn.cheque_number }} · {{ viewModal.grn.cheque_bank_name }}</p>
            </div>
            <div v-if="viewModal.grn.notes" class="col-span-2 bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Notes</p>
              <p class="text-sm text-gray-700">{{ viewModal.grn.notes }}</p>
            </div>
          </div>

          <!-- Items table -->
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm border rounded-lg overflow-hidden">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left">Product</th>
                  <th class="px-3 py-2 text-left">Part No.</th>
                  <th class="px-3 py-2 text-left">Category</th>
                  <th class="px-3 py-2 text-left">Vehicle / Model</th>
                  <th class="px-3 py-2 text-right">Ordered</th>
                  <th class="px-3 py-2 text-right">Received</th>
                  <th class="px-3 py-2 text-left">Batch</th>
                  <th class="px-3 py-2 text-left">Expiry</th>
                  <th class="px-3 py-2 text-right">Unit Cost</th>
                  <th class="px-3 py-2 text-right">Sell Price</th>
                  <th class="px-3 py-2 text-right">Line Total</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr v-for="item in viewModal.grn.items" :key="item.id" class="hover:bg-gray-50">
                  <td class="px-3 py-2">
                    <p class="font-medium text-gray-800">{{ item.product?.name ?? '—' }}</p>
                    <p class="text-xs text-gray-400 font-mono">{{ item.product?.sku }}</p>
                  </td>
                  <td class="px-3 py-2 font-mono text-xs text-blue-700">
                    {{ item.product?.part_number || '—' }}
                  </td>
                  <td class="px-3 py-2 text-xs text-gray-600">
                    {{ item.product?.part_category?.name || '—' }}
                  </td>
                  <td class="px-3 py-2 text-xs text-gray-600">
                    <span v-if="item.product?.vehicle_type">{{ item.product.vehicle_type.name }}</span>
                    <span v-if="item.product?.brand"> · {{ item.product.brand.name }}</span>
                    <span v-if="item.product?.model"> · {{ item.product.model.name }}</span>
                    <span v-if="!item.product?.vehicle_type && !item.product?.brand && !item.product?.model">—</span>
                  </td>
                  <td class="px-3 py-2 text-right text-gray-500">{{ item.ordered_quantity ?? item.quantity }}</td>
                  <td class="px-3 py-2 text-right">
                    <span :class="(item.received_quantity ?? item.quantity) >= (item.ordered_quantity ?? item.quantity)
                      ? 'text-green-700 font-semibold' : 'text-amber-600 font-semibold'">
                      {{ item.received_quantity ?? item.quantity }}
                    </span>
                  </td>
                  <td class="px-3 py-2">
                    <span v-if="item.batch_number" class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">{{ item.batch_number }}</span>
                    <span v-else class="text-gray-400">—</span>
                  </td>
                  <td class="px-3 py-2 text-xs text-gray-600">{{ item.expiry_date ?? '—' }}</td>
                  <td class="px-3 py-2 text-right">{{ numFmt(item.unit_cost) }}</td>
                  <td class="px-3 py-2 text-right text-blue-700">{{ numFmt(item.selling_price) }}</td>
                  <td class="px-3 py-2 text-right font-semibold">{{ numFmt(item.total) }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="9" class="px-3 py-2 text-right font-semibold text-gray-700">Total</td>
                  <td colspan="2" class="px-3 py-2 text-right font-bold text-green-700">LKR {{ numFmt(viewModal.grn.total) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>

        </div>

        <div class="px-6 py-4 border-t flex items-center justify-between">
          <button
            v-if="viewModal.grn?.payment_method === 'credit' && !viewModal.grn?.credit_settled_at"
            @click="openSettleCredit(viewModal.grn)"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700">
            Settle Credit Payment
          </button>
          <span v-else-if="viewModal.grn?.payment_method === 'credit' && viewModal.grn?.credit_settled_at"
            class="text-sm text-green-700 font-semibold">
            ✓ Credit settled on {{ fmtDate(viewModal.grn.credit_settled_at) }}
          </span>
          <span v-else></span>
          <button @click="viewModal.open = false" class="px-4 py-2 rounded-lg border text-gray-600 hover:bg-gray-50">Close</button>
        </div>
      </div>
    </div>

    <!-- ── SETTLE CREDIT MODAL ── -->
    <div v-if="settleCredit.open" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <div>
            <h3 class="text-lg font-semibold">Settle Credit Payment</h3>
            <p class="text-sm text-gray-500">{{ settleCredit.grn?.purchase_number }} — LKR {{ numFmt(settleCredit.grn?.total) }}</p>
          </div>
          <button @click="settleCredit.open = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="form-label">Payment Method *</label>
            <select v-model="settleCredit.payment_method" class="form-input">
              <option value="cash">Cash</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="cheque">Cheque</option>
            </select>
          </div>
          <div>
            <label class="form-label">Settlement Date</label>
            <input v-model="settleCredit.settled_date" type="date" class="form-input" />
          </div>
          <div>
            <label class="form-label">Notes</label>
            <textarea v-model="settleCredit.notes" rows="2" class="form-input" />
          </div>
          <div v-if="settleCredit.error" class="text-red-600 text-sm bg-red-50 px-3 py-2 rounded">{{ settleCredit.error }}</div>
        </div>
        <div class="px-6 py-4 border-t flex justify-end gap-3">
          <button @click="settleCredit.open = false" class="px-4 py-2 rounded-lg border text-gray-600 hover:bg-gray-50">Cancel</button>
          <button @click="submitSettleCredit" :disabled="settleCredit.submitting" class="btn-primary">
            {{ settleCredit.submitting ? 'Saving…' : 'Confirm Settlement' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── RECEIVE MODAL (against PO) ── -->
    <div v-if="receiveModal.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 overflow-y-auto py-8">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <div>
            <h3 class="text-lg font-semibold">Create GRN — {{ receiveModal.po?.purchase_number }}</h3>
            <p class="text-sm text-gray-500">{{ receiveModal.po?.supplier?.name }}</p>
          </div>
          <button @click="receiveModal.open = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
        </div>
        <div class="p-6 space-y-4">
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm border rounded-lg overflow-hidden">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left">Product</th>
                  <th class="px-3 py-2 text-right w-20">Ordered</th>
                  <th class="px-3 py-2 text-right w-28">Receive Qty</th>
                  <th class="px-3 py-2 text-right w-28">Unit Cost</th>
                  <th class="px-3 py-2 text-left w-32">Batch No.</th>
                  <th class="px-3 py-2 text-left w-32">Expiry Date</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr v-for="row in receiveModal.items" :key="row.id">
                  <td class="px-3 py-2 font-medium">{{ row.product?.name }}</td>
                  <td class="px-3 py-2 text-right text-gray-500">{{ row.ordered_quantity ?? row.quantity }}</td>
                  <td class="px-3 py-2">
                    <input v-model.number="row._qty" type="number" min="0" :max="row.ordered_quantity ?? row.quantity"
                      class="form-input text-right w-full" />
                  </td>
                  <td class="px-3 py-2">
                    <input v-model.number="row._cost" type="number" min="0" step="0.01" class="form-input text-right w-full" />
                  </td>
                  <td class="px-3 py-2">
                    <input v-model="row._batch" class="form-input" placeholder="Optional" />
                  </td>
                  <td class="px-3 py-2">
                    <input v-model="row._expiry" type="date" class="form-input" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div>
            <label class="form-label">Notes</label>
            <textarea v-model="receiveModal.notes" class="form-input" rows="2" />
          </div>
          <div v-if="receiveModal.error" class="text-red-600 text-sm bg-red-50 px-4 py-2 rounded">{{ receiveModal.error }}</div>
        </div>
        <div class="px-6 py-4 border-t flex justify-end gap-3">
          <button @click="receiveModal.open = false" class="px-4 py-2 rounded-lg border text-gray-600 hover:bg-gray-50">Cancel</button>
          <button @click="submitReceive" :disabled="receiveModal.submitting" class="btn-primary">
            {{ receiveModal.submitting ? 'Saving…' : 'Confirm GRN' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── DIRECT GRN MODAL ── -->
    <div v-if="directGRN.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 overflow-y-auto py-8">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-7xl mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h3 class="text-lg font-semibold">New Direct GRN (Walk-in Delivery)</h3>
          <button @click="directGRN.open = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
        </div>
        <div class="p-6 flex gap-6 min-h-0">

          <!-- Left: GRN details -->
          <div class="w-72 flex-shrink-0 space-y-4">
            <div>
              <label class="form-label">Supplier *</label>
              <SearchableSelect v-model="directGRN.supplier_id" :options="suppliers" placeholder="Search supplier…" />
            </div>
            <div>
              <label class="form-label">Supplier Invoice / Ref No.</label>
              <input v-model="directGRN.supplier_ref" class="form-input" placeholder="e.g. INV-2024-001" />
            </div>
            <div>
              <label class="form-label">GRN Date</label>
              <input v-model="directGRN.grn_date" type="date" class="form-input" />
            </div>
            <div>
              <label class="form-label">Payment Method</label>
              <select v-model="directGRN.payment_method" class="form-input">
                <option value="cash">Cash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cheque">Cheque</option>
                <option value="credit">Credit (Pay Later)</option>
              </select>
            </div>
            <template v-if="directGRN.payment_method === 'cheque'">
              <div>
                <label class="form-label">Cheque Number *</label>
                <input v-model="directGRN.cheque_number" class="form-input" />
              </div>
              <div>
                <label class="form-label">Cheque Date *</label>
                <input v-model="directGRN.cheque_date" type="date" class="form-input" />
              </div>
              <div>
                <label class="form-label">Bank Name *</label>
                <input v-model="directGRN.cheque_bank_name" class="form-input" />
              </div>
            </template>
            <template v-if="directGRN.payment_method === 'credit'">
              <div>
                <label class="form-label">Payment Due Date *</label>
                <input v-model="directGRN.credit_due_date" type="date" class="form-input" />
                <p v-if="directGRN.credit_due_date && isOverdue(directGRN.credit_due_date)"
                  class="text-xs text-amber-600 mt-1">⚠ Due date is in the past</p>
              </div>
            </template>
            <div>
              <label class="form-label">Notes</label>
              <textarea v-model="directGRN.notes" class="form-input" rows="3" />
            </div>
            <div v-if="directGRN.error" class="text-red-600 text-sm bg-red-50 px-3 py-2 rounded">{{ directGRN.error }}</div>
          </div>

          <!-- Right: Items received -->
          <div class="flex-1 min-w-0 flex flex-col">
            <div class="flex items-center justify-between mb-2">
              <h4 class="font-semibold text-gray-700">Items Received</h4>
              <button @click="directGRN.items.push(blankDGItem())" type="button"
                class="text-sm text-blue-600 hover:underline">+ Add Item</button>
            </div>
            <div class="overflow-auto flex-1">
              <table class="min-w-full text-sm border rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-3 py-2 text-left">Product</th>
                    <th class="px-3 py-2 text-right w-20">Qty</th>
                    <th class="px-3 py-2 text-right w-28">Unit Cost</th>
                    <th class="px-3 py-2 text-right w-28">Selling Price</th>
                    <th class="px-3 py-2 text-left w-28">Batch No.</th>
                    <th class="px-3 py-2 text-left w-28">Expiry</th>
                    <th class="w-8"></th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  <tr v-for="(item, idx) in directGRN.items" :key="idx">
                    <td class="px-3 py-2">
                      <SearchableSelect v-model="item.product_id" :async-search="searchProducts"
                        placeholder="Search name or part no…" @select="(opt) => prefillDG(item, opt)" />
                    </td>
                    <td class="px-3 py-2">
                      <input v-model.number="item.ordered_quantity" type="number" min="1" class="form-input text-right" />
                    </td>
                    <td class="px-3 py-2">
                      <input v-model.number="item.unit_cost" type="number" min="0" step="1" class="form-input text-right" />
                    </td>
                    <td class="px-3 py-2">
                      <input v-model.number="item.selling_price" type="number" min="0" step="1" class="form-input text-right" />
                    </td>
                    <td class="px-3 py-2">
                      <input v-model="item.batch_number" class="form-input" placeholder="Optional" />
                    </td>
                    <td class="px-3 py-2">
                      <input v-model="item.expiry_date" type="date" class="form-input" />
                    </td>
                    <td class="px-3 py-2 text-center">
                      <button v-if="directGRN.items.length > 1" @click="directGRN.items.splice(idx,1)"
                        class="text-red-400 hover:text-red-600">✕</button>
                    </td>
                  </tr>
                </tbody>
                <tfoot class="bg-gray-50">
                  <tr>
                    <td colspan="2" class="px-3 py-2 text-right font-semibold text-gray-700">Total:</td>
                    <td colspan="5" class="px-3 py-2 text-right font-bold text-blue-700">
                      {{ numFmt(directGRN.items.reduce((s,i) => s + (i.unit_cost||0)*(i.ordered_quantity||0), 0)) }}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

        </div>
        <div class="px-6 py-4 border-t flex justify-end gap-3">
          <button @click="directGRN.open = false" class="px-4 py-2 rounded-lg border text-gray-600 hover:bg-gray-50">Cancel</button>
          <button @click="submitDirectGRN" :disabled="directGRN.submitting" class="btn-primary">
            {{ directGRN.submitting ? 'Saving…' : 'Post GRN' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import axios from 'axios'
import SearchableSelect from '@/components/SearchableSelect.vue'

const activeTab    = ref('grn')
const grns         = ref([])
const pendingPOs   = ref([])
const suppliers    = ref([])
const loadingGRN   = ref(false)
const loadingPending = ref(false)
const grnPage      = ref(1)
const grnMeta      = ref({ current_page: 1, last_page: 1 })

const tabs = [
  { label: 'GRN Records', value: 'grn' },
  { label: 'Pending POs',  value: 'pending' },
]

const viewModal    = reactive({ open: false, grn: null })
const receiveModal = reactive({ open: false, po: null, items: [], notes: '', error: '', submitting: false })
const settleCredit = reactive({
  open: false, grn: null,
  payment_method: 'cash', settled_date: '', notes: '',
  error: '', submitting: false,
})
const directGRN    = reactive({
  open: false, supplier_id: '', supplier_ref: '',
  grn_date: new Date().toISOString().slice(0, 10),
  payment_method: 'cash', cheque_number: '', cheque_date: '', cheque_bank_name: '',
  credit_due_date: '',
  notes: '', items: [], error: '', submitting: false,
})

async function searchProducts(q) {
  const { data } = await axios.get('/api/products', { params: { search: q, per_page: 30 } })
  return (data.data ?? data).map(p => ({
    ...p,
    name: p.part_number || p.name,
    sub: p.name,
  }))
}

const blankDGItem = () => ({
  product_id: '', ordered_quantity: 1, unit_cost: 0, selling_price: 0,
  batch_number: '', expiry_date: '',
})

async function fetchGRNs() {
  loadingGRN.value = true
  const { data } = await axios.get('/api/purchases', {
    params: { 'statuses[]': ['received', 'partial'], page: grnPage.value, per_page: 20 },
  })
  grns.value    = data.data
  grnMeta.value = data.meta ?? { current_page: 1, last_page: 1 }
  loadingGRN.value = false
}

async function fetchPendingPOs() {
  loadingPending.value = true
  const { data } = await axios.get('/api/purchases', {
    params: { 'statuses[]': ['ordered', 'pending'], per_page: 100 },
  })
  pendingPOs.value     = data.data
  loadingPending.value = false
}

async function fetchSupport() {
  const { data } = await axios.get('/api/suppliers', { params: { per_page: 500 } })
  suppliers.value = data.data ?? data
}

async function viewGRN(grn) {
  const { data } = await axios.get(`/api/purchases/${grn.id}`)
  viewModal.grn  = data
  viewModal.open = true
}

function openReceive(po) {
  receiveModal.po    = po
  receiveModal.items = (po.items ?? []).map(i => ({
    ...i,
    _qty:    i.ordered_quantity ?? i.quantity,
    _cost:   i.unit_cost,
    _batch:  i.batch_number ?? '',
    _expiry: i.expiry_date ?? '',
  }))
  receiveModal.notes      = ''
  receiveModal.error      = ''
  receiveModal.submitting = false
  receiveModal.open       = true
}

async function submitReceive() {
  receiveModal.error      = ''
  receiveModal.submitting = true
  try {
    await axios.post(`/api/purchases/${receiveModal.po.id}/receive`, {
      notes: receiveModal.notes,
      items: receiveModal.items.map(i => ({
        id:           i.id,
        quantity:     i._qty,
        unit_cost:    i._cost,
        selling_price: i.selling_price,
        batch_number: i._batch || null,
        expiry_date:  i._expiry || null,
      })),
    })
    receiveModal.open = false
    fetchGRNs()
    fetchPendingPOs()
  } catch (e) {
    receiveModal.error = e.response?.data?.message ?? 'Failed to receive goods.'
  } finally {
    receiveModal.submitting = false
  }
}

function openDirectGRN() {
  Object.assign(directGRN, {
    open: true, supplier_id: '', supplier_ref: '',
    grn_date: new Date().toISOString().slice(0, 10),
    payment_method: 'cash', cheque_number: '', cheque_date: '', cheque_bank_name: '',
    credit_due_date: '',
    notes: '', items: [blankDGItem()], error: '', submitting: false,
  })
}

function prefillDG(item, opt) {
  if (opt) {
    item.unit_cost     = opt.purchase_price ?? 0
    item.selling_price = opt.selling_price  ?? 0
  }
}

async function submitDirectGRN() {
  directGRN.error = ''
  if (!directGRN.supplier_id) { directGRN.error = 'Please select a supplier.'; return }
  if (directGRN.items.some(i => !i.product_id)) { directGRN.error = 'All items need a product.'; return }
  directGRN.submitting = true
  try {
    await axios.post('/api/purchases', {
      supplier_id:      directGRN.supplier_id,
      supplier_ref:     directGRN.supplier_ref || null,
      payment_method:   directGRN.payment_method,
      cheque_number:    directGRN.cheque_number || null,
      cheque_date:      directGRN.cheque_date || null,
      cheque_bank_name: directGRN.cheque_bank_name || null,
      credit_due_date:  directGRN.credit_due_date || null,
      notes:            directGRN.notes || null,
      status:           'received',
      tax:              0,
      items:            directGRN.items.map(i => ({
        product_id:        i.product_id,
        ordered_quantity:  i.ordered_quantity,
        received_quantity: i.ordered_quantity,
        unit_cost:         i.unit_cost,
        selling_price:     i.selling_price || 0,
        batch_number:      i.batch_number || null,
        expiry_date:       i.expiry_date || null,
      })),
    })
    directGRN.open = false
    fetchGRNs()
  } catch (e) {
    directGRN.error = e.response?.data?.message ?? 'Failed to post GRN.'
  } finally {
    directGRN.submitting = false
  }
}

function openSettleCredit(grn) {
  Object.assign(settleCredit, {
    open: true, grn,
    payment_method: 'cash',
    settled_date: new Date().toISOString().slice(0, 10),
    notes: '', error: '', submitting: false,
  })
}

async function submitSettleCredit() {
  settleCredit.error      = ''
  settleCredit.submitting = true
  try {
    const { data } = await axios.post(`/api/purchases/${settleCredit.grn.id}/settle-credit`, {
      payment_method: settleCredit.payment_method,
      settled_date:   settleCredit.settled_date || null,
      notes:          settleCredit.notes || null,
    })
    settleCredit.open = false
    // Update the open view modal so the button reflects settled state
    if (viewModal.open && viewModal.grn?.id === data.id) {
      viewModal.grn.credit_settled_at = data.credit_settled_at
      viewModal.grn.credit_settlement_journal_id = data.credit_settlement_journal_id
    }
    fetchGRNs()
  } catch (e) {
    settleCredit.error = e.response?.data?.message ?? 'Failed to settle credit.'
  } finally {
    settleCredit.submitting = false
  }
}

function isOverdue(dateStr) {
  return dateStr && new Date(dateStr) < new Date()
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
function numFmt(n) { return Number(n || 0).toLocaleString('en-LK', { minimumFractionDigits: 2 }) }
function statusClass(s) {
  return {
    ordered:   'bg-blue-100 text-blue-700',
    pending:   'bg-yellow-100 text-yellow-700',
    received:  'bg-green-100 text-green-700',
    partial:   'bg-amber-100 text-amber-700',
    cancelled: 'bg-red-100 text-red-600',
  }[s] ?? 'bg-gray-100 text-gray-600'
}

watch(activeTab, (tab) => {
  if (tab === 'pending') fetchPendingPOs()
})

onMounted(() => { fetchGRNs(); fetchSupport() })
</script>
