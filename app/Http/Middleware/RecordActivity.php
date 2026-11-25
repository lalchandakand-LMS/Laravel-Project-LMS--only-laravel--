<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $actorId = $request->user()?->getAuthIdentifier();
        $response = $next($request);

        if ($response->getStatusCode() < 400
            && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && ! $request->is('up')) {
            try {
                if (DB::getSchemaBuilder()->hasTable('activity_logs')) {
                    DB::table('activity_logs')->insert([
                        'user_id' => $actorId ?: $request->user()?->getAuthIdentifier(),
                        'event' => strtolower($request->method()),
                        'description' => $this->description($request),
                        'route' => optional($request->route())->getName(),
                        'method' => $request->method(),
                        'path' => substr($request->path(), 0, 2048),
                        'ip_address' => $request->ip(),
                        'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $response;
    }

    private function description(Request $request): string
    {
        $route = optional($request->route())->getName();
        return substr(($route ? str_replace('.', ' ', $route) : $request->path())
            .' — '.$request->method(), 0, 255);
    }
}
