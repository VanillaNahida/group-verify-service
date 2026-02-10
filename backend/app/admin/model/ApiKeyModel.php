<?php
namespace app\admin\model;

use think\facade\Db;
use think\Model;
use think\facade\Env;
/**
 * @title API密钥管理模型
 * @desc 处理后台API密钥的增删改查及数据迁移逻辑
 * @use app\admin\model\ApiKeyModel
 */
class ApiKeyModel extends Model
{
    // 指定表名
    protected $name = 'api_keys';

    // 设置字段信息
    protected $schema = [
        'id'             => 'int',
        'value'          => 'string',
        'status'         => 'int',
        'last_access_at' => 'int',
        'created_at'     => 'int',
        'updated_at'     => 'int',
    ];

    /**
     * 时间 2026-02-10
     * @title 获取API密钥列表
     * @desc 支持分页及基本筛选
     * @author silveridc
     * @version v1
     * @param array $param 筛选及分页参数
     * @return array
     */
    public function apiKeyList($param)
    {
        $this->ensureApiKeysMigrated();

        $page = !empty($param['page']) ? intval($param['page']) : 1;
        $limit = !empty($param['limit']) ? intval($param['limit']) : 20;

        $query = Db::name($this->name);

        if (!empty($param['id'])) {
            $query->where('id', intval($param['id']));
        }

        $count = $query->count();

        $rows = $query->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $defaultId = $this->getDefaultApiKeyId();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id'         => $row['id'],
                'is_default' => $defaultId > 0 && $row['id'] === $defaultId,
                'masked'     => maskSecret($row['value']),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        return [
            'items' => $items,
            'count' => $count
        ];
    }

    /**
     * 时间 2026-02-10
     * @title 获取单个密钥详情
     * @desc 获取单个密钥详情
     * @author silveridc
     * @version v1
     * @param int $id 密钥ID
     */
    public function indexApiKey($id)
    {
        $row = Db::name($this->name)->where('id', $id)->find();
        if (!$row) return null;

        $defaultId = $this->getDefaultApiKeyId();

        return [
            'id'         => $row['id'],
            'is_default' => $defaultId > 0 && $row['id'] === $defaultId,
            'masked'     => maskSecret($row['value']),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * 时间 2026-02-10
     * @title 创建API密钥
     * @desc 创建api密钥
     * @author silveridc
     * @version v1
     * @param array $param
     * @return array
     */
    public function createApiKey($param)
    {
        $value = !empty($param['value']) ? trim((string)$param['value']) : '';

        if ($value === '') {
            $value = bin2hex(random_bytes(32));
        }

        if (mb_strlen($value) < 16) {
            return ['status' => 400, 'msg' => lang('api_key_length_error')];
        }

        $time = time();
        $id = Db::name($this->name)->insertGetId([
            'value'      => $value,
            'status'     => 1,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        return [
            'status' => 200,
            'msg'    => lang('success_message'),
            'data'   => [
                'id'         => $id,
                'value'      => $value,
                'masked'     => maskSecret($value),
                'created_at' => $time,
            ]
        ];
    }

    /**
     * 时间 2026-02-10
     * @title 重置API密钥
     * @desc 重置api密钥
     * @author silveridc
     */
    public function resetApiKey($id)
    {
        $newValue = bin2hex(random_bytes(32));
        $time = time();

        $update = Db::name($this->name)->where('id', $id)->update([
            'value'      => $newValue,
            'updated_at' => $time,
        ]);

        if (!$update) {
            return ['status' => 500, 'msg' => lang('reset_fail')];
        }

        return [
            'status' => 200,
            'msg'    => lang('success_message'),
            'data'   => [
                'id'         => $id,
                'value'      => $newValue,
                'masked'     => maskSecret($newValue),
                'updated_at' => $time,
            ]
        ];
    }

    /**
     * 时间 2026-02-10
     * @title 删除API密钥
     * @desc 删除api密钥
     * @author silveridc
     */
    public function deleteApiKey($id)
    {
        $defaultId = $this->getDefaultApiKeyId();
        if ($id == $defaultId) {
            return ['status' => 403, 'msg' => lang('api_key_default_cannot_delete')];
        }

        $delete = Db::name($this->name)->where('id', $id)->delete();
        
        return $delete ? ['status' => 200, 'msg' => lang('success_message')] : ['status' => 400, 'msg' => lang('delete_fail')];
    }

    /**
     * @title 检查是否为默认ApiKey
     * @desc 检查是否为默认ApiKey
     * @author silveridc
     * @version v1
     */
    public function getDefaultApiKeyId()
    {
        return (int)Db::name($this->name)->order('id', 'asc')->value('id');
    }
    
    /**
     * 时间 2026-02-10
     * @title 数据迁移初始化
     * @desc 如果表为空，从旧配置中导入数据
     */
    protected function ensureApiKeysMigrated()
    {
        $exists = Db::name($this->name)->limit(1)->find();
        if ($exists) return;
        $raw = (string)env('API_KEY', '');
        if (empty($raw)) {
            try {
                $raw = (string)Db::name('settings')->where('name', 'API_KEY')->value('value');
            } catch (\Throwable $e) {}
        }

        if (empty($raw)) return;

        // 简易解析解析 (逗号分隔)
        $keys = array_filter(array_map('trim', explode(',', $raw)));
        $time = time();
        foreach ($keys as $key) {
            Db::name($this->name)->insert([
                'value'      => $key,
                'status'     => 1,
                'created_at' => $time,
                'updated_at' => $time
            ]);
        }
    }
}
