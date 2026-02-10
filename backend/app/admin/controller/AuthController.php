<?php
namespace app\admin\controller;

use app\admin\model\ApiKeyModel;

/**
 * @title 认证管理
 * @desc Api密钥认证管理
 * @use app\admin\controller\AuthController
 */
#[\AllowDynamicProperties]
class AuthController extends AdminBaseController
{
    public function initialize()
    {
        parent::initialize();
        $this->apiKeyModel = new ApiKeyModel();
    }

    /**
     * 时间 2026-02-09
     * @title 验证apikey
     * @desc 验证apikey
     * @url /admin/v1/auth/verify
     * @method post
     * @author silveridc
     * @version v1
     * @param string api_key - API密钥 required
     * @return int status - 状态码
     * @return string msg - 提示信息
     * @return bool data.valid - 是否有效
     */
    public function verify()
    {
        $param = $this->request->param();
        
        $apiKey = isset($param['api_key']) ? trim((string)$param['api_key']) : '';

        if ($apiKey === '') {
            return json(['status' => 400, 'msg' => lang('api_key_required')]);
        }

        $valid = $this->apiKeyModel->hasApiKey($apiKey);

        $result = [
            'status' => 200,
            'msg'    => lang('success_message'),
            'data'   => [
                'valid' => $valid
            ]
        ];

        return json($result);
    }

    /**
     * 时间 2026-02-09
     * @title 获取当前认证信息
     * @desc 获取当前请求的认证状态
     * @url /admin/v1/auth/info
     * @method GET
     * @author silveridc
     * @version v1
     * @return int status - 状态码
     * @return string msg - 提示信息
     * @return bool data.authenticated - 是否已认证
     * @return string data.auth_type - 认证类型
     */
    public function info()
    {
        $result = [
            'status' => 200,
            'msg'    => lang('success_message'),
            'data'   => [
                'authenticated' => true,
                'auth_type'     => 'api_key'
            ]
        ];

        return json($result);
    }
}
