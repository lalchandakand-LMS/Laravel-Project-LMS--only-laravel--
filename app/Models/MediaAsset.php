<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    protected $fillable = [
        'title', 'alt_text', 'source_type', 'disk', 'path', 'url',
        'original_name', 'mime_type', 'size_bytes', 'uploaded_by',
    ];

    protected $casts = ['size_bytes' => 'integer'];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function publicUrl(): ?string
    {
        if ($this->source_type === 'link') {
            return $this->url;
        }

        return $this->path && $this->disk
            ? Storage::disk($this->disk)->url($this->path)
            : null;
    }

    public function isImage(): bool
    {
        return is_string($this->mime_type) && str_starts_with($this->mime_type, 'image/');
    }
}
