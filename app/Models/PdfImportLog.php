<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfImportLog extends Model
{
    protected $fillable = [
        'filename', 'supplier', 'status',
        'total_rows', 'imported', 'skipped',
        'notes', 'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];
}
