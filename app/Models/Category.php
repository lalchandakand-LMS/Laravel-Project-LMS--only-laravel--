<?php

namespace App\Models;

use App\Models\Concerns\TracksDeletionActor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    use TracksDeletionActor;

    protected $fillable = ['parent_id', 'name', 'slug', 'icon', 'image', 'description', 'position', 'is_active', 'meta_title', 'meta_description'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $category) {
            if (! $category->slug || $category->isDirty('name') && ! $category->isDirty('slug')) {
                $category->slug = Str::slug($category->name).'-'.Str::lower(Str::random(4));
            }
        });
    }

    public function getRouteKeyName() { return 'slug'; }
    public function courses() { return $this->hasMany(Course::class); }
    public function courseLinks() { return $this->belongsToMany(Course::class, 'course_category')->withTimestamps(); }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('name'); }

    public function descendants()
    {
        $ids = collect();
        $visited = collect([$this->id]);
        $frontier = $visited;
        while ($frontier->isNotEmpty()) {
            $next = self::whereIn('parent_id', $frontier)
                ->whereNotIn('id', $visited)
                ->pluck('id');
            $ids = $ids->merge($next);
            $visited = $visited->merge($next);
            $frontier = $next;
        }
        return self::whereIn('id', $ids);
    }

    public function publishedCourses()
    {
        return $this->courseLinks()->where('is_published', true);
    }

    public function scopeWithPublishedCourseCount(Builder $query): Builder
    {
        return $query->withCount([
            'courses as direct_published_courses_count' => fn ($courses) => $courses->where('courses.is_published', true),
            'courseLinks as additional_published_courses_count' => fn ($courses) => $courses
                ->where('courses.is_published', true)
                ->where(fn ($linked) => $linked->whereNull('courses.category_id')
                    ->orWhereColumn('courses.category_id', '<>', 'categories.id')),
        ]);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? media_url($this->image) : null;
    }
}
