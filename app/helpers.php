<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('bd_money')) {
    function bd_money($amount): string
    {
        return '৳'.number_format((float) $amount, 0);
    }
}

if (! function_exists('media_url')) {
    function media_url(?string $path, ?string $fallback = null): ?string
    {
        if (! $path) {
            return $fallback;
        }

        if ((str_starts_with(strtolower($path), 'http://') || str_starts_with(strtolower($path), 'https://'))) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}


if (! function_exists('delete_stored_media')) {
    /**
     * Delete an owned upload only when no LMS record still points to it.
     * Remote URLs and Media Library files are managed separately.
     */
    function delete_stored_media(string $disk, ?string $path): void
    {
        if (! $path
            || str_starts_with(strtolower($path), 'http://')
            || str_starts_with(strtolower($path), 'https://')
            || ($disk === 'public' && str_starts_with($path, 'media-library/'))) {
            return;
        }

        $values = [$path];
        if ($disk === 'public') {
            $values[] = \Illuminate\\Support\\Facades\\Storage::disk('public')->url($path);
            $values[] = asset('storage/'.ltrim($path, '/'));
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
            if (! \Illuminate\\Support\\Facades\\Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (\Illuminate\\Support\\Facades\\Schema::hasColumn($table, $column)
                    && \Illuminate\\Support\\Facades\\DB::table($table)->whereIn($column, $values)->exists()) {
                    return;
                }
            }
        }

        \Illuminate\\Support\\Facades\\Storage::disk($disk)->delete($path);
    }
}
