<?php
namespace app\admin\model;

use think\facade\Db;
use think\facade\Config;
use think\Model;

/**
 * @title 系统设置模型
 * @desc 系统配置管理
 * @use app\admin\model\SettingModel
 */
class SettingModel extends Model
{
    protected $name = 'settings';

    protected $schema = [
        'id'         => 'int',
        'name'       => 'string',
        'value'      => 'string',
        'created_at' => 'int',
        'updated_at' => 'int',
    ];

    /**
     * 时间 2026-02-10
     * @title 获取配置白名单
     * @desc 定义允许管理的配置项
     * @author silveridc
     * @version v1
     * @return array
     */
    public function getWhitelist()
    {
        return [
            ['key' => 'GEETEST_CAPTCHA_ID',  'label' => '极验验证ID',     'secret' => false, 'type' => 'string', 'config' => 'geetest.Captcha'],
            ['key' => 'GEETEST_CAPTCHA_KEY', 'label' => '极验验证密钥',   'secret' => true,  'type' => 'string', 'config' => 'geetest.CaptchaKey'],
            ['key' => 'GEETEST_API_SERVER',  'label' => '极验API服务器',  'secret' => false, 'type' => 'url',    'config' => 'geetest.ApiServer'],
            ['key' => 'GEETEST_CODE_EXPIRE', 'label' => '验证码过期时间', 'secret' => false, 'type' => 'int',    'config' => 'geetest.notBefore'],
            ['key' => 'SALT',                'label' => '加密盐值',       'secret' => true,  'type' => 'string', 'config' => 'geetest.salt'],
        ];
    }

    /**
     * 时间 2026-02-10
     * @title 获取系统设置列表
     * @author silveridc
     * @version v1
     * @return array
     */
    public function settingList()
    {
        $whitelist = $this->getWhitelist();
        $list = [];

        foreach ($whitelist as $def) {
            $key = $def['key'];
            $configKey = $def['config'] ?? '';

            // 优先从数据库读取
            $value = $this->getSettingValue($key);

            // 数据库没有则从 Config 获取默认值
            if ($value === null || $value === '') {
                $value = $configKey ? (string)Config::get($configKey, '') : '';
            }

            $isSecret = $def['secret'] ?? false;

            $list[] = [
                'key'    => $key,
                'label'  => $def['label'] ?? $key,
                'type'   => $def['type'] ?? 'string',
                'is_set' => $value !== '',
                'value'  => $isSecret ? '' : $value,
                'masked' => $isSecret ? maskSecret($value) : '',
            ];
        }

        return ['list' => $list];
    }

    /**
     * 时间 2026-02-10
     * @title 更新系统设置
     * @author silveridc
     * @version v1
     * @param array $param
     * @return array
     */
    public function updateSettings($param)
    {
        $values = $param['values'] ?? [];

        if (!is_array($values)) {
            return ['status' => 400, 'msg' => lang('param_error')];
        }

        // 构建白名单映射
        $defs = [];
        foreach ($this->getWhitelist() as $def) {
            $defs[$def['key']] = $def;
        }

        // 验证
        $errors = $this->validateValues($values, $defs);
        if (!empty($errors)) {
            return ['status' => 400, 'msg' => implode('；', $errors)];
        }

        // 保存
        foreach ($values as $key => $value) {
            if (!isset($defs[$key])) continue;

            $v = is_string($value) ? trim($value) : '';
            if ($v === '') continue;

            $this->setSettingValue($key, $v);
        }

        $result =  [
            'status' => 200,
            'msg'    => lang('success_message'),
            'data'   => $this->settingList()
        ];
        return $result;
    }

    /**
     * 时间 2026-02-10
     * @title 获取仪表盘数据
     * @author silveridc
     * @version v1
     * @return array
     */
    public function getDashboardData()
    {
        $now = time();
        $from24h = $now - 86400;

        return [
            'now'                    => $now,
            'api_keys_total'         => $this->safeCount('api_keys'),
            'tickets_total'          => $this->safeCount('GeetestTable'),
            'tickets_verified_total' => $this->safeCount('GeetestTable', [['verified', '=', 1]]),
            'tickets_used_total'     => $this->safeCount('GeetestTable', [['used', '=', 1]]),
            'tickets_pending'        => $this->safeCount('GeetestTable', [['verified', '=', 0], ['expire_at', '>', $now]]),
            'tickets_expired_total'  => $this->safeCount('GeetestTable', [['expire_at', '<=', $now]]),
            'calls_24h_total'        => $this->safeCount('api_call_logs', [['created_at', '>=', $from24h]]),
            'calls_24h_error'        => $this->safeCount('api_call_logs', [['created_at', '>=', $from24h], ['status_code', '>=', 400]]),
            'calls_24h_by_endpoint'  => $this->getCallsByEndpoint($from24h),
            'calls_24h_top_groups'   => $this->getTopGroups($from24h),
            'recent_calls'           => $this->getRecentCalls(),
        ];
    }

