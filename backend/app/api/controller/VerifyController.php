<?php
namespace app\api\controller;

use app\api\model\VerifyModel;
use app\api\validate\VerifyValidate;
/**
 * @title 验证码验证
 * @desc 极验验证码相关接口
 * @use app\api\controller\VerifyController
 */
#[\AllowDynamicProperties]
class VerifyController extends BaseController
{
    public function initialize()
    {
        parent::initialize();
        $this->verifyModel = new VerifyModel();
        $this->validate = new VerifyValidate();
    }

    /**
     * 时间 2026-02-09
     * @title 生成验证链接
     * @desc 生成验证链接供用户访问
     * @url /api/v1/verify/create
     * @method post
     * @author silveridc
     * @version v1
     * @param string group_id - 分组ID required
     * @param string user_id - 用户ID required
     * @return int status - 状态码
     * @return string msg - 提示信息
     * @return string data.ticket - 验证票据
     * @return string data.url - 验证链接
     * @return int data.expire - 过期时间(秒)
     */
    public function create()
    {
        $param = $this->request->param();

        // 参数验证
        if (!$this->validate->scene('create')->check($param)) {
            return json(['status' => 400, 'msg' => lang($this->validate->getError())]);
        }

        $result = $this->verifyModel->createVerify([
            'group_id'   => $param['group_id'],
            'user_id'    => $param['user_id'],
            'api_key_id' => $this->getApiKeyId(),
            'domain'     => $this->request->domain(),
        ]);

        return json($result);
    }

    /**
     * 时间 2026-02-09
     * @title 验证页面
     * @desc 生成用户访问的验证页面
     * @url /v/:ticket
     * @method GET
     * @author silveridc
     * @version v1
     * @param string ticket - 验证票据 required
     * @return html
     */
    public function page()
    {
        $ticket = (string)$this->request->route('ticket', '');

        if ($ticket === '') {
            return response(lang('invalid_verify_link'), 400);
        }

        $htmlFile = root_path() . 'public' . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'verify' . DIRECTORY_SEPARATOR . 'index.html';

        if (!is_file($htmlFile)) {
            return response(lang('verify_page_missing'), 500);
        }

        $html = (string)file_get_contents($htmlFile);
        return response($html)->contentType('text/html');
    }

    /**
     * 时间 2026-02-09
     * @title 获取验证状态
     * @desc 获取验证票据的当前状态
     * @url /api/v1/verify/:ticket/status
     * @method GET
     * @author silveridc
     * @version v1
     * @param string ticket - 验证票据 required
     * @return int status - 状态码
     * @return string msg - 提示信息
     * @return string data.ticket - 验证票据
     * @return bool data.verified - 是否已验证
     * @return string data.code - 验证码(已验证时返回)
     * @return string data.captcha_id - 极验ID(未验证时返回)
     * @return int data.code_expire - 验证码过期时间(秒)
     * @return int data.expire_minutes - 过期分钟数
     */
    public function status()
    {
        $ticket = (string)$this->request->route('ticket', '');

        if ($ticket === '') {
            return json(['status' => 400, 'msg' => lang('param_error')]);
        }

        $result = $this->verifyModel->getVerifyStatus($ticket);

        return json($result);
    }

    /**
     * 时间 2026-02-09
     * @title 极验验证回调
     * @desc 处理极验验证结果
     * @url /api/v1/verify/callback
     * @method post
     * @author silveridc
     * @version v1
     * @param string ticket - 验证票据 required
     * @param string lot_number - 极验流水号 required
     * @param string captcha_output - 极验输出 required
     * @param string pass_token - 极验通过令牌 required
     * @param string gen_time - 生成时间 required
     * @return int status - 状态码
     * @return string msg - 提示信息
     * @return string data.code - 验证码
     */
    public function callback()
    {
        $param = $this->request->param();

        // 参数验证
        if (!$this->validate->scene('callback')->check($param)) {
            return json(['status' => 400, 'msg' => lang($this->validate->getError())]);
        }

        $result = $this->verifyModel->processCallback($param);

        return json($result);
    }

    /**
     * 时间 2026-02-09
     * @title 验证验证码
     * @desc 验证用户提交的验证码
     * @url /api/v1/verify/check
     * @method post
     * @author silveridc
     * @version v1
     * @param string group_id - 分组ID required
     * @param string user_id - 用户ID
     * @param string code - 验证码 required
     * @return int status - 状态码
     * @return string msg - 提示信息
     * @return bool passed - 是否通过
     * @return string data.user_id - 用户ID
     * @return string data.group_id - 分组ID
     */
    public function check()
    {
        $param = $this->request->param();

        // 参数验证
        if (!$this->validate->scene('check')->check($param)) {
            return json(['status' => 400, 'msg' => lang($this->validate->getError()), 'passed' => false]);
        }

        $result = $this->verifyModel->checkCode($param);

        return json($result);
    }

    /**
     * 时间 2026-02-09
     * @title 清理过期验证码
     * @desc 清理过期的验证码(仅默认API Key可调用)
     * @url /api/v1/verify/clean
     * @method post
     * @author silveridc
     * @version v1
     * @return int status - 状态码
     * @return string msg - 提示信息
     */
    public function clean()
    {
        // 权限检查
        if (!$this->isDefaultApiKey()) {
            return json(['status' => 403, 'msg' => lang('permission_denied_default_key_only')]);
        }

        $result = $this->verifyModel->cleanExpired();

        return json($result);
    }

    /**
     * 时间 2026-02-09
     * @title 重置API Key
     * @desc 重置当前API Key(仅默认API Key可调用)
     * @url /api/v1/verify/reset_key
     * @method post
     * @author silveridc
     * @version v1
     * @return int status - 状态码
     * @return string msg - 提示信息
     * @return int data.id - API Key ID
     * @return string data.value - 新的API Key值
     * @return int data.updated_at - 更新时间戳
     */
    public function resetKey()
    {
        $apiKeyId = $this->getApiKeyId();

        if ($apiKeyId <= 0) {
            return json(['status' => 401, 'msg' => lang('unauthorized')]);
        }

        // 权限检查
        if (!$this->isDefaultApiKey()) {
            return json(['status' => 403, 'msg' => lang('permission_denied_default_key_only')]);
        }

        $result = $this->verifyModel->resetApiKey($apiKeyId);

        return json($result);
    }

    /**
     * 时间 2026-02-09
     * @title 获取API Key ID
     * @desc 从中间件获取API Key ID
     * @author silveridc
     * @version v1
     * @return int
     */
    protected function getApiKeyId(): int
    {
        try {
            return (int)$this->request->middleware('api_key_id', 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 时间 2026-02-09
     * @title 是否为默认API Key
     * @desc 检查当前请求是否使用默认API Key
     * @author silveridc
     * @version v1
     * @return bool
     */
    protected function isDefaultApiKey(): bool
    {
        try {
            return (bool)$this->request->middleware('api_key_is_default', false);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
