<?php

namespace App\\Http\\Middleware;

use Closure;
use Illuminate\\Http\\Request;
use Symfony\\Component\\HttpFoundation\\Response;

class CaptureMarketingAttribution
{
    private const FIELDS = [
        'utm_source' => 120,
        'utm_medium' => 120,
        'utm_campaign' => 160,
        'utm_term' => 160,
        'utm_content' => 160,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $attribution = [];
        foreach (self::FIELDS as $field => $maxLength) {
            $value = $request->query($field);
            if (is_string($value)) {
                $value = trim(strip_tags($value));
                if ($value !== '' && mb_strlen($value) <= $maxLength && ! preg_match('/[\\x00-\\x1F]/', $value)) {
                    $attribution[$field] = $value;
                }
            }
        }

        if ($attribution !== []) {
            $current = $request->session()->get('marketing_attribution', []);
            $request->session()->put('marketing_attribution', array_merge(
                is_array($current) ? $current : [],
                $attribution,
                [
                    'landing_page' => '/'.ltrim($request->path(), '/'),
                    'referrer_host' => parse_url((string) $request->headers->get('referer'), PHP_URL_HOST) ?: null,
                ]
            ));
        }

        return $next($request);
    }
}
