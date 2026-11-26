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
     * Delete a locally uploaded file without ever treating a remote URL or
     * a shared Media Library asset as an owned, disposable file.
     */
    function delete_stored_media(string $disk, ?string $path): void
    {
        if (! $path
            || str_starts_with(strtolower($path), 'http://')
            || str_starts_with(strtolower($path), 'https://')
            || ($disk === 'public' && str_starts_with($path, 'media-library/'))) {
            return;
        }

        \Illuminate\Support\Facades\Storage::disk($disk)->delete($path);
    }
}
