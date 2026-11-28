<?php

namespace App\Support;

final class HomepageSectionOrder
{
    public const KEYS = [
        'hero',
        'online_batches',
        'stats',
        'categories',
        'learning_modes',
        'branches',
        'featured',
        'latest',
        'live_classes',
        'exams',
        'learning_benefits',
        'learning_preview',
        'teachers',
        'board_results',
        'reviews',
        'ebooks',
        'learning_hub',
        'admission',
    ];

    public const DEFAULT_ORDER = self::KEYS;

    private const LEGACY_DEFAULT_ORDERS = [
        [
            'hero', 'online_batches', 'stats', 'categories', 'featured', 'latest', 'live_classes',
            'exams', 'teachers', 'board_results', 'reviews', 'ebooks', 'admission',
        ],
        [
            'hero', 'categories', 'featured', 'online_batches', 'live_classes', 'stats', 'latest',
            'exams', 'teachers', 'board_results', 'reviews', 'ebooks', 'admission',
        ],
    ];

    public static function normalize(mixed $storedOrder): array
    {
        $order = is_array($storedOrder)
            ? $storedOrder
            : json_decode(is_string($storedOrder) ? $storedOrder : '[]', true);

        if (! is_array($order) || ! array_is_list($order) || in_array($order, self::LEGACY_DEFAULT_ORDERS, true)) {
            return self::DEFAULT_ORDER;
        }

        $order = array_values(array_unique(array_filter(
            $order,
            fn ($key) => is_string($key) && in_array($key, self::KEYS, true) && $key !== 'hero',
        )));

        return array_merge(
            ['hero'],
            $order,
            array_values(array_diff(self::KEYS, array_merge(['hero'], $order))),
        );
    }
}
