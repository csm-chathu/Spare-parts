<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\BusinessLoan;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\LoanRepayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        // source: 'customer' | 'owner' | 'bank' (default)
        $source = $request->source ?? 'bank';
        return BusinessLoan::with(['liabilityAccount:id,code,name', 'receivedToAccount:id,code,name', 'customer:id,name,phone'])
            ->where('source', $source)
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->search, fn($q, $v) => $q->where(function ($x) use ($v) {
                $x->whereRaw('MATCH(lender_name) AGAINST(? IN BOOLEAN MODE)', ["{$v}*"])
                    ->orWhere('loan_number', 'like', "{$v}%");
            }))
            ->orderByDesc('start_date')
            ->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'source'                 => 'nullable|in:bank,customer,owner',
            'lender_name'            => 'nullable|string|max:200',
            'customer_id'            => 'nullable|exists:customers,id',
            'principal_amount'       => 'required|numeric|min:0.01',
            'interest_rate'          => 'nullable|numeric|min:0',
            'monthly_installment'    => 'nullable|numeric|min:0',
            'start_date'             => 'required|date',
            'due_date'               => 'nullable|date|after_or_equal:start_date',
            'liability_account_id'   => 'nullable|exists:accounts,id',
            'received_to_account_id' => 'nullable|exists:accounts,id',
            'status'                 => 'nullable|in:active,closed',
            'notes'                  => 'nullable|string|max:1000',
            'post_to_gl'             => 'nullable|boolean',
        ]);

        // Auto-populate lender_name from customer when customer_id is given
        if (!empty($data['customer_id']) && empty($data['lender_name'])) {
            $customer = \App\Models\Customer::find($data['customer_id']);
            $data['lender_name'] = $customer?->name ?? 'Customer';
        }

        if (empty($data['lender_name'])) {
            return response()->json(['errors' => ['lender_name' => ['Lender / owner name is required.']]], 422);
        }

        DB::beginTransaction();
        try {
            $seq = BusinessLoan::withTrashed()->count() + 1;
            $source = $data['source'] ?? 'bank';
            $prefix = match($source) { 'owner' => 'OINV', 'customer' => 'CINV', default => 'LOAN' };
            $loan = BusinessLoan::create([
                ...$data,
                'source'              => $source,
                'loan_number'         => $prefix . '-' . date('Ym') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT),
                'outstanding_balance' => $data['principal_amount'],
                'status'              => $data['status'] ?? 'active',
                'branch_id'           => auth()->user()->branch_id,
                'user_id'             => auth()->id(),
            ]);

            $postToGl = ($data['post_to_gl'] ?? true)
                && $loan->status === 'active'
                && $loan->liability_account_id
                && $loan->received_to_account_id;

            if ($postToGl) {
                $entry = $this->postLoanDisbursement($loan);
                $loan->update(['journal_entry_id' => $entry->id]);
            }

            AuditLog::record('loan_created', "Business loan {$loan->loan_number} created", $loan);
            DB::commit();

            return response()->json($loan->load(['liabilityAccount:id,code,name', 'receivedToAccount:id,code,name']), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create loan: ' . $e->getMessage()], 500);
        }
    }

    public function show(BusinessLoan $loan)
    {
        $loan->load([
            'liabilityAccount:id,code,name',
            'receivedToAccount:id,code,name',
            'repayments' => fn($q) => $q->with(['paidFromAccount:id,code,name'])->latest('payment_date')->limit(20),
        ]);

        return response()->json($loan);
    }

    public function repay(Request $request, BusinessLoan $loan)
    {
        if ($loan->status === 'closed') {
            return response()->json(['message' => 'This loan is already closed.'], 422);
        }

        $data = $request->validate([
            'payment_date'                => 'required|date',
            'principal_amount'            => 'nullable|numeric|min:0',
            'interest_amount'             => 'nullable|numeric|min:0',
            'paid_from_account_id'        => 'required|exists:accounts,id',
            'interest_expense_account_id' => 'nullable|exists:accounts,id',
            'notes'                       => 'nullable|string|max:500',
        ]);

        $principal = (float) ($data['principal_amount'] ?? 0);
        $interest = (float) ($data['interest_amount'] ?? 0);
        $total = $principal + $interest;

        if ($total <= 0) {
            return response()->json(['message' => 'Repayment total must be greater than zero.'], 422);
        }

        if ($principal > $loan->outstanding_balance) {
            return response()->json(['message' => 'Principal repayment exceeds outstanding balance.'], 422);
        }

        DB::beginTransaction();
        try {
            $seq = LoanRepayment::withTrashed()->count() + 1;
            $repayment = LoanRepayment::create([
                'payment_number'              => 'LR-' . date('Ym') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT),
                'loan_id'                     => $loan->id,
                'payment_date'                => $data['payment_date'],
                'principal_amount'            => $principal,
                'interest_amount'             => $interest,
                'total_amount'                => $total,
                'paid_from_account_id'        => $data['paid_from_account_id'],
                'interest_expense_account_id' => $data['interest_expense_account_id'] ?? null,
                'notes'                       => $data['notes'] ?? null,
                'branch_id'                   => auth()->user()->branch_id,
                'user_id'                     => auth()->id(),
            ]);

            if ($loan->liability_account_id) {
                $entry = $this->postRepayment($loan, $repayment);
                $repayment->update(['journal_entry_id' => $entry->id]);
            }

            $loan->outstanding_balance = max(0, round($loan->outstanding_balance - $principal, 2));
            $loan->status = $loan->outstanding_balance <= 0.0001 ? 'closed' : 'active';
            $loan->save();

            AuditLog::record('loan_repayment', "Loan repayment {$repayment->payment_number} posted", $loan);
            DB::commit();

            return response()->json($repayment->load(['paidFromAccount:id,code,name', 'interestExpenseAccount:id,code,name']), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to post repayment: ' . $e->getMessage()], 500);
        }
    }

    private function postLoanDisbursement(BusinessLoan $loan): JournalEntry
    {
        $received = Account::find($loan->received_to_account_id);
        $liability = Account::find($loan->liability_account_id);

        if (!$received || !$liability) {
            throw new \RuntimeException('Required accounts not found for loan disbursement.');
        }

        $entry = JournalEntry::create([
            'entry_number'   => $this->nextEntryNumber(),
            'entry_date'     => $loan->start_date,
            'description'    => "Loan received {$loan->loan_number} from {$loan->lender_name}",
            'reference_type' => 'BusinessLoan',
            'reference_id'   => $loan->id,
            'branch_id'      => $loan->branch_id,
            'created_by'     => auth()->id(),
            'status'         => 'posted',
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $received->id,
            'debit'            => $loan->principal_amount,
            'credit'           => 0,
            'description'      => 'Loan disbursement received',
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $liability->id,
            'debit'            => 0,
            'credit'           => $loan->principal_amount,
            'description'      => 'Loan liability recognized',
        ]);

        return $entry;
    }

    private function postRepayment(BusinessLoan $loan, LoanRepayment $repayment): JournalEntry
    {
        $liability = Account::find($loan->liability_account_id);
        $paidFrom = Account::find($repayment->paid_from_account_id);
        $interestExpense = $repayment->interest_expense_account_id
            ? Account::find($repayment->interest_expense_account_id)
            : Account::where('code', '5900')->first();

        if (!$liability || !$paidFrom) {
            throw new \RuntimeException('Required accounts not found for repayment posting.');
        }

        $entry = JournalEntry::create([
            'entry_number'   => $this->nextEntryNumber(),
            'entry_date'     => $repayment->payment_date,
            'description'    => "Loan repayment {$repayment->payment_number} ({$loan->loan_number})",
            'reference_type' => 'LoanRepayment',
            'reference_id'   => $repayment->id,
            'branch_id'      => $repayment->branch_id,
            'created_by'     => auth()->id(),
            'status'         => 'posted',
        ]);

        if ($repayment->principal_amount > 0) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $liability->id,
                'debit'            => $repayment->principal_amount,
                'credit'           => 0,
                'description'      => 'Loan principal repaid',
            ]);
        }

        if ($repayment->interest_amount > 0 && $interestExpense) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $interestExpense->id,
                'debit'            => $repayment->interest_amount,
                'credit'           => 0,
                'description'      => 'Loan interest expense',
            ]);
        }

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $paidFrom->id,
            'debit'            => 0,
            'credit'           => $repayment->total_amount,
            'description'      => 'Loan repayment paid',
        ]);

        return $entry;
    }

    private function nextEntryNumber(): string
    {
        $seq = JournalEntry::whereDate('created_at', today())->withTrashed()->count() + 1;
        return 'JE-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