    //辅助方法 start
    // 获取单个配置值
    protected function getSettingValue($key)
    {
        try {
            $value = Db::name($this->name)->where('name', $key)->value('value');
            if ($value !== null) return (string)$value;

            // 兼容旧字段名
            $value = Db::name($this->name)->where('key', $key)->value('value');
            if ($value !== null) return (string)$value;
        } catch (\Throwable $e) {}

        return null;
    }

    // 设置单个配置值
    protected function setSettingValue($key, $value)
    {
        $time = time();

        // 尝试更新
        $updated = Db::name($this->name)->where('name', $key)->update([
            'value'      => $value,
            'updated_at' => $time,
        ]);

        if ($updated) return true;

        // 不存在则插入
        try {
            Db::name($this->name)->insert([
                'name'       => $key,
                'value'      => $value,
                'created_at' => $time,
                'updated_at' => $time,
            ]);
        } catch (\Throwable $e) {}

        return true;
    }

    // 验证输入值
    protected function validateValues($values, $defs)
    {
        $errors = [];

        foreach ($values as $key => $value) {
            if (!isset($defs[$key])) continue;

            $v = is_string($value) ? trim($value) : '';
            if ($v === '') continue;

            $type = $defs[$key]['type'] ?? 'string';

            if ($type === 'int') {
                if (!ctype_digit($v)) {
                    $errors[] = $key . ' ' . lang('must_be_integer');
                    continue;
                }
                $n = (int)$v;
                if ($key === 'GEETEST_CODE_EXPIRE' && ($n < 30 || $n > 3600)) {
                    $errors[] = $key . ' ' . lang('range_30_3600');
                }
            }

            if ($type === 'url' && !filter_var($v, FILTER_VALIDATE_URL)) {
                $errors[] = $key . ' ' . lang('invalid_url');
            }

            if ($key === 'SALT' && mb_strlen($v) < 32) {
                $errors[] = $key . ' ' . lang('salt_min_length');
            }
        }

        return $errors;
    }
    /**
     * 按接口统计调用数
     */
    protected function getCallsByEndpoint($from)
    {
        try {
            $rows = Db::name('api_call_logs')
                ->field('endpoint, COUNT(1) AS cnt')
                ->where('created_at', '>=', $from)
                ->group('endpoint')
                ->order('cnt', 'desc')
                ->limit(10)
                ->select()
                ->toArray();

            $result = [];
            foreach ($rows as $r) {
                $result[] = [
                    'endpoint' => (string)($r['endpoint'] ?? ''),
                    'count'    => (int)($r['cnt'] ?? 0),
                ];
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 获取调用量最高的分组
     */
    protected function getTopGroups($from)
    {
        try {
            $rows = Db::name('api_call_logs')
                ->field('group_id, COUNT(1) AS cnt')
                ->where('created_at', '>=', $from)
                ->where('group_id', '<>', '')
                ->group('group_id')
                ->order('cnt', 'desc')
                ->limit(10)
                ->select()
                ->toArray();

            $result = [];
            foreach ($rows as $r) {
                $gid = (string)($r['group_id'] ?? '');
                if ($gid === '') continue;
                $result[] = [
                    'group_id' => $gid,
                    'count'    => (int)($r['cnt'] ?? 0),
                ];
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 获取最近的调用记录
     */
    protected function getRecentCalls()
    {
        try {
            $rows = Db::name('api_call_logs')
                ->order('id', 'desc')
                ->limit(20)
                ->select()
                ->toArray();

            $result = [];
            foreach ($rows as $r) {
                $result[] = [
                    'id'          => (int)($r['id'] ?? 0),
                    'created_at'  => (int)($r['created_at'] ?? 0),
                    'api_key_id'  => (int)($r['api_key_id'] ?? 0),
                    'endpoint'    => (string)($r['endpoint'] ?? ''),
                    'method'      => (string)($r['method'] ?? ''),
                    'status_code' => (int)($r['status_code'] ?? 0),
                    'group_id'    => (string)($r['group_id'] ?? ''),
                    'user_id'     => (string)($r['user_id'] ?? ''),
                    'duration_ms' => (int)($r['duration_ms'] ?? 0),
                ];
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }
    //辅助方法 end
    protected function safeCount($table, $where = [])
    {
        try {
            $query = Db::name($table);
            foreach ($where as $w) {
                if (is_array($w) && count($w) >= 3) {
                    $query->where($w[0], $w[1], $w[2]);
                }
            }
            return (int)$query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }


}
