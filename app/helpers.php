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
