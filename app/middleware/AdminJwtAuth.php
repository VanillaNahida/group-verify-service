<?php
namespace app\middleware;

class AdminJwtAuth
{
    public function handle($request, \Closure $next)
    {
        $apiAuth = new ApiAuth();
        return $apiAuth->handle($request, function ($request) use ($next) {
            $isDefaultKey = false;
            try {
                $isDefaultKey = (bool)$request->middleware('api_key_is_default', false);
            } catch (\Throwable $e) {
                $isDefaultKey = false;
            }
            if (!$isDefaultKey) {
                return json(['code' => 403, 'msg' => '权限不足：仅默认 API Key 可访问管理接口'], 403);
            }
            return $next($request);
        });
    }
}
