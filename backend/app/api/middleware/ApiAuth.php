<?php
namespace app\api\middleware;

use app\api\model\ApiKeyModel;

/**
 * @title API授权检查
 * @desc API Key授权验证中间件
 * @use app\api\middleware\ApiAuth
 */
class ApiAuth
{
    /**
     * @title 处理请求
     * @desc 处理请求
     * @author silveridc
     * @version v1
     */
    public function handle($request, \Closure $next)
    {
        $result = $this->checkAuth($request);

        if ($result['status'] != 200) {
            return json($result, $result['status']);
        }

        $authData = $result['data'];

        // 将认证信息注入请求对象
        $request->api_key_id = $authData['id'];
        $request->api_key_is_default = $authData['is_default'];

        return $next($request);
    }

    /**
     * @title 检查api认证
     * @desc 检查api认证
     * @author silveridc
     * @version v1
     * @param mixed $request
     * @return array
     */
    protected function checkAuth($request)
    {
        // 实例化模型类
        $ApiKeyModel = new ApiKeyModel();

        // 获取所有有效的API Key
        $keys = $ApiKeyModel->getApiKeyRows();

        if (empty($keys)) {
            return ['status' => 500, 'msg' => lang('service_not_initialized')];
        }

        // 获取Authorization头
        $authorization = $request->header('Authorization');

        if (empty($authorization)) {
            return ['status' => 401, 'msg' => lang('unauthorized')];
        }

        // 解析Bearer Token
        $providedKey = $this->parseBearerToken($authorization);

        if ($providedKey === '') {
            return ['status' => 401, 'msg' => lang('invalid_authorization_format')];
        }

        // 验证API Key
        $matchedKey = $ApiKeyModel->validateApiKey($providedKey, $keys);

        if (!$matchedKey) {
            return ['status' => 401, 'msg' => lang('invalid_api_key')];
        }

        // 判断是否为默认Key
        $isDefault = $ApiKeyModel->isDefaultApiKey($matchedKey['id']);

        return [
            'status' => 200,
            'data'   => [
                'id'         => $matchedKey['id'],
                'is_default' => $isDefault,
            ]
        ];
    }

    /**
     * @title 解析Bearer Token
     * @desc 解析Bearer Token
     * @author silveridc
     * @version v1
     * @param string $authorization
     * @return string
     */
    protected function parseBearerToken($authorization)
    {
        //正则匹配Bearer
        if (!preg_match('/^Bearer\s+(.*)$/i', $authorization, $matches)) {
            return '';
        }

        //序列化token
        $result = trim((string)$matches[1]);
        return $result;
    }
}
