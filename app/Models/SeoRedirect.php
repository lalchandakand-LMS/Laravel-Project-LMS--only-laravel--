<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoRedirect extends Model
{
    protected $fillable = [
        'created_by',
        'source_path',
        'destination_path',
        'status_code',
        'is_active',
        'hits',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'is_active' => 'boolean',
        'hits' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
