<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobCard;
use App\Models\JobCardItem;
use App\Models\Product;
use App\Models\SmsLog;
use App\Services\SmsService;
use Illuminate\Http\Request;

class JobCardController extends Controller
{
    public function __construct(private SmsService $sms) {}

    public function index(Request $request)
    {
        $cards = JobCard::with(['customer:id,name,phone', 'createdBy:id,name'])
            ->when($request->search, fn($q, $s) =>
                $q->where(fn($i) =>
                    $i->where('card_number', 'like', "%$s%")
                      ->orWhere('customer_name', 'like', "%$s%")
                      ->orWhere('vehicle_number', 'like', "%$s%")
                      ->orWhere('customer_phone', 'like', "%$s%")
                ))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json($cards);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'          => 'nullable|exists:customers,id',
            'customer_name'        => 'nullable|string|max:120',
            'customer_phone'       => 'nullable|string|max:30',
            'vehicle_number'       => 'nullable|string|max:30',
            'vehicle_make'         => 'nullable|string|max:60',
            'vehicle_model'        => 'nullable|string|max:60',
            'mileage'              => 'nullable|integer|min:0',
            'complaint'            => 'nullable|string',
            'assigned_technician'  => 'nullable|string|max:100',
            'estimated_completion' => 'nullable|date',
            'notes'                => 'nullable|string',
        ]);

        $card = JobCard::create([
            ...$data,
            'card_number' => JobCard::generateCardNumber(),
            'status'      => 'received',
            'branch_id'   => $request->user()->branch_id,
            'created_by'  => $request->user()->id,
        ]);

        // SMS on creation
        $phone = $data['customer_phone']
            ?? optional($card->customer)->phone;

        if ($phone) {
            $shopName  = config('app.name', 'Siril Motors');
            $trackUrl  = config('app.url') . '/job/' . $card->public_token;
            $msg = "Dear {$card->customer_name}, your vehicle ({$card->vehicle_number}) has been received at {$shopName}. "
                 . "Job Card: {$card->card_number}. Track your service status here: {$trackUrl}";

            $result = $this->sms->sendSingle($phone, $msg);

            SmsLog::create([
                'type'          => 'job_card',
                'sender_id'     => config('services.smslenz.sender_id', 'SMSlenzDEMO'),
                'recipients'    => [$phone],
                'message'       => $msg,
                'total_count'   => 1,
                'success_count' => $result['success'] ? 1 : 0,
                'failed_count'  => $result['success'] ? 0 : 1,
                'api_response'  => $result['response'],
                'status'        => $result['success'] ? 'sent' : 'failed',
                'campaign_name' => "Job Card Created – {$card->card_number}",
                'sent_by'       => $request->user()->id,
            ]);
        }

        return response()->json($card->load(['customer:id,name,phone', 'items']), 201);
    }

    public function show(JobCard $jobCard)
    {
        return response()->json(
            $jobCard->load(['customer:id,name,phone', 'items.product:id,name,sku', 'createdBy:id,name'])
        );
    }

    public function update(Request $request, JobCard $jobCard)
    {
        $data = $request->validate([
            'customer_id'          => 'nullable|exists:customers,id',
            'customer_name'        => 'nullable|string|max:120',
            'customer_phone'       => 'nullable|string|max:30',
            'vehicle_number'       => 'nullable|string|max:30',
            'vehicle_make'         => 'nullable|string|max:60',
            'vehicle_model'        => 'nullable|string|max:60',
            'mileage'              => 'nullable|integer|min:0',
            'complaint'            => 'nullable|string',
            'assigned_technician'  => 'nullable|string|max:100',
            'estimated_completion' => 'nullable|date',
            'status'               => 'sometimes|in:received,in_progress,completed,delivered,cancelled',
            'notes'                => 'nullable|string',
            'bill_discount'        => 'sometimes|numeric|min:0',
        ]);

        $jobCard->update($data);

        return response()->json($jobCard->load(['customer:id,name,phone', 'items.product:id,name,sku']));
    }

    public function addItem(Request $request, JobCard $jobCard)
    {
        $data = $request->validate([
            'type'        => 'required|in:part,labour,other',
            'description' => 'required|string|max:255',
            'product_id'  => 'nullable|exists:products,id',
            'quantity'    => 'required|integer|min:1',
            'unit_price'  => 'required|numeric|min:0',
            'discount'    => 'nullable|numeric|min:0',
        ]);

        $data['discount'] = $data['discount'] ?? 0;
        $data['total']    = ($data['quantity'] * $data['unit_price']) - $data['discount'];

        $item = $jobCard->items()->create($data);
        $jobCard->recalcTotal();

        return response()->json($item->load('product:id,name,sku'), 201);
    }

    public function removeItem(JobCard $jobCard, JobCardItem $item)
    {
        abort_unless($item->job_card_id === $jobCard->id, 404);
        $item->delete();
        $jobCard->recalcTotal();

        return response()->json(['message' => 'Item removed']);
    }

    public function complete(Request $request, JobCard $jobCard)
    {
        if (in_array($jobCard->status, ['completed', 'delivered'])) {
            return response()->json(['message' => 'Job card already completed'], 422);
        }

        $jobCard->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // SMS on completion
        $phone = $jobCard->customer_phone
            ?? optional($jobCard->customer)->phone;

        if ($phone) {
            $shopName   = config('app.name', 'Siril Motors');
            $invoiceUrl = config('app.url') . '/job/' . $jobCard->public_token . '/invoice';
            $msg = "Dear {$jobCard->customer_name}, your vehicle ({$jobCard->vehicle_number}) service is complete at {$shopName}. "
                 . "Job Card: {$jobCard->card_number}. Total: LKR " . number_format(max(0, $jobCard->total - $jobCard->bill_discount), 2)
                 . ". View your invoice: {$invoiceUrl}";

            $result = $this->sms->sendSingle($phone, $msg);

            SmsLog::create([
                'type'          => 'job_card',
                'sender_id'     => config('services.smslenz.sender_id', 'SMSlenzDEMO'),
                'recipients'    => [$phone],
                'message'       => $msg,
                'total_count'   => 1,
                'success_count' => $result['success'] ? 1 : 0,
                'failed_count'  => $result['success'] ? 0 : 1,
                'api_response'  => $result['response'],
                'status'        => $result['success'] ? 'sent' : 'failed',
                'campaign_name' => "Job Card Completed – {$jobCard->card_number}",
                'sent_by'       => $request->user()->id,
            ]);
        }

        return response()->json($jobCard->load(['customer:id,name,phone', 'items.product:id,name,sku']));
    }

    public function destroy(JobCard $jobCard)
    {
        $jobCard->delete();
        return response()->json(['message' => 'Job card deleted']);
    }
}
