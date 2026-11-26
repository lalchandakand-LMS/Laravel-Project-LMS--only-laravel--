<?php

namespace App\\Models;

use App\\Models\\Concerns\\TracksDeletionActor;
use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Support\\Str;

class Post extends Model
{
    use TracksDeletionActor;

    protected $fillable = [
        'category_id', 'author_id', 'title', 'slug', 'excerpt', 'content',
        'video_url', 'thumbnail', 'tags', 'meta_title', 'meta_description',
        'is_published', 'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $post) {
            if (! $post->slug || ($post->isDirty('title') && ! $post->isDirty('slug'))) {
                $base = Str::slug($post->title) ?: 'post';
                $slug = $base;
                $suffix = 2;
                while (static::withTrashed()->where('slug', $slug)->whereKeyNot($post->getKey())->exists()) {
                    $slug = $base.'-'.$suffix++;
                }
                $post->slug = $slug;
            }
            if ($post->is_published && ! $post->published_at) {
                $post->published_at = now();
            }
            if (! $post->is_published) {
                $post->published_at = null;
            }
        });
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function category() { return $this->belongsTo(Category::class); }
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
    public function scopePublished($query) { return $query->where('is_published', true)->whereNotNull('published_at')->where('published_at', '<=', now()); }
}