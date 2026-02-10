<?php
namespace app\api\model;

use think\facade\Db;
use think\facade\Config;
use think\Model;

/**
 * @title 验证码业务模型
 * @desc 验证码业务模型
 * @use app\api\model\VerifyModel
 */
class VerifyModel extends Model
{
    protected $name = 'GeetestTable';

    protected $schema = [
        'id'            => 'int',
        'token'         => 'string',
        'api_key_id'    => 'int',
        'group_id'      => 'string',
        'user_id'       => 'string',
        'code'          => 'string',
        'verified'      => 'int',
        'used'          => 'int',
        'ip'            => 'string',
        'user_agent'    => 'string',
        'expire_at'     => 'int',
        'created_at'    => 'int',
        'updated_at'    => 'int',
        'verified_at'   => 'int',
        'used_at'       => 'int',
    ];

    /**
     * 时间 2026-02-10
     * @title 生成唯一Token
     * @desc 生成唯一Token
     * @author silveridc
     * @version v1
     */
    public function createToken($gid, $uid)
    {
        $salt = (string)Config::get('geetest.salt', '');
        return hash('sha256', $gid . $uid . time() . $salt);
    }

    /**
     * 时间 2026-02-10
     * @title 生成6位随机验证码
     * @author silveridc
     * @version v1
     */
    public function generateCode()
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }

    /**
     * 时间 2026-02-10
     * @title 创建验证票据
     * @author silveridc
     * @version v1
     */
    public function createTicket($param)
    {
        $time = time();
        $expire = (int)Config::get('geetest.notBefore', 300);

        $result = Db::name($this->name)->insert([
            'token'      => (string)$param['token'],
            'api_key_id' => intval($param['api_key_id'] ?? 0),
            'group_id'   => (string)$param['group_id'],
            'user_id'    => (string)$param['user_id'],
            'verified'   => 0,
            'used'       => 0,
            'ip'         => get_client_ip(),
            'user_agent' => mb_substr((string)request()->header('user-agent'), 0, 500),
            'expire_at'  => $time + $expire,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        return $result ? true : false;
    }

    /**
     * 时间 2026-02-10
     * @title 获取票据详情
     * @author silveridc
     * @version v1
     */
    public function getTicketDetail($token)
    {
        return Db::name($this->name)
            ->where('token', $token)
            ->where('expire_at', '>', time())
            ->find();
    }

    /**
     * 时间 2026-02-10
     * @title 完成极验验证
     * @desc 验证通过后更新状态并生成验证码
     * @author silveridc
     * @version v1
     */
    public function completeVerify($token)
    {
        $time = time();
        $row = Db::name($this->name)
            ->where('token', $token)
            ->where('expire_at', '>', $time)
            ->find();

        if (empty($row)) {
            return false;
        }

        // 已验证过直接返回
        if ($row['verified'] == 1) {
            return ['code' => $row['code']];
        }

        $code = $this->generateCode();

        $update = Db::name($this->name)->where('id', $row['id'])->update([
            'code'        => $code,
            'verified'    => 1,
            'verified_at' => $time,
            'updated_at'  => $time,
        ]);

        return $update ? ['code' => $code] : false;
    }

    /**
     * 时间 2026-02-10
     * @title 请求极验二次验证API
     * @author silveridc
     * @version v1
     */
    public function verifyGeetestApi($params)
    {
        $captchaId  = (string)Config::get('geetest.Captcha', '');
        $captchaKey = (string)Config::get('geetest.CaptchaKey', '');
        $apiServer  = (string)Config::get('geetest.ApiServer', 'https://gcaptcha4.geetest.com');

        $lotNumber = $params['lot_number'] ?? '';

        if (empty($lotNumber) || empty($captchaId) || empty($captchaKey)) {
            return false;
        }

        $signToken = hash_hmac('sha256', $lotNumber, $captchaKey);

        $postData = [
            'lot_number'     => $lotNumber,
            'captcha_output' => $params['captcha_output'] ?? '',
            'pass_token'     => $params['pass_token'] ?? '',
            'gen_time'       => $params['gen_time'] ?? '',
            'sign_token'     => $signToken,
            'captcha_id'     => $captchaId,
        ];

        $url = $apiServer . '/validate?captcha_id=' . $captchaId;

        $result = curl($url, $postData, 10, 'POST');

        if ($result['http_code'] !== 200 || !empty($result['error'])) {
            return false;
        }

        $res = json_decode($result['content'], true);
        return isset($res['result']) && $res['result'] === 'success';
    }

    /**
     * 时间 2026-02-10
     * @title 根据验证码查找有效票据
     * @author silveridc
     * @version v1
     */
    public function findByCode($code, $groupId)
    {
        return Db::name($this->name)
            ->where('code', $code)
            ->where('group_id', $groupId)
            ->where('verified', 1)
            ->where('used', 0)
            ->where('expire_at', '>', time())
            ->find();
    }

    /**
     * 时间 2026-02-10
     * @title 查找验证码全状态
     * @desc 用于诊断验证码为何不可用
     * @author silveridc
     * @version v1
     */
    public function findCodeStatus($code, $groupId)
    {
        return Db::name($this->name)
            ->where('code', $code)
            ->where('group_id', $groupId)
            ->find();
    }

    /**
     * 时间 2026-02-10
     * @title 核销验证码
     * @author silveridc
     * @version v1
     */
    public function markCodeAsUsed($code, $groupId)
    {
        $time = time();
        $row = Db::name($this->name)
            ->where('code', $code)
            ->where('group_id', $groupId)
            ->where('verified', 1)
            ->where('used', 0)
            ->where('expire_at', '>', $time)
            ->find();

        if (empty($row)) {
            return false;
        }

        Db::name($this->name)->where('id', $row['id'])->update([
            'used'       => 1,
            'used_at'    => $time,
            'updated_at' => $time,
        ]);

        return $row;
    }

    /**
     * 时间 2026-02-10
     * @title 清理过期数据
     * @author silveridc
     * @version v1
     */
    public function cleanExpired()
    {
        $apiKeyId  = intval(request()->api_key_id ?? 0);
        $isDefault = boolval(request()->api_key_is_default ?? false);

        $query = Db::name($this->name)->where('expire_at', '<', time());

        // 非默认Key只能清理自己的数据
        if (!$isDefault) {
            $query->where('api_key_id', $apiKeyId > 0 ? $apiKeyId : -1);
        }

        return $query->delete();
    }

    /**
     * 时间 2026-02-10
     * @title 获取极验ID
     * @author silveridc
     * @version v1
     */
    public function getCaptchaId()
    {
        return Config::get('geetest.captcha_id', '');
    }

    /**
     * 时间 2026-02-10
     * @title 获取过期时间配置
     * @author silveridc
     * @version v1
     */
    public function getCodeExpire()
    {
        return Config::get('geetest.notBefore', 300);
    }
}
