<?php
namespace app\controller;

use app\BaseController;
use app\model\GeetestModel;
use think\facade\Db;

class VerifyController extends BaseController
{
    /**
     * @title 生成验证链接
     * @desc 生成验证链接
     * @author VanillaNahida
     * @url /verify/create
     * @method post
     * @return json
     */
    public function create()
    {   
        //实例化模型类
        $GeetestModel = new GeetestModel();

        $apiKeyId = 0;
        try {
            $apiKeyId = (int)$this->request->middleware('api_key_id', 0);
        } catch (\Throwable $e) {
            $apiKeyId = 0;
        }

        $ip = '';
        try {
            $ip = (string)$this->request->ip();
        } catch (\Throwable $e) {
            $ip = '';
        }
        $limitKey = $apiKeyId > 0 ? ('rl:verify_create:api:' . $apiKeyId) : ('rl:verify_create:ip:' . ($ip !== '' ? $ip : 'unknown'));
        $retryAfter = rate_limit_hit($limitKey, 120, 60);
        if ($retryAfter > 0) {
            return json(['code' => 429, 'msg' => '请求过于频繁，请稍后重试'], 429)->header(['Retry-After' => (string)$retryAfter]);
        }

        //赋值参数
        $groupId = $this->request->post('group_id', '');
        $userId = $this->request->post('user_id', '');

        //不存在返回400
        if (empty($groupId) || empty($userId)) {
            $Errorresult = ['code' => 400, 'msg' => '参数错误'];
            return json($Errorresult, 400);
        }

        //生成唯一Token
        if (!ctype_digit($groupId) || !ctype_digit($userId)) {
            $Errorresult = ['code' => 400, 'msg' => '参数错误：group_id 和 user_id 必须为数字'];
            return json($Errorresult, 400);
        }

        $token = $GeetestModel->generateToken($groupId, $userId);
        
        //保存验证数据
        $GeetestModel->saveVerifyData($token, ['api_key_id' => $apiKeyId, 'group_id' => $groupId,'user_id' => $userId,'verified' => false,'code' => null]);
        
        // 生成验证链接
        $validate = $this->request->domain() . '/v/' . $token;
        $expire = $GeetestModel->getCodeExpire();
        //构建json并返回
        $result = ['code' => 0,'msg' => 'success','data' => ['ticket' => $token,'url' => $validate,'expire' => $expire,]];
        return json($result);
    }

    /**
     * 时间 2026-01-03
     * @title 生成用户访问的验证页
     * @desc 生成用户访问的验证页
     * @author VanillaNahida
     * @url /verify/page?=
     * @method get
     * @return html
     */
    public function page()
    {
        $ticket = (string)$this->request->route('ticket', '');
        if ($ticket === '') {
            return response('无效的验证链接', 400);
        }

        $htmlFile = root_path() . 'public' . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'verify' . DIRECTORY_SEPARATOR . 'index.html';
        if (!is_file($htmlFile)) {
            return response('验证页面资源缺失', 500);
        }

        $html = (string)file_get_contents($htmlFile);
        return response($html)->contentType('text/html');
    }

    public function adminPage()
    {
        $htmlFile = root_path() . 'public' . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'verify' . DIRECTORY_SEPARATOR . 'index.html';
        if (!is_file($htmlFile)) {
            return response('验证页面资源缺失', 500);
        }

        $html = (string)file_get_contents($htmlFile);
        return response($html)->contentType('text/html');
    }

    public function status()
    {
        $ticket = (string)$this->request->route('ticket', '');
        if ($ticket === '') {
            return json(['code' => 400, 'msg' => '参数错误'], 400);
        }

        $GeetestModel = new GeetestModel();
        $codeExpire = $GeetestModel->getCodeExpire();
        $expireMinutes = (int)ceil($codeExpire / 60);
        $data = $GeetestModel->getVerifyData($ticket);

        if (!$data) {
            return json(['code' => 404, 'msg' => '验证链接已过期或不存在'], 404);
        }

        if (!empty($data['verified'])) {
            return json([
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'ticket' => $ticket,
                    'verified' => true,
                    'code' => $data['code'],
                    'code_expire' => $codeExpire,
                    'expire_minutes' => $expireMinutes,
                ],
            ]);
        }

