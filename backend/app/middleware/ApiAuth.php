<?php
namespace app\middleware;

use think\facade\Env;

class ApiAuth
{
    /**
     * 验证API密钥
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // 从环境变量中获取API密钥
        $apiKey = Env::get('API_KEY');
        
        // 获取请求头中的Authorization
        $authorization = $request->header('Authorization');
        
        // 检查Authorization头格式是否正确
        if (empty($authorization) || !preg_match('/^Bearer\s+(.*)$/', $authorization, $matches)) {
            return json([
                'code' => 401,
                'msg' => 'Unauthorized: Invalid Authorization header format'
            ], 401);
        }
        
        // 提取密钥
        $providedKey = $matches[1];
        
        // 验证密钥
        if ($providedKey !== $apiKey) {
            return json([
                'code' => 401,
                'msg' => 'Unauthorized: Invalid API key'
            ], 401);
        }
        
        // 密钥验证通过，继续执行请求
        return $next($request);
    }
}
