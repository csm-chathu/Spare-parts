<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobCardItem extends Model
{
    protected $fillable = [
        'job_card_id', 'type', 'description', 'product_id',
        'quantity', 'unit_price', 'discount', 'total',
    ];

    protected $casts = [
        'quantity'   => 'float',
        'unit_price' => 'float',
        'discount'   => 'float',
        'total'      => 'float',
    ];

    public function jobCard()  { return $this->belongsTo(JobCard::class); }
    public function product()  { return $this->belongsTo(Product::class); }
}
