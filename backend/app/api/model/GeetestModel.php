<?php
namespace app\api\model;

use think\Model;
use think\facade\Config;
/**
 * @title 极验验证模型
 * @desc 处理验证的生命周期，包括创建、状态更新及核销
 * @use app\api\model\GeetestModel
 */
class GeetestModel extends Model
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
     * @title 保存验证原始数据
     * @desc 创建一个新的验证记录
     * @author silveridc
     * @version v1
     * @param string $token 唯一标识
     * @param array $data 包含 api_key_id, group_id, user_id 等
     * @return bool
     */
    public function saveVerifyData($token, array $data)
    {
        $time = time();
        $expire = intval(Config::get('geetest.notBefore')) ?: 300;

        $result = $this->create([
            'token'      => $token,
            'api_key_id' => intval($data['api_key_id'] ?? 0),
            'group_id'   => (string)$data['group_id'],
            'user_id'    => (string)$data['user_id'],
            'verified'   => 0,
            'used'       => 0,
            'ip'         => get_client_ip(),
            'user_agent' => substr((string)request()->header('user-agent'), 0, 500),
            'expire_at'  => $time + $expire,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        return $result ? true : false;
    }

    /**
     * 时间 2026-02-10
     * @title 更新验证状态
     * @desc 极验回调成功后，更新为已验证并生成验证码
     * @author silveridc
     * @version v1
     * @param string $token 唯一标识
     * @param string $code 生成的6位验证码
     * @return bool
     */
    public function updateVerifyStatus($token, $code)
    {
        $time = time();
        $ticket = $this->where('token', $token)
            ->where('expire_at', '>', $time)
            ->find();

        if (!empty($ticket)) {
            $result = $ticket->save([
                'code'        => $code,
                'verified'    => 1,
                'verified_at' => $time,
                'updated_at'  => $time
            ]);
            return $result ? true : false;
        }

        return false;
    }

    /**
     * 时间 2026-02-10
     * @title 验证验证码
     * @desc 验证验证码
     * @author silveridc
     * @version v1
     * @param string $code 验证码
     * @param string $groupId 分组ID
     * @return array|bool
     */
    public function markCodeAsUsed($code, $groupId)
    {
        $time = time();
        $ticket = $this->where('code', $code)
            ->where('group_id', $groupId)
            ->where('verified', 1)
            ->where('used', 0)
            ->where('expire_at', '>', $time)
            ->find();

        if (!empty($ticket)) {
            $ticket->save([
                'used'       => 1,
                'used_at'    => $time,
                'updated_at' => $time
            ]);
            return $ticket->toArray();
        }

        return false;
    }

    /**
     * 时间 2026-02-10
     * @title 获取详情
     * @desc 根据 Token 获取未过期的验证记录
     * @author silveridc
     * @version v1
     */
    public function getTicket($token)
    {
        $ticket = $this->where('token', $token)
            ->where('expire_at', '>', time())
            ->find();

        return !empty($ticket) ? $ticket->toArray() : null;
    }

    /**
     * 时间 2026-02-10
     * @title 获取所有状态的验证码信息
     * @desc 用于校验失败时返回具体错误原因
     * @author silveridc
     * @version v1
     */
    public function findCodeByAllStatus($code, $groupId)
    {
        $ticket = $this->where('code', $code)
            ->where('group_id', $groupId)
            ->find();

        return !empty($ticket) ? $ticket->toArray() : null;
    }
}
