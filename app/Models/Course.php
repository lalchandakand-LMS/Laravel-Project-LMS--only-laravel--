<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\TracksDeletionActor;
use Illuminate\Support\Str;

class Course extends Model
{
    use TracksDeletionActor;
    protected $fillable = [
        'category_id','level_id','instructor_id','title','slug','subtitle','description','requirements',
        'outcomes','thumbnail','promo_video','price','discount_price','level','language',
        'duration_minutes','is_published','is_featured','has_certificate','starts_at',
        'seat_limit','meta_title','meta_description','access_duration_days',
    ];

    protected $casts = [
        'outcomes' => 'array',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'has_certificate' => 'boolean',
        'starts_at' => 'datetime',
        'price' => 'float',
        'discount_price' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $c) {
            if (! $c->slug) {
                $c->slug = Str::slug($c->title).'-'.Str::lower(Str::random(4));
            }
        });
    }

    public function getRouteKeyName() { return 'slug'; }

    /* Relations */
    public function category()    { return $this->belongsTo(Category::class); }
    public function categories() { return $this->belongsToMany(Category::class, 'course_category')->withTimestamps(); }
    public function courseLevel() { return $this->belongsTo(Level::class, 'level_id'); }
    public function batches() { return $this->hasMany(Batch::class); }
    public function instructor()  { return $this->belongsTo(User::class, 'instructor_id'); }
    public function sections()    { return $this->hasMany(Section::class)->orderBy('position'); }
    public function lessons()     { return $this->hasMany(Lesson::class)->orderBy('position'); }
    public function enrollments() { return $this->hasMany(Enrollment::class); }
    public function students()    { return $this->belongsToMany(User::class, 'enrollments')->withTimestamps(); }
    public function reviews()     { return $this->hasMany(Review::class); }
    public function exams()       { return $this->hasMany(Exam::class); }
    public function assignments() { return $this->hasMany(Assignment::class); }
    public function announcements(){ return $this->hasMany(Announcement::class); }
    public function liveClasses() { return $this->hasMany(LiveClass::class)->orderBy('starts_at'); }

    /* Scopes */
    public function scopePublished($q) { return $q->where('is_published', true); }
    public function scopeFeatured($q)  { return $q->where('is_featured', true); }

    /* Accessors */
    /**
     * Resolve the best currently running campaign for this course.
     * A campaign is eligible when it targets the course directly or one of its assigned categories.
     */
    public function activeFlashSale(): ?FlashSale
    {
        if ($this->relationLoaded('effectiveFlashSale')) {
            return $this->getRelation('effectiveFlashSale');
        }

        $categoryIds = $this->relationLoaded('categories')
            ? $this->categories->pluck('id')->all()
            : $this->categories()->pluck('categories.id')->all();
        if ($this->category_id) {
            $categoryIds[] = (int) $this->category_id;
        }
        $categoryIds = array_values(array_unique($categoryIds));

        $regularDiscountedPrice = (float) ($this->discount_price ?? $this->price);
        $sale = FlashSale::currentlyRunning()
            ->where(function ($query) use ($categoryIds) {
                $query->whereHas('courses', fn ($courses) => $courses->where('courses.id', $this->id));
                if ($categoryIds !== []) {
                    $query->orWhereHas('categories', fn ($categories) => $categories->whereIn('categories.id', $categoryIds));
                }
            })
            ->get()
            ->filter(fn (FlashSale $candidate) => $candidate->discountedPrice((float) $this->price) < $regularDiscountedPrice)
            ->sortBy(fn (FlashSale $candidate) => $candidate->discountedPrice((float) $this->price))
            ->first();

        $this->setRelation('effectiveFlashSale', $sale);

        return $sale;
    }

    public function getFinalPriceAttribute(): float
    {
        $regularDiscountedPrice = (float) ($this->discount_price ?? $this->price);
        $sale = $this->activeFlashSale();

        return $sale
            ? min($regularDiscountedPrice, $sale->discountedPrice((float) $this->price))
            : $regularDiscountedPrice;
    }

    public function getHasDiscountAttribute(): bool
    {
        return $this->final_price < (float) $this->price;
    }

    public function getDiscountPercentAttribute(): int
    {
        if (! $this->has_discount || $this->price <= 0) {
            return 0;
        }
        return (int) round(((float) $this->price - $this->final_price) / (float) $this->price * 100);
    }

    public function getThumbnailUrlAttribute(): string
    {
        return $this->thumbnail
            ? media_url($this->thumbnail)
            : 'https://picsum.photos/seed/'.$this->id.'/640/400';
    }

    public function getLessonCountAttribute(): int
    {
        return $this->lessons()->count();
    }

    public function getRatingAttribute(): float
    {
        return round((float) $this->reviews()->where('is_approved', true)->avg('rating'), 1);
    }

    public function getStudentsCountAttribute(): int
    {
        return $this->enrollments()->count();
    }

    public function totalLessons(): int { return $this->lessons()->count(); }

    /** Percentage of lessons the given user completed. */
    public function progressFor(?User $user): int
    {
        if (! $user) {
            return 0;
        }
        // Draft lessons are not part of the learner's course curriculum.
        $publishedLessonIds = $this->lessons()->where('is_published', true)->pluck('id');
        $total = $publishedLessonIds->count();
        if ($total === 0) {
            return 0;
        }
        $done = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $publishedLessonIds)
            ->whereNotNull('completed_at')
            ->count();

        return (int) round($done / $total * 100);
    }

    public function nextLessonFor(User $user): ?Lesson
    {
        return $this->lessons()
            ->whereNotIn('id', LessonProgress::where('user_id', $user->id)
                ->whereNotNull('completed_at')->pluck('lesson_id'))
            ->orderBy('position')->first() ?? $this->lessons()->first();
    }
}
