<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JobCard extends Model
{
    protected $fillable = [
        'public_token', 'card_number', 'customer_id', 'customer_name', 'customer_phone',
        'vehicle_number', 'vehicle_make', 'vehicle_model', 'mileage',
        'complaint', 'assigned_technician', 'estimated_completion',
        'status', 'notes', 'total', 'bill_discount', 'branch_id', 'created_by', 'completed_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $card) {
            $card->public_token ??= (string) Str::uuid();
        });
    }

    protected $casts = [
        'estimated_completion' => 'date',
        'completed_at'         => 'datetime',
        'total'                => 'float',
        'bill_discount'        => 'float',
    ];

    public static function generateCardNumber(): string
    {
        $year    = now()->year;
        $prefix  = "JC-{$year}-";
        $last    = DB::table('job_cards')
            ->where('card_number', 'like', "{$prefix}%")
            ->max('card_number');
        $seq     = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    public function recalcTotal(): void
    {
        $this->total = $this->items()->sum('total');
        $this->save();
    }

    public function customer()   { return $this->belongsTo(Customer::class); }
    public function branch()     { return $this->belongsTo(Branch::class); }
    public function createdBy()  { return $this->belongsTo(User::class, 'created_by'); }
    public function items()      { return $this->hasMany(JobCardItem::class); }
}
