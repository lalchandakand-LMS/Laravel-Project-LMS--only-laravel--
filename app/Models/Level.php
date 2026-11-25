<?php

namespace App\\Models;

use App\\Models\\Concerns\\TracksDeletionActor;
use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Support\\Str;

class Level extends Model
{
    use TracksDeletionActor;

    protected $fillable = ['name', 'slug', 'description', 'position', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $level) {
            if (! $level->slug || $level->isDirty('name')) {
                $level->slug = Str::slug($level->name);
            }
        });
    }

    public function courses() { return $this->hasMany(Course::class); }
    public function batches() { return $this->hasMany(Batch::class); }
}
