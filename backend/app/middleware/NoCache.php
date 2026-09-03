<?php
namespace app\middleware;

/**
 * Prevent shared caches from serving stale application pages or API results.
 * Static assets are intentionally excluded and can keep their immutable cache policy.
 */
class NoCache
{
    public function handle($request, \Closure $next)
    {
        $response = $next($request);

        $path = '';
        try {
            $path = trim((string)$request->pathinfo(), '/');
        } catch (\Throwable $e) {
        }

        $dynamic = $path === 'setup'
            || $path === 'admin'
            || str_starts_with($path, 'admin/')
            || $path === 'verify/callback'
            || str_starts_with($path, 'verify/status/');

        if ($dynamic) {
            // Disable ThinkPHP's automatic request-cache header before setting
            // explicit no-store directives for browsers and reverse proxies.
            $response->allowCache(false);
            $response->header([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'CDN-Cache-Control' => 'no-store',
                'Surrogate-Control' => 'no-store',
            ]);
        }

        return $response;
    }
}
