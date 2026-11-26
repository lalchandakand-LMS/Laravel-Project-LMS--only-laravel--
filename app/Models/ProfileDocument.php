<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileDocument extends Model
{
    protected $fillable = [
        'document_type', 'disk', 'path', 'original_name', 'mime_type',
        'size_bytes', 'status', 'review_note', 'uploaded_by', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
