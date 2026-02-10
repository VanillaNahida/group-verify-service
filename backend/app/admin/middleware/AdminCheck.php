<?php
namespace app\admin\middleware;

use app\admin\model\ApiKeyModel;
use think\Request;
use think\facade\Cache;

/**
 * @title 后台授权检查
 * @desc 验证 API Key 的合法性、状态及 IP 白名单
 */
class AdminCheck
{
    /**
     * 处理请求
     * @param Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        $result = $this->checkAuth($request);

        if ($result['status'] != 200) {
            return json($result, $result['status'] ?? 401);
        }

        $authData = $result['data'];

        // 将认证信息注入请求对象，方便控制器使用
        $request->api_key_id = $authData['id'];
        $request->api_key_value = $authData['value'];
        $request->api_key_is_default = $authData['is_default'];

        return $next($request);
    }

    /**
     * 校验授权
     * @param Request $request
     * @return array
     */
    protected function checkAuth(Request $request): array
    {
        $authorization = $request->header('Authorization');
        if (!$authorization) {
            $authorization = cookie('admin_api_key');
        }

        if (empty($authorization) || $authorization === 'null') {
            return ['status' => 401, 'msg' => lang('unauthorized')];
        }

        $apiKey = $this->parseToken($authorization);
        if (empty($apiKey)) {
            return ['status' => 401, 'msg' => lang('invalid_token_format')];
        }

        if (Cache::has('logout_api_key_' . md5($apiKey))) {
            return ['status' => 401, 'msg' => lang('token_expired_please_login')];
        }

        return $this->verifyApiKey($apiKey);
    }

    /**
     * 解析 Token
     * @param string $token
     * @return string
     */
    protected function parseToken(string $token): string
    {
        if (stripos($token, 'Bearer ') === 0) {
            return trim(substr($token, 7));
        }
        return trim($token);
    }

    /**
     * 验证 API Key 详情
     * @param string $apiKey
     * @return array
     */
    protected function verifyApiKey(string $apiKey): array
    {
        $ApiKeyModel = new ApiKeyModel();
        
        $keyInfo = $ApiKeyModel->getApiKeyByValue($apiKey);

        if (empty($keyInfo)) {
            return ['status' => 401, 'msg' => lang('invalid_api_key')];
        }

        $id = $keyInfo['id'];

        if (isset($keyInfo['status']) && $keyInfo['status'] != 1) {
            return ['status' => 401, 'msg' => lang('api_key_is_disabled')];
        }

        $currentIp = request()->ip();
        if (!empty($keyInfo['ip_whitelist'])) {
            if (!$ApiKeyModel->isIpInWhitelist($currentIp, $keyInfo['ip_whitelist'])) {
                return ['status' => 401, 'msg' => lang('ip_not_in_whitelist') . ': ' . $currentIp];
            }
        }

        $time = time();
        $autoLogoutTime = config('app.autologout') ?: 7200; // 默认2小时
        if (isset($keyInfo['last_action_time']) && ($keyInfo['last_action_time'] + $autoLogoutTime) < $time) {
        // return ['status' => 401, 'msg' => lang('session_timeout')];
        }

        // 记录/更新最后操作时间
        $ApiKeyModel->updateActionTime($id, $time);

        return [
            'status' => 200,
            'data'   => [
                'id'         => $id,
                'value'      => $apiKey,
                'is_default' => $ApiKeyModel->isDefaultApiKey($id)
            ]
        ];
    }
}
