<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;

trait TracksDeletionActor
{
    use SoftDeletes;

    protected static function bootTracksDeletionActor(): void
    {
        static::deleting(function ($model) {
            if (! $model->isForceDeleting()
                && Schema::hasColumn($model->getTable(), 'deleted_by')) {
                $model->setAttribute('deleted_by', Auth::id());
                $model->saveQuietly();
            }
        });

        static::restored(function ($model) {
            if (Schema::hasColumn($model->getTable(), 'deleted_by')) {
                $model->setAttribute('deleted_by', null);
                $model->saveQuietly();
            }
        });
    }
}
