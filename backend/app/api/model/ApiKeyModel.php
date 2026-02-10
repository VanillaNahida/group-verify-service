<?php
namespace app\api\model;

use think\facade\Db;
use think\Model;
/**
 * @title API密钥模型
 * @desc 管理 API Key 的读取、迁移及验证
 * @use app\api\model\ApiKeyModel
 */
class ApiKeyModel extends Model
{
    protected $name = 'api_keys';

    // 字段定义
    protected $schema = [
        'id'             => 'int',
        'value'          => 'string',
        'status'         => 'int',
        'created_at'     => 'int',
        'updated_at'     => 'int',
    ];

    /**
     * @title 获取apikey列表
     * @desc 获取有效的apikey列表
     * @author silveridc(admin@silveridc.cn)
     * @version v1
     * @return array
     */
    public function getValidKeys()
    {
        // 从数据库获取
        $rows = Db::name($this->name)->where('status', 1)->field('id,value')->select()->toArray();
        
        // !empty 判断不为空 直接返回
        if (!empty($rows)) {
            return $rows;
        }

        // 如果为空 直接爆
        throw new \think\Exception('表数据为空', 10006);
    }

    /**
     * @title 检查apikey是否存在
     * @desc 检查apikey是否存在有效
     * @author silveridc
     * @version v1
     * @param string $apiKey
     */
    public function findMatchedKey($token, $rows)
    {
        foreach ($rows as $row) {
            if (hash_equals((string)$row['value'], $token)) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @title 检查是否为默认ApiKey
     * @desc 检查是否为默认ApiKey
     * @author silveridc
     * @version v1
     */
    public function isDefaultKey($id)
    {
        $defaultId = Db::name($this->name)->order('id', 'asc')->value('id');
        return $id > 0 && $id == $defaultId;
    }

    /**
     * @title 解析ApiKey
     * @desc 解析json,分隔符这样的ApiKey列表
     * @author silveridc
     * @version v1
     */
    protected function parseRawKeys($raw)
    {
        $raw = trim($raw);
        if (empty($raw)) return [];

        // 尝试解析 JSON
        if (str_starts_with($raw, '[') && str_ends_with($raw, ']')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_filter(array_map('trim', $decoded));
            }
        }

        // 尝试解析分隔符 (逗号、分号、空格)
        $parts = preg_split('/[,\s;，；]+/u', $raw);
        return array_filter(array_map('trim', $parts));
    }
}