        $captchaId = $GeetestModel->getCaptchaId();
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'ticket' => $ticket,
                'verified' => false,
                'captcha_id' => $captchaId,
                'code_expire' => $codeExpire,
                'expire_minutes' => $expireMinutes,
            ],
        ]);
    }

    /**
     * 时间 2026-01-03
     * @title 处理极验验证结果
     * @desc 处理极验验证结果
     * @author VanillaNahida
     * @url /verify/callback
     * @method post
     * @return json
     */
    public function callback()
    {
        //实例化模型类
        $GeetestModel = new GeetestModel();

        $ip = '';
        try {
            $ip = (string)$this->request->ip();
        } catch (\Throwable $e) {
            $ip = '';
        }
        $retryAfter = rate_limit_hit('rl:verify_callback:' . ($ip !== '' ? $ip : 'unknown'), 30, 60);
        if ($retryAfter > 0) {
            return json(['code' => 429, 'msg' => '请求过于频繁，请稍后重试'], 429)->header(['Retry-After' => (string)$retryAfter]);
        }

        //从post请求里获取信息
        $ticket = $this->request->post('ticket', '');
        $lotNumber = $this->request->post('lot_number', '');
        $captchaOutput = $this->request->post('captcha_output', '');
        $passToken = $this->request->post('pass_token', '');
        $genTime = $this->request->post('gen_time', '');
        
        //ticket不存在的处理
        if (empty($ticket)) {
            return json(['code' => 400, 'msg' => '参数错误'], 400);
        }
        
        $data = $GeetestModel->getVerifyData($ticket);
        
        if (!$data) {
            return json(['code' => 404, 'msg' => '验证链接已过期或不存在'], 404);
        }
        
        if ($data['verified']) {
            return json(['code' => 0, 'msg' => '已验证', 'data' => ['code' => $data['code']]]);
        }
        
        //验证
        $param = ['lot_number' => $lotNumber,'captcha_output' => $captchaOutput,'pass_token' => $passToken,'gen_time' => $genTime,];
        $geetestResult = $GeetestModel->verifyGeetest($param);
        
        if (!$geetestResult) {
            $result=['code' => 400, 'msg' => '验证失败，请重试'];
            return json($result, 400);
        }
        
        //生成6位验证码
        $code = $GeetestModel->generateCode();
        
        // 更新验证数据
        $result = ['verified' => true, 'code' => $code, 'verified_at' => time()];
        $GeetestModel->updateVerifyData($ticket, $result);

        $jsonResult = ['code' => 0, 'msg' => '验证成功', 'data' => ['code' => $code]];
        return json($jsonResult);
    }

    /**
     * 时间 2026-01-03
     * @title 验证验证码
     * @desc 验证验证码
     * @author VanillaNahida
     * @url /verify/check
     * @method post
     * @return json
     */
    public function check()
    {
        //实例化模型类
        $GeetestModel = new GeetestModel();

        $ip = '';
        try {
            $ip = (string)$this->request->ip();
        } catch (\Throwable $e) {
            $ip = '';
        }
        $retryAfter = rate_limit_hit('rl:verify_check:' . ($ip !== '' ? $ip : 'unknown'), 60, 60);
        if ($retryAfter > 0) {
            return json(['code' => 429, 'msg' => '请求过于频繁，请稍后重试', 'passed' => false], 429)->header(['Retry-After' => (string)$retryAfter]);
        }

        $groupId = $this->request->post('group_id', '');
        $userId = $this->request->post('user_id', '');
        $code = $this->request->post('code', '');
        
        if (empty($groupId) || empty($code)) {
            return json(['code' => 400, 'msg' => '参数错误：缺少必填参数 group_id 或 code', 'passed' => false], 400);
        }
        
        // 查找匹配的验证码
        if (!ctype_digit($groupId)) {
            return json(['code' => 400, 'msg' => '参数错误：group_id 必须为数字', 'passed' => false], 400);
        }

        if (!empty($userId) && !ctype_digit($userId)) {
            return json(['code' => 400, 'msg' => '参数错误：user_id 必须为数字', 'passed' => false], 400);
        }
        
        $data = $GeetestModel->findByCode($code, $groupId);
        
        if (!$data) {
            $allStatusData = $GeetestModel->findCodeByAllStatus($code, $groupId);
            
            if ($allStatusData) {
                if ($allStatusData['used'] == 1) {
                    return json(['code' => 400, 'msg' => '验证失败：验证码已使用', 'passed' => false], 400);
                } elseif ($allStatusData['expire_at'] < time()) {
                    return json(['code' => 400, 'msg' => '验证失败：验证码已过期', 'passed' => false], 400);
                } elseif ($allStatusData['verified'] != 1) {
                    return json(['code' => 400, 'msg' => '验证失败：验证码未完成验证', 'passed' => false], 400);
                } else {
                    return json(['code' => 400, 'msg' => '验证失败：验证码不存在或已失效', 'passed' => false], 400);
                }
            }
            
            return json(['code' => 400, 'msg' => '验证失败：验证码不存在或已失效', 'passed' => false], 400);
        }
        
        if (!empty($userId) && $data['user_id'] !== $userId) {
            return json(['code' => 400, 'msg' => '验证失败：用户ID不匹配', 'passed' => false], 400);
        }

        // 标记验证码为已使用
        $GeetestModel->markCodeAsUsed($code, $groupId);
        
        //构建返回数据
        $result = ['code' => 0,'msg' => '验证通过','passed' => true,'data' => ['user_id' => $data['user_id'],'group_id' => $data['group_id'],]];
        return json($result);
    }

    /**
     * 时间 2026-01-03
     * @title 删除验证码
     * @desc 删除验证码
     * @author VanillaNahida
     * @url /verify/clean
     * @method post
     * @return json
     */
    public function clean()
    {
        $isDefaultKey = false;
        try {
            $isDefaultKey = (bool)$this->request->middleware('api_key_is_default', false);
        } catch (\Throwable $e) {
            $isDefaultKey = false;
        }
        if (!$isDefaultKey) {
            return json(['code' => 403, 'msg' => '权限不足：该接口仅允许默认 API Key 调用'], 403);
        }

        //实例化模型类
        $GeetestModel = new GeetestModel();
        $cleaned = $GeetestModel->cleanExpiredCodes();
        $result = ['code' => 0, 'msg' => "清理了 {$cleaned} 个过期验证码"];
        return json($result);
    }

    public function resetKey()
    {
        $apiKeyId = 0;
        try {
            $apiKeyId = (int)$this->request->middleware('api_key_id', 0);
        } catch (\Throwable $e) {
            $apiKeyId = 0;
        }
        if ($apiKeyId <= 0) {
            return json(['code' => 401, 'msg' => 'Unauthorized'], 401);
        }

        $isDefaultKey = false;
        try {
            $isDefaultKey = (bool)$this->request->middleware('api_key_is_default', false);
        } catch (\Throwable $e) {
            $isDefaultKey = false;
        }
        if (!$isDefaultKey) {
            return json(['code' => 403, 'msg' => '权限不足：该接口仅允许默认 API Key 调用'], 403);
        }

        $retryAfter = rate_limit_hit('rl:api_key_reset:' . $apiKeyId, 3, 60);
        if ($retryAfter > 0) {
            return json(['code' => 429, 'msg' => '请求过于频繁，请稍后重试'], 429)->header(['Retry-After' => (string)$retryAfter]);
        }

        ensure_api_keys_table();

        $newValue = '';
        $updated = 0;
        $ts = time();
        for ($i = 0; $i < 3; $i++) {
            try {
                try {
                    $newValue = bin2hex(random_bytes(32));
                } catch (\Throwable $e) {
                    $newValue = hash('sha256', uniqid('api_key_reset', true) . microtime(true) . $i);
                }

                $updated = (int)Db::name('api_keys')->where('id', $apiKeyId)->update([
                    'value' => $newValue,
                    'updated_at' => $ts,
                ]);
                if ($updated > 0) {
                    break;
                }
            } catch (\Throwable $e) {
                $updated = 0;
            }
        }

        if ($updated <= 0 || $newValue === '') {
            return json(['code' => 500, 'msg' => '重置失败'], 500);
        }

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'id' => $apiKeyId,
                'value' => $newValue,
                'updated_at' => $ts,
            ],
        ]);
    }
}
