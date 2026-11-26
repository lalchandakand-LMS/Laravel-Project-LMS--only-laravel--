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
     * A copied Media Library URL may be referenced by site content.
     * Prevent deleting the library item while any supported field uses it.
     */
    public function isReferenced(): bool
    {
        $url = $this->publicUrl();

        if (! $url) {
            return false;
        }

        $values = [$url];
        if ($this->source_type === 'upload' && $this->path) {
            $values[] = $this->path;
        }

        if ($this->source_type === 'upload' && $this->disk && $this->path) {
            $duplicate = self::query()
                ->where('id', '!=', $this->id)
                ->where('source_type', 'upload')
                ->where('disk', $this->disk)
                ->where('path', $this->path)
                ->exists();

            if ($duplicate) {
                return true;
            }
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
            'posts' => ['thumbnail'],
        ];

        foreach ($references as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)
                    && DB::table($table)->whereIn($column, $values)->exists()) {
                    return true;
                }
            }
        }

        // Blog post bodies may embed copied Media Library URLs inside rich text.
        if (Schema::hasTable('posts') && Schema::hasColumn('posts', 'content')) {
            foreach ($values as $value) {
                if (DB::table('posts')->where('content', 'like', '%'.$value.'%')->exists()) {
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
