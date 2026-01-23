<?php
namespace app\middleware;

class AdminCors
{
    public function handle($request, \Closure $next)
    {
        $origin = (string)$request->header('Origin', '');
        $allowOrigin = (string)env('ADMIN_CORS_ORIGIN', '*');

        if ($allowOrigin !== '*' && $origin !== '' && $origin !== $allowOrigin) {
            return json(['code' => 403, 'msg' => 'Forbidden'], 403);
        }

        $headers = [
            'Access-Control-Allow-Origin' => $allowOrigin === '*' ? '*' : ($origin !== '' ? $origin : $allowOrigin),
            'Access-Control-Allow-Methods' => 'GET,POST,PUT,OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization,Content-Type',
            'Access-Control-Max-Age' => '86400',
        ];

        if ($allowOrigin !== '*') {
            $headers['Vary'] = 'Origin';
        }

        if (strtoupper((string)$request->method()) === 'OPTIONS') {
            return response('', 204)->header($headers);
        }

        $response = $next($request);
        return $response->header($headers);
    }
}

