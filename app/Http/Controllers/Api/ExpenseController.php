<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Account;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with(['paidByUser:id,name,email', 'createdByUser:id,name', 'branch:id,name,code']);

        // Filter by date range
        if ($request->from_date) {
            $query->where('expense_date', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->where('expense_date', '<=', $request->to_date);
        }

        // Filter by category
        if ($request->category && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        // Filter by payment method
        if ($request->payment_method) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by branch
        if ($request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        }

        // Search by description
        if ($request->search) {
            $query->whereRaw('MATCH(description) AGAINST(? IN BOOLEAN MODE)', ["{$request->search}*"]);
        }

        $expenses = $query->latest('expense_date')->paginate($request->per_page ?? 20);

        return response()->json($expenses);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_date'      => 'required|date',
            'category'          => 'required|in:' . implode(',', array_keys(Expense::CATEGORIES)),
            'description'       => 'required|string|max:500',
            'amount'            => 'required|numeric|min:0.01',
            'payment_method'    => 'required|in:' . implode(',', Expense::PAYMENT_METHODS),
            'reference_number'  => 'nullable|string|max:100',
            'paid_by_user_id'   => 'required|exists:users,id',
            'branch_id'         => 'nullable|exists:branches,id',
            'notes'             => 'nullable|string|max:1000',
        ]);

        $data['created_by_user_id'] = $request->user()->id;

        $expense = Expense::create($data);

        // Post to General Ledger
        try {
            $entry = $this->postExpenseJournal($expense);
            $expense->update(['journal_entry_id' => $entry->id]);
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Log::error('Expense GL posting failed: ' . $e->getMessage(), ['expense_id' => $expense->id]);
        }

        AuditLog::record(
            'expense_created',
            "Created expense: {$expense->description} ({$expense->category}) - LKR {$expense->amount}",
            $expense
        );

        return response()->json($expense->load(['paidByUser:id,name', 'createdByUser:id,name', 'branch:id,name']), 201);
    }

    public function show(Expense $expense)
    {
        return response()->json($expense->load(['paidByUser:id,name,email', 'createdByUser:id,name', 'branch:id,name,code']));
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $request->validate([
            'expense_date'      => 'sometimes|date',
            'category'          => 'sometimes|in:' . implode(',', array_keys(Expense::CATEGORIES)),
            'description'       => 'sometimes|string|max:500',
            'amount'            => 'sometimes|numeric|min:0.01',
            'payment_method'    => 'sometimes|in:' . implode(',', Expense::PAYMENT_METHODS),
            'reference_number'  => 'nullable|string|max:100',
            'paid_by_user_id'   => 'sometimes|exists:users,id',
            'branch_id'         => 'nullable|exists:branches,id',
            'notes'             => 'nullable|string|max:1000',
        ]);

        $old = $expense->only(array_keys($data));
        $expense->update($data);

        AuditLog::record(
            'expense_updated',
            "Updated expense: {$expense->description}",
            $expense,
            $old,
            $expense->fresh()->only(array_keys($old))
        );

        return response()->json($expense->load(['paidByUser:id,name', 'createdByUser:id,name', 'branch:id,name']));
    }

    public function destroy(Request $request, Expense $expense)
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Only admins can delete expenses');
        }

        AuditLog::record('expense_deleted', "Deleted expense: {$expense->description}", $expense);
        $expense->delete();

        return response()->json(['message' => 'Expense deleted']);
    }

    public function summary(Request $request)
    {
        $query = Expense::query();

        if ($request->from_date) {
            $query->where('expense_date', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->where('expense_date', '<=', $request->to_date);
        }
        if ($request->category) {
            $query->where('category', $request->category);
        }
        if ($request->payment_method) {
            $query->where('payment_method', $request->payment_method);
        }
        if ($request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        }

        $byCategory = $query->selectRaw('category, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('category')
            ->orderByRaw('SUM(amount) DESC')
            ->get();

        $total = $query->sum('amount');

        return response()->json(['by_category' => $byCategory, 'grand_total' => $total]);
    }

    private function postExpenseJournal(Expense $expense): JournalEntry
    {
        // Get expense account by category code
        $categoryCode = Expense::CATEGORY_ACCOUNT_CODES[$expense->category] ?? '5999';
        $expenseAccount = Account::where('code', $categoryCode)->first();
        
        // Get payment account by method
        $paidAccount = $this->paymentAccountByMethod($expense->payment_method);

        if (!$expenseAccount) {
            throw new \Exception("Expense account ({$categoryCode}) not found for category: {$expense->category}");
        }
        if (!$paidAccount) {
            throw new \Exception("Payment account not found for method: {$expense->payment_method}");
        }

        $entry = JournalEntry::create([
            'entry_number'   => $this->nextEntryNumber(),
            'entry_date'     => $expense->expense_date,
            'description'    => "{$expense->description}",
            'reference_type' => 'Expense',
            'reference_id'   => $expense->id,
            'branch_id'      => $expense->branch_id,
            'created_by'     => $expense->created_by_user_id,
            'status'         => 'posted',
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $expenseAccount->id,
            'debit'            => $expense->amount,
            'credit'           => 0,
            'description'      => $expense->description,
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $paidAccount->id,
            'debit'            => 0,
            'credit'           => $expense->amount,
            'description'      => "Payment for: {$expense->description}",
        ]);

        return $entry;
    }

    private function paymentAccountByMethod(string $method): ?Account
    {
        if ($method === 'cash') {
            return Account::where('code', '1000')->first();
        }

        if (in_array($method, ['bank_transfer', 'cheque'])) {
            return Account::where('code', '1010')->first();
        }

        if ($method === 'card') {
            return Account::where('code', '1010')->first(); // Assume card goes to bank
        }

        return null;
    }

    private function nextEntryNumber(): string
    {
        $seq = JournalEntry::whereDate('created_at', today())->withTrashed()->count() + 1;
        return 'JE-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
