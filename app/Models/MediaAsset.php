<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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


    /**
     * A copied Media Library URL may be referenced by live site content.
     * Never remove the backing file while one of those references exists.
     */
    public function isReferenced(): bool
    {
        $url = $this->publicUrl();

        if (! $url) {
            return false;
        }

        $references = [
            'settings' => ['value'],
            'courses' => ['thumbnail'],
            'categories' => ['image'],
            'sliders' => ['image'],
            'ebooks' => ['cover', 'file'],
            'lessons' => ['attachment'],
            'assignments' => ['attachment'],
            'users' => ['avatar'],
        ];

        foreach ($references as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)
                    && DB::table($table)->where($column, $url)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isImage(): bool
    {
        return is_string($this->mime_type) && str_starts_with($this->mime_type, 'image/');
    }
}
