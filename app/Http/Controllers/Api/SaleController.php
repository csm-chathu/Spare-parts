<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ShopSetting;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index()
    {
        $user = request()->user();
        $statusFilter = request('status');
        $sales = Sale::with(['customer:id,name,phone,vehicle_number', 'user:id,name', 'journalEntry:id,entry_number'])
            ->when(!$user->isAdmin(), fn($q) => $q->where('branch_id', $user->branch_id))
            ->when(request('search'), fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('invoice_number', 'like', "%$s%")
                  ->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', "%$s%")
                      ->orWhere('vehicle_number', 'like', "%$s%")
                      ->orWhere('phone', 'like', "%$s%"));
            }))
            ->when(request('customer_id'), fn($q, $c) => $q->where('customer_id', $c))
            ->when($statusFilter === 'draft', fn($q) => $q->where('is_draft', true))
            ->when($statusFilter && $statusFilter !== 'draft', fn($q) => $q->where('payment_status', $statusFilter)->where('is_draft', false))
            ->when(!$statusFilter, fn($q) => $q)
            ->when(request('sale_type'), fn($q, $s) => $q->where('sale_type', $s))
            ->when(request('date_from'), fn($q, $d) => $q->whereDate('sold_at', '>=', $d))
            ->when(request('date_to'), fn($q, $d) => $q->whereDate('sold_at', '<=', $d))
            ->latest('sold_at')
            ->paginate(request('per_page', 20));
        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'        => 'nullable|exists:customers,id',
            'items'               => 'required|array|min:1',
            'items.*.type'        => 'nullable|in:part,labour,other',
            'items.*.product_id'  => 'nullable|exists:products,id',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.quantity'    => 'required|integer|min:1',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.discount'    => 'nullable|numeric|min:0',
            'discount'            => 'nullable|numeric|min:0',
            'tax'                 => 'nullable|numeric|min:0',
            'tax_rate'            => 'nullable|numeric|min:0|max:100',
            'maintenance_amount'  => 'nullable|numeric|min:0',
            'payment_method'      => 'required|in:cash,card,bank_transfer,cheque,other',
            'payment_status'      => 'required|in:pending,paid,partial,refunded',
            'sale_type'           => 'nullable|in:instant,booking',
            'booking_expires_at'  => 'nullable|date',
            'amount_paid'         => 'required|numeric|min:0',
            'notes'               => 'nullable|string',
            'sold_at'             => 'nullable|date',
            'is_draft'            => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $isDraft  = !empty($data['is_draft']);
            $subtotal = 0;
            $itemData = [];

            foreach ($data['items'] as $item) {
                $itemType = $item['type'] ?? 'part';
                $product  = null;

                if ($itemType === 'part' && !empty($item['product_id'])) {
                    $product = Product::findOrFail($item['product_id']);

                    if (!$request->user()->isAdmin() && $product->branch_id !== $request->user()->branch_id) {
                        throw new \Exception("Product not available for your branch: {$product->name}");
                    }
                    if (!$isDraft && $product->stock_quantity < $item['quantity']) {
                        throw new \Exception("Insufficient stock for: {$product->name}");
                    }
                }

                $qty         = $item['quantity'];
                $unitPrice   = $item['unit_price'];
                $itemDisc    = $item['discount'] ?? 0;
                $lineTotal   = ($unitPrice * $qty) - $itemDisc;
                $description = $item['description'] ?? ($product?->name ?? '');

                $subtotal += $lineTotal;
                $itemData[] = compact('product', 'itemType', 'description', 'qty', 'unitPrice', 'itemDisc', 'lineTotal');
            }

            $discount          = $data['discount'] ?? 0;
            $tax               = $data['tax'] ?? 0;
            $maintenanceAmount = $data['maintenance_amount'] ?? 0;
            $total             = $subtotal - $discount + $tax + $maintenanceAmount;
            $amountPaid        = (float) ($data['amount_paid'] ?? 0);
            $saleType          = $data['sale_type'] ?? 'instant';
            $soldAt            = !empty($data['sold_at']) ? Carbon::parse($data['sold_at']) : now();

            $bookingExpiresAt = null;
            $deliveryStatus   = 'delivered';
            $deliveredAt      = $soldAt;

            if ($saleType === 'booking') {
                if (empty($data['customer_id'])) {
                    throw new \Exception('Customer is required for booking sales.');
                }

                $bookingExpiresAt = !empty($data['booking_expires_at'])
                    ? Carbon::parse($data['booking_expires_at'])
                    : $soldAt->copy()->addMonths(3);

                if ($bookingExpiresAt->gt($soldAt->copy()->addMonths(3))) {
                    throw new \Exception('Booking expiry must be within 3 months from sale date.');
                }

                $deliveryStatus = 'booked';
                $deliveredAt    = null;
            }

            if (!$request->user()->isAdmin() && !empty($data['customer_id'])) {
                $customer = Customer::findOrFail($data['customer_id']);
                if ($customer->branch_id !== $request->user()->branch_id) {
                    throw new \Exception('Selected customer does not belong to your branch.');
                }
            }

            $invoiceNumber  = $isDraft
                ? 'DRAFT-' . now()->format('Ymd') . '-' . str_pad(Sale::where('invoice_number', 'like', 'DRAFT-' . now()->format('Ymd') . '-%')->withTrashed()->count() + 1, 4, '0', STR_PAD_LEFT)
                : $this->nextInvoiceNumber();

            $sale = Sale::create([
                'branch_id'          => $request->user()->branch_id,
                'invoice_number'     => $invoiceNumber,
                'customer_id'        => $data['customer_id'] ?? null,
                'user_id'            => $request->user()->id,
                'subtotal'           => $subtotal,
                'discount'           => $discount,
                'tax'                => $tax,
                'tax_rate'           => $data['tax_rate'] ?? 0,
                'maintenance_amount' => $maintenanceAmount,
                'total'              => $total,
                'payment_method'     => $data['payment_method'],
                'payment_status'     => $data['payment_status'],
                'sale_type'          => $saleType,
                'delivery_status'    => $deliveryStatus,
                'booking_expires_at' => $bookingExpiresAt,
                'delivered_at'       => $deliveredAt,
                'amount_paid'        => $amountPaid,
                'notes'              => $data['notes'] ?? null,
                'sold_at'            => $soldAt,
                'is_draft'           => $isDraft,
            ]);

            foreach ($itemData as $i) {
                SaleItem::create([
                    'sale_id'     => $sale->id,
                    'product_id'  => $i['product']?->id,
                    'type'        => $i['itemType'],
                    'description' => $i['description'],
                    'quantity'    => $i['qty'],
                    'unit_price'  => $i['unitPrice'],
                    'discount'    => $i['itemDisc'],
                    'total'       => $i['lineTotal'],
                ]);
                if (!$isDraft && $i['product']) {
                    $i['product']->decrement('stock_quantity', $i['qty']);
                }
            }

            if (!$isDraft) {
                if ($saleType === 'instant') {
                    $entry = $this->postInstantSaleJournal($sale, $itemData);
                    $sale->update(['journal_entry_id' => $entry->id]);
                } elseif ($amountPaid > 0) {
                    $entry = $this->postBookingAdvanceJournal($sale);
                    $sale->update(['journal_entry_id' => $entry->id]);
                }
            }

            AuditLog::record('sale_created', ($isDraft ? 'Draft' : 'Sale') . " {$sale->invoice_number} — LKR {$total}", $sale);

            DB::commit();
            return response()->json($sale->load(['items.product', 'customer', 'user']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Sale $sale)
    {
        $this->authorizeBranch($sale->branch_id);
        return response()->json($sale->load(['items.product', 'customer:id,name,phone,email,vehicle_number', 'user', 'journalEntry']));
    }

    public function settleBooking(Request $request, Sale $sale)
    {
        $this->authorizeBranch($sale->branch_id);

        if ($sale->sale_type !== 'booking') {
            return response()->json(['message' => 'Only booking sales can be settled with this endpoint.'], 422);
        }
        if ($sale->delivery_status !== 'booked') {
            return response()->json(['message' => 'This booking is already delivered or cancelled.'], 422);
        }
        if ($sale->booking_expires_at && now()->startOfDay()->gt($sale->booking_expires_at)) {
            return response()->json(['message' => 'Booking period has expired. Please create a new sale.'], 422);
        }

        $data = $request->validate([
            'payment_method' => 'required|in:cash,card,bank_transfer,cheque,other',
            'payment_amount' => 'required|numeric|min:0',
            'delivered_at'   => 'nullable|date',
            'notes'          => 'nullable|string|max:1000',
        ]);

        $remaining     = round(max(0, $sale->total - $sale->amount_paid), 2);
        $paymentAmount = round((float) $data['payment_amount'], 2);

        if (abs($paymentAmount - $remaining) > 0.01) {
            return response()->json(['message' => "Payment amount must equal remaining balance (LKR {$remaining})."], 422);
        }

        DB::beginTransaction();
        try {
            $entry = $this->postBookingSettlementJournal($sale, $paymentAmount, $data['payment_method']);

            $sale->update([
                'amount_paid'      => $sale->total,
                'payment_status'   => 'paid',
                'payment_method'   => $data['payment_method'],
                'delivery_status'  => 'delivered',
                'delivered_at'     => !empty($data['delivered_at']) ? Carbon::parse($data['delivered_at']) : now(),
                'journal_entry_id' => $entry->id,
                'notes'            => $data['notes'] ?? $sale->notes,
            ]);

            AuditLog::record('sale_booking_settled', "Booking {$sale->invoice_number} settled and delivered", $sale);
            DB::commit();

            return response()->json($sale->fresh(['items.product', 'customer', 'user', 'journalEntry']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function settle(Request $request, Sale $sale)
    {
        $this->authorizeBranch($sale->branch_id);

        if (!in_array($sale->payment_status, ['pending', 'partial'])) {
            return response()->json(['message' => 'This sale is already fully paid.'], 422);
        }
        if ($sale->is_draft) {
            return response()->json(['message' => 'Cannot settle a draft sale.'], 422);
        }

        $data = $request->validate([
            'payment_method' => 'required|in:cash,card,bank_transfer,cheque,other',
            'amount_received' => 'required|numeric|min:0.01',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $remaining     = round(max(0, $sale->total - $sale->amount_paid), 2);
        $amountReceived = round((float) $data['amount_received'], 2);

        if ($amountReceived > $remaining + 0.01) {
            return response()->json(['message' => "Amount received (LKR {$amountReceived}) exceeds balance due (LKR {$remaining})."], 422);
        }

        $newAmountPaid = round($sale->amount_paid + $amountReceived, 2);
        $newStatus     = ($newAmountPaid >= $sale->total - 0.01) ? 'paid' : 'partial';

        DB::beginTransaction();
        try {
            $sale->update([
                'amount_paid'    => $newAmountPaid,
                'payment_status' => $newStatus,
                'payment_method' => $data['payment_method'],
                'notes'          => $data['notes'] ?? $sale->notes,
            ]);

            AuditLog::record('sale_settled', "Payment of LKR {$amountReceived} received for {$sale->invoice_number}. Status: {$newStatus}", $sale);
            DB::commit();

            return response()->json($sale->fresh(['items.product', 'customer', 'user']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function publicView(string $token)
    {
        $sale = Sale::with(['items.product:id,name,sku', 'customer:id,name,phone,email,vehicle_number'])
            ->where('view_token', $token)
            ->firstOrFail();

        $shop = ShopSetting::first();

        return response()->json([
            'sale' => $sale,
            'shop' => $shop ? [
                'shop_name' => $shop->shop_name,
                'address'   => $shop->address,
                'phone'     => $shop->phone,
                'logo_url'  => $shop->logo_url,
            ] : [],
        ]);
    }

    public function sendSms(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'phone'   => 'nullable|string|max:20',
            'message' => 'nullable|string|max:500',
        ]);

        $phone = trim($data['phone'] ?? '');
        if (!$phone && $sale->customer_id) {
            $phone = optional($sale->customer ?? $sale->load('customer')->customer)->phone ?? '';
        }
        if (!$phone) {
            return response()->json(['message' => 'Please enter a phone number to send the SMS to.'], 422);
        }

        $shopName = optional(ShopSetting::first())->shop_name ?? 'Our Shop';
        $viewUrl  = rtrim(config('app.url'), '/') . '/receipt/' . $sale->view_token;
        $saleType = $sale->sale_type === 'booking' ? 'Booking advance' : 'Invoice';
        $custName = $sale->customer->name ?? 'Customer';

        $message = $data['message'] ?? (
            "Dear {$custName}, {$saleType} {$sale->invoice_number} of LKR " .
            number_format($sale->total, 2) . " has been created. " .
            "View receipt: {$viewUrl} . Thank you! - {$shopName}"
        );

        $smsService = app(SmsService::class);
        $result     = $smsService->sendSingle($phone, $message);

        if (!$result['success']) {
            return response()->json(['message' => 'SMS could not be delivered. Check SMS gateway settings.'], 422);
        }

        return response()->json(['message' => 'SMS sent successfully.', 'phone' => $phone]);
    }

    public function update(Request $request, Sale $sale)
    {
        $this->authorizeBranch($sale->branch_id);

        if (!$sale->is_draft) {
            return response()->json(['message' => 'Only draft sales can be edited.'], 422);
        }

        $data = $request->validate([
            'customer_id'         => 'nullable|exists:customers,id',
            'items'               => 'required|array|min:1',
            'items.*.type'        => 'nullable|in:part,labour,other',
            'items.*.product_id'  => 'nullable|exists:products,id',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.quantity'    => 'required|integer|min:1',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.discount'    => 'nullable|numeric|min:0',
            'discount'            => 'nullable|numeric|min:0',
            'tax'                 => 'nullable|numeric|min:0',
            'tax_rate'            => 'nullable|numeric|min:0|max:100',
            'maintenance_amount'  => 'nullable|numeric|min:0',
            'payment_method'      => 'required|in:cash,card,bank_transfer,cheque,other',
            'payment_status'      => 'required|in:pending,paid,partial,refunded',
            'sale_type'           => 'nullable|in:instant,booking',
            'amount_paid'         => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string',
            'sold_at'             => 'nullable|date',
        ]);

        DB::beginTransaction();
        try {
            $subtotal = 0;
            $itemData = [];

            foreach ($data['items'] as $item) {
                $qty         = $item['quantity'];
                $unitPrice   = $item['unit_price'];
                $itemDisc    = $item['discount'] ?? 0;
                $lineTotal   = ($unitPrice * $qty) - $itemDisc;
                $product     = !empty($item['product_id']) ? Product::find($item['product_id']) : null;
                $description = $item['description'] ?? ($product?->name ?? '');
                $subtotal += $lineTotal;
                $itemData[] = [
                    'product_id'  => $item['product_id'] ?? null,
                    'type'        => $item['type'] ?? 'part',
                    'description' => $description,
                    'quantity'    => $qty,
                    'unit_price'  => $unitPrice,
                    'discount'    => $itemDisc,
                    'total'       => $lineTotal,
                ];
            }

            $discount          = $data['discount'] ?? 0;
            $tax               = $data['tax'] ?? 0;
            $maintenanceAmount = $data['maintenance_amount'] ?? 0;
            $total             = $subtotal - $discount + $tax + $maintenanceAmount;

            $sale->items()->delete();

            foreach ($itemData as $row) {
                SaleItem::create(array_merge(['sale_id' => $sale->id], $row));
            }

            $sale->update([
                'customer_id'        => $data['customer_id'] ?? null,
                'subtotal'           => $subtotal,
                'discount'           => $discount,
                'tax'                => $tax,
                'tax_rate'           => $data['tax_rate'] ?? 0,
                'maintenance_amount' => $maintenanceAmount,
                'total'              => $total,
                'payment_method'     => $data['payment_method'],
                'payment_status'     => $data['payment_status'],
                'sale_type'          => $data['sale_type'] ?? $sale->sale_type,
                'amount_paid'        => $data['amount_paid'] ?? 0,
                'notes'              => $data['notes'] ?? null,
                'sold_at'            => !empty($data['sold_at']) ? Carbon::parse($data['sold_at']) : $sale->sold_at,
            ]);

            AuditLog::record('draft_updated', "Draft {$sale->invoice_number} updated — LKR {$total}", $sale);
            DB::commit();

            return response()->json($sale->fresh(['items.product', 'customer', 'user']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function finalize(Sale $sale)
    {
        $this->authorizeBranch($sale->branch_id);

        if (!$sale->is_draft) {
            return response()->json(['message' => 'This sale is already finalized.'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($sale->items as $item) {
                if ($item->product->stock_quantity < $item->quantity) {
                    throw new \Exception("Insufficient stock for: {$item->product->name}");
                }
            }

            $invoiceNumber = $this->nextInvoiceNumber();

            foreach ($sale->items as $item) {
                $item->product->decrement('stock_quantity', $item->quantity);
            }

            $sale->update([
                'is_draft'       => false,
                'invoice_number' => $invoiceNumber,
                'sold_at'        => $sale->sold_at ?? now(),
            ]);

            $itemData = $sale->items->map(fn($si) => [
                'product'   => $si->product,
                'qty'       => $si->quantity,
                'unitPrice' => $si->unit_price,
                'itemDisc'  => $si->discount,
                'lineTotal'  => $si->total,
            ])->all();

            if ($sale->sale_type === 'instant') {
                $entry = $this->postInstantSaleJournal($sale, $itemData);
                $sale->update(['journal_entry_id' => $entry->id]);
            } elseif ($sale->amount_paid > 0) {
                $entry = $this->postBookingAdvanceJournal($sale);
                $sale->update(['journal_entry_id' => $entry->id]);
            }

            AuditLog::record('sale_finalized', "Draft {$sale->invoice_number} finalized", $sale);
            DB::commit();

            return response()->json($sale->fresh(['items.product', 'customer', 'user']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Sale $sale)
    {
        $this->authorizeBranch($sale->branch_id);
        if (!$sale->is_draft && !request()->user()->canDeleteTransactions()) {
            abort(403, 'You do not have permission to delete transactions.');
        }
        DB::beginTransaction();
        try {
            if (!$sale->is_draft) {
                foreach ($sale->items as $item) {
                    $item->product->increment('stock_quantity', $item->quantity);
                }
            }
            AuditLog::record('sale_deleted', "Sale {$sale->invoice_number} deleted" . ($sale->is_draft ? ' (draft)' : ', stock restored'), $sale,
                ['invoice' => $sale->invoice_number, 'total' => $sale->total]);
            $sale->delete();
            DB::commit();
            return response()->json(['message' => $sale->is_draft ? 'Draft deleted' : 'Sale deleted and stock restored']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function authorizeBranch(?int $branchId): void
    {
        $user = request()->user();
        if (!$user->isAdmin() && $user->branch_id !== $branchId) {
            abort(403, 'Forbidden for this branch.');
        }
    }

    private function paymentAccountByMethod(string $method): ?Account
    {
        if ($method === 'cash') {
            return Account::where('code', '1000')->first();
        }
        if (in_array($method, ['bank_transfer', 'card', 'cheque', 'other'])) {
            return Account::where('code', '1010')->first();
        }
        return null;
    }

    private function nextEntryNumber(): string
    {
        $seq = JournalEntry::whereDate('created_at', today())->withTrashed()->count() + 1;
        return 'JE-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    private function nextInvoiceNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ymd') . '-';
        $last = Sale::where('invoice_number', 'like', $prefix . '%')
            ->withTrashed()
            ->lockForUpdate()
            ->orderByDesc('invoice_number')
            ->value('invoice_number');
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    private function postInstantSaleJournal(Sale $sale, array $itemData = []): JournalEntry
    {
        $revenue    = Account::where('code', '4000')->first();
        $cogs       = Account::where('code', '5000')->first();
        $inventory  = Account::where('code', '1200')->first();
        $receivable = Account::where('code', '1100')->first();
        $paidAccount = $this->paymentAccountByMethod($sale->payment_method);

        if (!$revenue) throw new \Exception('Revenue account (4000) is missing.');

        $paid = round(min($sale->amount_paid, $sale->total), 2);
        $due  = round(max(0, $sale->total - $paid), 2);

        if ($paid > 0 && !$paidAccount) throw new \Exception('Payment account could not be resolved for this method.');
        if ($due > 0 && !$receivable)   throw new \Exception('Accounts receivable account (1100) is missing.');

        $entry = JournalEntry::create([
            'entry_number'   => $this->nextEntryNumber(),
            'entry_date'     => $sale->sold_at,
            'description'    => "Sales invoice {$sale->invoice_number}",
            'reference_type' => 'Sale',
            'reference_id'   => $sale->id,
            'branch_id'      => $sale->branch_id,
            'created_by'     => auth()->id(),
            'status'         => 'posted',
        ]);

        if ($paid > 0) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $paidAccount->id,
                'debit'            => $paid,
                'credit'           => 0,
                'description'      => 'Cash/Bank received for sale',
            ]);
        }
        if ($due > 0) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $receivable->id,
                'debit'            => $due,
                'credit'           => 0,
                'description'      => 'Accounts receivable for sale',
            ]);
        }

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $revenue->id,
            'debit'            => 0,
            'credit'           => $sale->total,
            'description'      => 'Sales revenue',
        ]);

        // COGS: Dr Cost of Goods Sold / Cr Inventory
        if ($cogs && $inventory) {
            $costTotal = round(collect($itemData)->sum(
                fn($i) => ($i['product']->purchase_price ?? 0) * $i['qty']
            ), 2);

            if ($costTotal <= 0) {
                // Fallback: load from DB if itemData not passed
                $costTotal = round($sale->items()->with('product:id,purchase_price')->get()->sum(
                    fn($si) => ($si->product->purchase_price ?? 0) * $si->quantity
                ), 2);
            }

            if ($costTotal > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $cogs->id,
                    'debit'            => $costTotal,
                    'credit'           => 0,
                    'description'      => 'Cost of goods sold',
                ]);
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $inventory->id,
                    'debit'            => 0,
                    'credit'           => $costTotal,
                    'description'      => 'Inventory reduced for sale',
                ]);
            }
        }

        return $entry;
    }

    private function postBookingAdvanceJournal(Sale $sale): JournalEntry
    {
        $deposit     = Account::where('code', '2200')->first();
        $paidAccount = $this->paymentAccountByMethod($sale->payment_method);

        if (!$deposit || !$paidAccount) {
            throw new \Exception('Required accounts for booking advance (2200 and payment account) are missing.');
        }

        $entry = JournalEntry::create([
            'entry_number'   => $this->nextEntryNumber(),
            'entry_date'     => $sale->sold_at,
            'description'    => "Advance received for booking {$sale->invoice_number}",
            'reference_type' => 'SaleBookingAdvance',
            'reference_id'   => $sale->id,
            'branch_id'      => $sale->branch_id,
            'created_by'     => auth()->id(),
            'status'         => 'posted',
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $paidAccount->id,
            'debit'            => $sale->amount_paid,
            'credit'           => 0,
            'description'      => 'Advance received from customer',
        ]);
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $deposit->id,
            'debit'            => 0,
            'credit'           => $sale->amount_paid,
            'description'      => 'Customer deposit liability',
        ]);

        return $entry;
    }

    private function postBookingSettlementJournal(Sale $sale, float $paymentAmount, string $paymentMethod): JournalEntry
    {
        $deposit     = Account::where('code', '2200')->first();
        $revenue     = Account::where('code', '4000')->first();
        $cogs        = Account::where('code', '5000')->first();
        $inventory   = Account::where('code', '1200')->first();
        $paidAccount = $this->paymentAccountByMethod($paymentMethod);

        if (!$deposit || !$revenue || !$paidAccount) {
            throw new \Exception('Required accounts for booking settlement are missing.');
        }

        $entry = JournalEntry::create([
            'entry_number'   => $this->nextEntryNumber(),
            'entry_date'     => now(),
            'description'    => "Booking settled {$sale->invoice_number}",
            'reference_type' => 'SaleBookingSettlement',
            'reference_id'   => $sale->id,
            'branch_id'      => $sale->branch_id,
            'created_by'     => auth()->id(),
            'status'         => 'posted',
        ]);

        if ($paymentAmount > 0) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $paidAccount->id,
                'debit'            => $paymentAmount,
                'credit'           => 0,
                'description'      => 'Final payment received for booking',
            ]);
        }
        if ($sale->amount_paid > 0) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $deposit->id,
                'debit'            => $sale->amount_paid,
                'credit'           => 0,
                'description'      => 'Customer deposit cleared',
            ]);
        }

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $revenue->id,
            'debit'            => 0,
            'credit'           => $sale->total,
            'description'      => 'Sales revenue recognized on delivery',
        ]);

        // COGS: Dr Cost of Goods Sold / Cr Inventory on delivery
        if ($cogs && $inventory) {
            $costTotal = round($sale->items()->with('product:id,purchase_price')->get()->sum(
                fn($si) => ($si->product->purchase_price ?? 0) * $si->quantity
            ), 2);

            if ($costTotal > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $cogs->id,
                    'debit'            => $costTotal,
                    'credit'           => 0,
                    'description'      => 'Cost of goods sold on delivery',
                ]);
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $inventory->id,
                    'debit'            => 0,
                    'credit'           => $costTotal,
                    'description'      => 'Inventory reduced on delivery',
                ]);
            }
        }

        return $entry;
    }
}
