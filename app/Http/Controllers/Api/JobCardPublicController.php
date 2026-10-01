<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobCard;

class JobCardPublicController extends Controller
{
    public function show(string $token)
    {
        $card = JobCard::where('public_token', $token)
            ->with(['items.product:id,name,sku', 'branch:id,name'])
            ->firstOrFail();

        return response()->json([
            'card_number'          => $card->card_number,
            'status'               => $card->status,
            'customer_name'        => $card->customer_name,
            'vehicle_number'       => $card->vehicle_number,
            'vehicle_make'         => $card->vehicle_make,
            'vehicle_model'        => $card->vehicle_model,
            'mileage'              => $card->mileage,
            'complaint'            => $card->complaint,
            'assigned_technician'  => $card->assigned_technician,
            'estimated_completion' => $card->estimated_completion?->toDateString(),
            'completed_at'         => $card->completed_at?->toIso8601String(),
            'total'                => $card->total,
            'bill_discount'        => $card->bill_discount,
            'net_total'            => max(0, $card->total - $card->bill_discount),
            'branch_name'          => $card->branch?->name,
            'created_at'           => $card->created_at->toIso8601String(),
            'items'                => $card->items->map(fn($i) => [
                'type'        => $i->type,
                'description' => $i->description,
                'quantity'    => $i->quantity,
                'unit_price'  => $i->unit_price,
                'discount'    => $i->discount,
                'total'       => $i->total,
            ]),
        ]);
    }
}
