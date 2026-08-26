<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function index()
    {
        $user = request()->user();
        $purchases = Purchase::with(['supplier:id,name', 'user:id,name', 'journalEntry:id,entry_number', 'items.product:id,name'])
            ->when(!$user->isAdmin(), fn($q) => $q->where('branch_id', $user->branch_id))
            ->when(request('search'), fn($q, $s) => $q->where('purchase_number', 'like', "%$s%"))
            ->when(request('supplier_id'), fn($q, $s) => $q->where('supplier_id', $s))
            ->when(request('status'), fn($q, $s) => $q->where('status', $s))
            ->when(request('statuses'), fn($q, $s) => $q->whereIn('status', (array) $s))
            ->when(request('payment_method'), fn($q, $m) => $q->where('payment_method', $m))
            ->when(request('date_from'), fn($q, $d) => $q->whereDate('purchased_at', '>=', $d))
            ->when(request('date_to'), fn($q, $d) => $q->whereDate('purchased_at', '<=', $d))
            ->latest('purchased_at')
            ->paginate(request('per_page', 20));
        return response()->json($purchases);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id'       => 'required|exists:suppliers,id',
            'supplier_ref'      => 'nullable|string|max:100',
            'expected_delivery' => 'nullable|date',
            'items'             => 'required|array|min:1',
            'items.*.product_id'                   => 'nullable|exists:products,id',
            'items.*.new_product.name'             => 'nullable|required_without:items.*.product_id|string|max:200',
            'items.*.new_product.sku'              => 'nullable|string|max:100',
            'items.*.new_product.part_category_id' => 'nullable|exists:part_categories,id',
            'items.*.new_product.vehicle_type_id'  => 'nullable|exists:vehicle_types,id',
            'items.*.new_product.brand_id'         => 'nullable|exists:brands,id',
            'items.*.new_product.model_id'         => 'nullable|exists:vehicle_models,id',
            'items.*.new_product.quality_type_id'  => 'nullable|exists:quality_types,id',
            'items.*.new_product.rack_location'    => 'nullable|string|max:50',
            'items.*.new_product.min_stock_level'  => 'nullable|integer|min:0',
            'items.*.new_product.image_url'        => 'nullable|url|max:1000',
            'items.*.new_product.image_public_id'  => 'nullable|string|max:255',
            'items.*.ordered_quantity'  => 'required|integer|min:1',
            'items.*.received_quantity' => 'nullable|integer|min:0',
            'items.*.unit_cost'         => 'required|numeric|min:0',
            'items.*.selling_price'     => 'nullable|numeric|min:0',
            'items.*.batch_number'      => 'nullable|string|max:100',
            'items.*.expiry_date'       => 'nullable|date',
            'tax'              => 'nullable|numeric|min:0',
            'status'           => 'required|in:ordered,pending,received,partial,cancelled',
            'payment_method'   => 'nullable|in:cash,bank_transfer,cheque,credit',
            'cheque_number'    => 'nullable|required_if:payment_method,cheque|string|max:50',
            'cheque_date'      => 'nullable|required_if:payment_method,cheque|date',
            'cheque_bank_name' => 'nullable|required_if:payment_method,cheque|string|max:100',
            'credit_due_date'  => 'nullable|required_if:payment_method,credit|date',
            'notes'            => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $subtotal = collect($data['items'])->sum(fn($i) => $i['unit_cost'] * $i['ordered_quantity']);
            $tax      = $data['tax'] ?? 0;

            $purchase = Purchase::create([
                'branch_id'       => $request->user()->branch_id,
                'purchase_number'   => 'PO-' . now()->format('Ymd') . '-' . str_pad(Purchase::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT),
                'supplier_id'       => $data['supplier_id'],
                'user_id'           => $request->user()->id,
                'subtotal'          => $subtotal,
                'tax'               => $tax,
                'total'             => $subtotal + $tax,
                'status'            => $data['status'],
                'payment_method'    => $data['payment_method'] ?? 'cash',
                'cheque_number'     => $data['cheque_number'] ?? null,
                'cheque_date'       => $data['cheque_date'] ?? null,
                'cheque_bank_name'  => $data['cheque_bank_name'] ?? null,
                'credit_due_date'   => $data['credit_due_date'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'supplier_ref'      => $data['supplier_ref'] ?? null,
                'expected_delivery' => $data['expected_delivery'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                // Create product on-the-fly if no existing product selected
                if (empty($item['product_id'])) {
                    $np   = $item['new_product'];
                    $last = Product::withTrashed()->max('id') ?? 0;
                    $sku  = !empty($np['sku']) ? $np['sku'] : str_pad($last + 1, 6, '0', STR_PAD_LEFT);
                    $product = Product::create([
                        'branch_id'        => $request->user()->branch_id,
                        'name'             => $np['name'],
                        'sku'              => $sku,
                        'part_category_id' => $np['part_category_id'] ?? null,
                        'vehicle_type_id'  => $np['vehicle_type_id']  ?? null,
                        'brand_id'         => $np['brand_id']          ?? null,
                        'model_id'         => $np['model_id']          ?? null,
                        'quality_type_id'  => $np['quality_type_id']   ?? null,
                        'rack_location'    => $np['rack_location']     ?? null,
                        'supplier_id'      => $data['supplier_id'],
                        'purchase_price'   => $item['unit_cost'],
                        'selling_price'    => 0,
                        'stock_quantity'   => 0,
                        'min_stock_level'  => $np['min_stock_level'] ?? 1,
                        'is_active'        => true,
                        'image'            => $np['image_url']        ?? null,
                        'image_public_id'  => $np['image_public_id']  ?? null,
                    ]);
                } else {
                    $product = Product::findOrFail($item['product_id']);
                    if (!$request->user()->isAdmin() && $product->branch_id !== $request->user()->branch_id) {
                        throw new \Exception("Product not available for your branch: {$product->name}");
                    }
                }

                $orderedQty  = $item['ordered_quantity'];
                $receivedQty = $item['received_quantity'] ?? $orderedQty;

                PurchaseItem::create([
                    'purchase_id'       => $purchase->id,
                    'product_id'        => $product->id,
                    'quantity'          => $orderedQty,
                    'ordered_quantity'  => $orderedQty,
                    'received_quantity' => $receivedQty,
                    'unit_cost'         => $item['unit_cost'],
                    'selling_price'     => $item['selling_price'] ?? 0,
                    'total'             => $item['unit_cost'] * $orderedQty,
                    'batch_number'      => $item['batch_number'] ?? null,
                    'expiry_date'       => $item['expiry_date'] ?? null,
                ]);

                $stockQtyToAdd = in_array($data['status'], ['received', 'partial']) ? $receivedQty : 0;
                if ($stockQtyToAdd > 0) {
                    $product->increment('stock_quantity', $stockQtyToAdd);
                    $updates = ['purchase_price' => $item['unit_cost']];
                    if (!empty($item['selling_price'])) {
                        $updates['selling_price'] = $item['selling_price'];
                    }
                    $product->update($updates);
                }
            }

            if (in_array($purchase->status, ['received', 'partial'])) {
                // For partial GRN, journal only covers value of goods actually received
                $receivedValue = collect($data['items'])->sum(function ($i) {
                    $qty = $i['received_quantity'] ?? $i['ordered_quantity'];
                    return $i['unit_cost'] * $qty;
                }) + ($data['tax'] ?? 0);

                $entry = $this->postPurchaseJournal($purchase, $receivedValue);
                $purchase->update(['journal_entry_id' => $entry->id]);
            }

            AuditLog::record('purchase_created', "Purchase {$purchase->purchase_number} created", $purchase);

            DB::commit();
            return response()->json($purchase->load(['items.product', 'supplier', 'user']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Purchase $purchase)
    {
        $this->authorizeBranch($purchase->branch_id);
        return response()->json($purchase->load([
            'items.product.partCategory:id,name',
            'items.product.vehicleType:id,name',
            'items.product.brand:id,name',
            'items.product.model:id,name',
            'supplier',
            'user:id,name',
            'journalEntry:id,entry_number',
        ]));
    }

    public function receive(Request $request, Purchase $purchase)
    {
        $this->authorizeBranch($purchase->branch_id);

        if ($purchase->status === 'received') {
            return response()->json(['message' => 'Purchase already received.'], 422);
        }

        $data = $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.id'            => 'required|exists:purchase_items,id',
            'items.*.quantity'      => 'required|integer|min:0',
            'items.*.unit_cost'     => 'required|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
            'notes'                 => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $subtotal = 0;
            foreach ($data['items'] as $row) {
                $item    = PurchaseItem::findOrFail($row['id']);
                $product = Product::findOrFail($item->product_id);

                $item->update([
                    'quantity'      => $row['quantity'],
                    'unit_cost'     => $row['unit_cost'],
                    'selling_price' => $row['selling_price'] ?? $item->selling_price,
                    'total'         => $row['unit_cost'] * $row['quantity'],
                ]);

                $subtotal += $item->total;

                if ($row['quantity'] > 0) {
                    $product->increment('stock_quantity', $row['quantity']);
                    $updates = ['purchase_price' => $row['unit_cost']];
                    if (!empty($row['selling_price'])) {
                        $updates['selling_price'] = $row['selling_price'];
                    }
                    $product->update($updates);
                }
            }

            $purchase->update([
                'status'   => 'received',
                'subtotal' => $subtotal,
                'total'    => $subtotal + $purchase->tax,
                'notes'    => $data['notes'] ?? $purchase->notes,
            ]);

            $entry = $this->postPurchaseJournal($purchase->fresh());
            $purchase->update(['journal_entry_id' => $entry->id]);

            AuditLog::record('purchase_received', "GRN received for {$purchase->purchase_number}", $purchase);

            DB::commit();
            return response()->json($purchase->load(['items.product', 'supplier', 'user']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancel(Purchase $purchase)
    {
        $this->authorizeBranch($purchase->branch_id);
        if (!in_array($purchase->status, ['ordered', 'pending'])) {
            return response()->json(['message' => 'Only ordered or pending POs can be cancelled.'], 422);
        }
        $purchase->update(['status' => 'cancelled']);
        AuditLog::record('purchase_cancelled', "Purchase {$purchase->purchase_number} cancelled", $purchase);
        return response()->json($purchase->fresh());
    }

    public function destroy(Purchase $purchase)
    {
        $this->authorizeBranch($purchase->branch_id);
        $purchase->delete();
        return response()->json(['message' => 'Purchase deleted']);
    }

    private function authorizeBranch(?int $branchId): void
    {
        $user = request()->user();
        if (!$user->isAdmin() && $user->branch_id !== $branchId) {
            abort(403, 'Forbidden for this branch.');
        }
    }

    private function postPurchaseJournal(Purchase $purchase, ?float $journalAmount = null): JournalEntry
    {
        $amount    = round($journalAmount ?? $purchase->total, 2);
        $inventory = Account::where('code', '1200')->first();
        $ap        = Account::where('code', '2000')->first();
        $paymentAccount = $this->paymentAccountByMethod($purchase->payment_method);

        if (!$inventory) {
            throw new \Exception('Inventory account (1200) not found.');
        }
        if ($purchase->payment_method === 'credit' && !$ap) {
            throw new \Exception('Accounts payable account (2000) not found for credit purchase.');
        }
        if (!in_array($purchase->payment_method, ['credit', 'cheque']) && !$paymentAccount) {
            throw new \Exception('Payment account not found for purchase payment method.');
        }

        $statusNote = $purchase->status === 'partial'
            ? ' (Partial GRN)'
            : '';

        $entry = JournalEntry::create([
            'entry_number'   => $this->nextEntryNumber(),
            'entry_date'     => $purchase->purchased_at ?? now(),
            'description'    => "Purchase {$purchase->purchase_number}{$statusNote}",
            'reference_type' => 'Purchase',
            'reference_id'   => $purchase->id,
            'branch_id'      => $purchase->branch_id,
            'created_by'     => auth()->id(),
            'status'         => 'posted',
        ]);

        // Dr Inventory — goods received into stock
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $inventory->id,
            'debit'            => $amount,
            'credit'           => 0,
            'description'      => 'Inventory received' . $statusNote,
        ]);

        // Cr Cash / Bank / Cheques Payable / Accounts Payable
        $creditAccountId = ($purchase->payment_method === 'credit') ? $ap->id : $paymentAccount->id;
        $creditDesc      = ($purchase->payment_method === 'credit')
            ? 'Supplier payable recorded' . ($purchase->credit_due_date ? ' — due ' . $purchase->credit_due_date->format('d M Y') : '')
            : 'Purchase paid';

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $creditAccountId,
            'debit'            => 0,
            'credit'           => $amount,
            'description'      => $creditDesc,
        ]);

        return $entry;
    }

    public function settleChequePurchase(Request $request, Purchase $purchase)
    {
        $this->authorizeBranch($purchase->branch_id);

        if ($purchase->payment_method !== 'cheque') {
            return response()->json(['message' => 'This purchase is not a cheque payment.'], 422);
        }
        if ($purchase->cheque_settled_at) {
            return response()->json(['message' => 'Cheque already settled.'], 422);
        }

        $data = $request->validate([
            'settled_date' => 'nullable|date',
            'notes'        => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $chequesPayable = Account::where('code', '2050')->first();
            $bank           = Account::where('code', '1010')->first();

            if (!$chequesPayable || !$bank) {
                throw new \Exception('Cheques Payable (2050) or Bank Account (1010) not found.');
            }

            $settledAt = isset($data['settled_date']) ? $data['settled_date'] : now();

            $entry = JournalEntry::create([
                'entry_number'   => $this->nextEntryNumber(),
                'entry_date'     => $settledAt,
                'description'    => "Cheque settled – {$purchase->purchase_number} (#{$purchase->cheque_number}, {$purchase->cheque_bank_name})",
                'reference_type' => 'Purchase',
                'reference_id'   => $purchase->id,
                'branch_id'      => $purchase->branch_id,
                'created_by'     => auth()->id(),
                'status'         => 'posted',
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $chequesPayable->id,
                'debit'            => $purchase->total,
                'credit'           => 0,
                'description'      => "Cheque payable cleared – {$purchase->purchase_number}",
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $bank->id,
                'debit'            => 0,
                'credit'           => $purchase->total,
                'description'      => "Bank payment – cheque #{$purchase->cheque_number}",
            ]);

            $purchase->update([
                'cheque_settled_at'    => $settledAt,
                'settlement_journal_id'=> $entry->id,
            ]);

            AuditLog::record('cheque_settled', "Cheque settled for {$purchase->purchase_number}", $purchase);

            DB::commit();
            return response()->json($purchase->fresh());
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function settleCreditPurchase(Request $request, Purchase $purchase)
    {
        $this->authorizeBranch($purchase->branch_id);

        if ($purchase->payment_method !== 'credit') {
            return response()->json(['message' => 'This purchase is not a credit payment.'], 422);
        }
        if ($purchase->credit_settled_at) {
            return response()->json(['message' => 'Credit already settled.'], 422);
        }

        $data = $request->validate([
            'payment_method' => 'required|in:cash,bank_transfer,cheque',
            'settled_date'   => 'nullable|date',
            'notes'          => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $ap      = Account::where('code', '2000')->first();
            $payAcct = $this->paymentAccountByMethod($data['payment_method']);

            if (!$ap)      throw new \Exception('Accounts Payable account (2000) not found.');
            if (!$payAcct) throw new \Exception('Payment account not found for selected payment method.');

            $settledAt = $data['settled_date'] ?? now();

            $entry = JournalEntry::create([
                'entry_number'   => $this->nextEntryNumber(),
                'entry_date'     => $settledAt,
                'description'    => "Credit settled – {$purchase->purchase_number} (supplier: {$purchase->supplier?->name})",
                'reference_type' => 'Purchase',
                'reference_id'   => $purchase->id,
                'branch_id'      => $purchase->branch_id,
                'created_by'     => auth()->id(),
                'status'         => 'posted',
            ]);

            // Dr Accounts Payable — clears the liability
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $ap->id,
                'debit'            => $purchase->total,
                'credit'           => 0,
                'description'      => "Credit payable cleared – {$purchase->purchase_number}",
            ]);

            // Cr Cash / Bank
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $payAcct->id,
                'debit'            => 0,
                'credit'           => $purchase->total,
                'description'      => "Paid to supplier – {$purchase->purchase_number}",
            ]);

            $purchase->update([
                'credit_settled_at'            => $settledAt,
                'credit_settlement_journal_id' => $entry->id,
            ]);

            AuditLog::record('credit_settled', "Credit settled for {$purchase->purchase_number}", $purchase);

            DB::commit();
            return response()->json($purchase->fresh());
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function paymentAccountByMethod(string $method): ?Account
    {
        if ($method === 'cash') {
            return Account::where('code', '1000')->first();
        }

        if ($method === 'bank_transfer') {
            return Account::where('code', '1010')->first();
        }

        // Cheques go to Cheques Payable (2050); settled separately via settleChequePurchase
        if ($method === 'cheque') {
            return Account::where('code', '2050')->first();
        }

        return null;
    }

    private function nextEntryNumber(): string
    {
        $seq = JournalEntry::whereDate('created_at', today())->withTrashed()->count() + 1;
        return 'JE-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
