<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\RentPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RentPaymentController extends Controller
{
    public function index(Request $request)
    {
        return RentPayment::with(['expenseAccount:id,code,name', 'paidFromAccount:id,code,name'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->month, fn($q, $v) => $q->where('month', $v))
            ->when($request->search, fn($q, $v) => $q->where(function ($x) use ($v) {
                $x->whereRaw('MATCH(property_name, landlord_name) AGAINST(? IN BOOLEAN MODE)', ["{$v}*"])
                    ->orWhere('rent_number', 'like', "{$v}%");
            }))
            ->orderByDesc('due_date')
            ->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'month'                 => ['required', 'regex:/^\\d{4}-\\d{2}$/'],
            'property_name'         => 'required|string|max:200',
            'landlord_name'         => 'nullable|string|max:200',
            'due_date'              => 'required|date',
            'payment_date'          => 'nullable|date',
            'amount'                => 'required|numeric|min:0.01',
            'payment_method'        => 'required|in:cash,bank_transfer,cheque',
            'cheque_number'         => 'nullable|required_if:payment_method,cheque|string|max:50',
            'cheque_date'           => 'nullable|required_if:payment_method,cheque|date',
            'cheque_bank_name'      => 'nullable|required_if:payment_method,cheque|string|max:100',
            'expense_account_id'    => 'nullable|exists:accounts,id',
            'paid_from_account_id'  => 'nullable|exists:accounts,id',
            'reminder_days_before'  => 'nullable|integer|min:1|max:30',
            'status'                => 'nullable|in:due,paid,overdue',
            'notes'                 => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $seq = RentPayment::withTrashed()->count() + 1;
            $rent = RentPayment::create([
                ...$data,
                'rent_number'           => 'RNT-' . date('Ym') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT),
                'expense_account_id'    => $data['expense_account_id'] ?? Account::where('code', '5200')->value('id'),
                'reminder_days_before'  => $data['reminder_days_before'] ?? 5,
                'status'                => $data['status'] ?? 'due',
                'branch_id'             => auth()->user()->branch_id,
                'user_id'               => auth()->id(),
            ]);

            if ($rent->status === 'paid') {
                if (!$rent->paid_from_account_id) {
                    throw new \RuntimeException('Paid-from account is required when rent status is paid.');
                }
                $entry = $this->postJournalEntry($rent);
                $rent->update(['journal_entry_id' => $entry->id]);
            }

            AuditLog::record('rent_created', "Rent {$rent->rent_number} created", $rent);
            DB::commit();

            return response()->json($rent->load(['expenseAccount:id,code,name', 'paidFromAccount:id,code,name']), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create rent record: ' . $e->getMessage()], 500);
        }
    }

    public function pay(Request $request, RentPayment $rentPayment)
    {
        if ($rentPayment->status === 'paid') {
            return response()->json(['message' => 'This rent item is already paid.'], 422);
        }

        $data = $request->validate([
            'payment_date'         => 'required|date',
            'payment_method'       => 'required|in:cash,bank_transfer,cheque',
            'cheque_number'        => 'nullable|required_if:payment_method,cheque|string|max:50',
            'cheque_date'          => 'nullable|required_if:payment_method,cheque|date',
            'cheque_bank_name'     => 'nullable|required_if:payment_method,cheque|string|max:100',
            'paid_from_account_id' => 'required|exists:accounts,id',
            'notes'                => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $rentPayment->update([
                ...$data,
                'status' => 'paid',
            ]);

            $entry = $this->postJournalEntry($rentPayment->fresh());
            $rentPayment->update(['journal_entry_id' => $entry->id]);

            AuditLog::record('rent_paid', "Rent {$rentPayment->rent_number} paid", $rentPayment);
            DB::commit();

            return response()->json($rentPayment->fresh(['expenseAccount:id,code,name', 'paidFromAccount:id,code,name']));
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to pay rent: ' . $e->getMessage()], 500);
        }
    }

    public function reminders(Request $request)
    {
        $today = Carbon::today();
        $withinDays = (int) ($request->within_days ?? 7);
        $horizon = $today->copy()->addDays($withinDays);

        $rows = RentPayment::query()
            ->whereIn('status', ['due', 'overdue'])
            ->whereDate('due_date', '<=', $horizon)
            ->orderBy('due_date')
            ->get()
            ->map(function (RentPayment $rent) use ($today) {
                $daysLeft = $today->diffInDays($rent->due_date, false);
                $level = $daysLeft < 0 ? 'overdue' : ($daysLeft === 0 ? 'today' : 'upcoming');

                return [
                    'id' => $rent->id,
                    'rent_number' => $rent->rent_number,
                    'month' => $rent->month,
                    'property_name' => $rent->property_name,
                    'landlord_name' => $rent->landlord_name,
                    'amount' => $rent->amount,
                    'due_date' => $rent->due_date?->toDateString(),
                    'days_left' => $daysLeft,
                    'level' => $level,
                    'status' => $rent->status,
                ];
            })
            ->values();

        return response()->json([
            'today' => $today->toDateString(),
            'within_days' => $withinDays,
            'rows' => $rows,
        ]);
    }

    private function postJournalEntry(RentPayment $rent): JournalEntry
    {
        $expense = Account::find($rent->expense_account_id);
        $paidFrom = Account::find($rent->paid_from_account_id);

        if (!$expense || !$paidFrom) {
            throw new \RuntimeException('Required accounts not found for rent posting.');
        }

        $entry = JournalEntry::create([
            'entry_number'   => $this->nextEntryNumber(),
            'entry_date'     => $rent->payment_date ?? today(),
            'description'    => "Rent payment {$rent->rent_number} ({$rent->month})",
            'reference_type' => 'RentPayment',
            'reference_id'   => $rent->id,
            'branch_id'      => $rent->branch_id,
            'created_by'     => auth()->id(),
            'status'         => 'posted',
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $expense->id,
            'debit'            => $rent->amount,
            'credit'           => 0,
            'description'      => 'Monthly rent expense',
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $paidFrom->id,
            'debit'            => 0,
            'credit'           => $rent->amount,
            'description'      => 'Rent paid from account',
        ]);

        return $entry;
    }

    private function nextEntryNumber(): string
    {
        $seq = JournalEntry::whereDate('created_at', today())->withTrashed()->count() + 1;
        return 'JE-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
