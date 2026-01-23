<?php
namespace app\middleware;

use think\facade\Db;

class ApiCallLogger
{
    protected function shouldLog($request): bool
    {
        $path = '';
        try {
            $path = trim((string)$request->pathinfo(), '/');
        } catch (\Throwable $e) {
            $path = '';
        }
        if ($path === '') {
            return false;
        }
        if ($path !== 'verify/create' && $path !== 'verify/check' && $path !== 'verify/clean' && $path !== 'verify/reset-key' && $path !== 'verify/callback') {
            return false;
        }
        return true;
    }

    protected function safeString($v, int $maxLen = 0): string
    {
        $s = trim((string)$v);
        if ($s === '') {
            return '';
        }
        if ($maxLen > 0 && mb_strlen($s) > $maxLen) {
            return mb_substr($s, 0, $maxLen);
        }
        return $s;
    }

    public function handle($request, \Closure $next)
    {
        if (!$this->shouldLog($request)) {
            return $next($request);
        }

        $start = microtime(true);

        $endpoint = '';
        try {
            $endpoint = '/' . trim((string)$request->pathinfo(), '/');
        } catch (\Throwable $e) {
            $endpoint = '';
        }
        $method = '';
        try {
            $method = strtoupper((string)$request->method());
        } catch (\Throwable $e) {
            $method = '';
        }

        $groupId = '';
        $userId = '';
        $ticket = '';
        $code = '';
        try {
            $groupId = $this->safeString($request->post('group_id', ''), 64);
            $userId = $this->safeString($request->post('user_id', ''), 64);
            $ticket = $this->safeString($request->post('ticket', ''), 64);
            $code = $this->safeString($request->post('code', ''), 10);
        } catch (\Throwable $e) {
        }

        $ip = '';
        try {
            $ip = $this->safeString($request->ip(), 45);
        } catch (\Throwable $e) {
            $ip = '';
        }
        $ua = '';
        try {
            $ua = $this->safeString($request->header('user-agent', ''), 500);
        } catch (\Throwable $e) {
            $ua = '';
        }

        $apiKeyId = 0;
        try {
            $apiKeyId = (int)$request->middleware('api_key_id', 0);
        } catch (\Throwable $e) {
            $apiKeyId = 0;
        }

        if ($ticket !== '' && ($groupId === '' || $userId === '' || $apiKeyId <= 0)) {
            try {
                $row = Db::name('GeetestTable')
                    ->field('api_key_id, group_id, user_id')
                    ->where('token', $ticket)
                    ->find();
                if (is_array($row)) {
                    if ($apiKeyId <= 0) {
                        $apiKeyId = (int)($row['api_key_id'] ?? 0);
                    }
                    if ($groupId === '') {
                        $groupId = $this->safeString($row['group_id'] ?? '', 64);
                    }
                    if ($userId === '') {
                        $userId = $this->safeString($row['user_id'] ?? '', 64);
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $durationMs = (int)max(0, round((microtime(true) - $start) * 1000));

            try {
                ensure_api_call_logs_table();
                Db::name('api_call_logs')->insert([
                    'api_key_id' => $apiKeyId > 0 ? $apiKeyId : null,
                    'endpoint' => $endpoint !== '' ? $endpoint : '/',
                    'method' => $method !== '' ? $method : 'GET',
                    'status_code' => 500,
                    'group_id' => $groupId !== '' ? $groupId : null,
                    'user_id' => $userId !== '' ? $userId : null,
                    'ticket' => $ticket !== '' ? $ticket : null,
                    'code' => $code !== '' ? $code : null,
                    'ip' => $ip !== '' ? $ip : null,
                    'user_agent' => $ua !== '' ? $ua : null,
                    'duration_ms' => $durationMs,
                    'created_at' => time(),
                ]);
            } catch (\Throwable $e3) {
            }
            throw $e;
        }

        $durationMs = (int)max(0, round((microtime(true) - $start) * 1000));
        $statusCode = 200;
        try {
            $statusCode = (int)$response->getCode();
        } catch (\Throwable $e) {
            $statusCode = 200;
        }

        try {
            ensure_api_call_logs_table();
            Db::name('api_call_logs')->insert([
                'api_key_id' => $apiKeyId > 0 ? $apiKeyId : null,
                'endpoint' => $endpoint !== '' ? $endpoint : '/',
                'method' => $method !== '' ? $method : 'GET',
                'status_code' => $statusCode > 0 ? $statusCode : 200,
                'group_id' => $groupId !== '' ? $groupId : null,
                'user_id' => $userId !== '' ? $userId : null,
                'ticket' => $ticket !== '' ? $ticket : null,
                'code' => $code !== '' ? $code : null,
                'ip' => $ip !== '' ? $ip : null,
                'user_agent' => $ua !== '' ? $ua : null,
                'duration_ms' => $durationMs,
                'created_at' => time(),
            ]);
        } catch (\Throwable $e) {
        }

        return $response;
    }
}
