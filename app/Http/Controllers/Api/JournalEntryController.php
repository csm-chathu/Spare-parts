<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        $entries = JournalEntry::with('createdBy:id,name')
            ->when($request->from,   fn($q, $v) => $q->where('entry_date', '>=', $v))
            ->when($request->to,     fn($q, $v) => $q->where('entry_date', '<=', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->search, fn($q, $s) => $q->where(function ($inner) use ($s) {
                $inner->whereRaw('MATCH(description) AGAINST(? IN BOOLEAN MODE)', ["$s*"])
                      ->orWhere('entry_number', 'like', "$s%");
            }))
            ->withCount('lines')
            ->withSum('lines', 'debit')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($entries);
    }

    public function show(JournalEntry $journalEntry)
    {
        return response()->json(
            $journalEntry->load('createdBy:id,name', 'lines.account:id,code,name,type')
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'entry_date'          => 'required|date',
            'description'         => 'required|string|max:500',
            'reference_type'      => 'nullable|string|max:50',
            'reference_id'        => 'nullable|integer',
            'status'              => 'nullable|in:draft,posted',
            'lines'               => 'required|array|min:2',
            'lines.*.account_id'  => 'required|exists:accounts,id',
            'lines.*.debit'       => 'required|numeric|min:0',
            'lines.*.credit'      => 'required|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:255',
        ]);

        $totalDebit  = array_sum(array_column($data['lines'], 'debit'));
        $totalCredit = array_sum(array_column($data['lines'], 'credit'));

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return response()->json([
                'message' => "Journal entry is not balanced. Debits ($totalDebit) ≠ Credits ($totalCredit).",
            ], 422);
        }

        DB::beginTransaction();
        try {
            $seq         = JournalEntry::whereDate('created_at', today())->withTrashed()->count() + 1;
            $entryNumber = 'JE-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

            $entry = JournalEntry::create([
                'entry_number'   => $entryNumber,
                'entry_date'     => $data['entry_date'],
                'description'    => $data['description'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id'   => $data['reference_id']   ?? null,
                'branch_id'      => auth()->user()->branch_id,
                'created_by'     => auth()->id(),
                'status'         => $data['status'] ?? 'posted',
            ]);

            foreach ($data['lines'] as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line['account_id'],
                    'debit'            => $line['debit'],
                    'credit'           => $line['credit'],
                    'description'      => $line['description'] ?? null,
                ]);
            }

            AuditLog::record('journal_entry_created', "Journal entry {$entryNumber} created", $entry);

            DB::commit();
            return response()->json($entry->load('lines.account:id,code,name'), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to save journal entry.'], 500);
        }
    }

    public function destroy(JournalEntry $journalEntry)
    {
        if ($journalEntry->status === 'posted' && !empty($journalEntry->reference_type)) {
            return response()->json(['message' => 'System-generated posted entries cannot be deleted.'], 422);
        }

        AuditLog::record('journal_entry_deleted', "Journal entry {$journalEntry->entry_number} deleted", $journalEntry);
        $journalEntry->delete();

        return response()->json(['message' => 'Entry deleted.']);
    }
}
