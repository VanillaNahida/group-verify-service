<?php
namespace app\admin\model;

use think\facade\Db;
use think\Model;

/**
 * @title API调用日志模型
 * @desc API调用日志数据操作
 * @use app\admin\model\ApiCallLogModel
 */
class ApiCallLogModel extends Model
{
    protected $name = 'api_call_logs';

    protected $schema = [
        'id'          => 'int',
        'api_key_id'  => 'int',
        'endpoint'    => 'string',
        'method'      => 'string',
        'status_code' => 'int',
        'group_id'    => 'string',
        'user_id'     => 'string',
        'ticket'      => 'string',
        'code'        => 'string',
        'ip'          => 'string',
        'user_agent'  => 'string',
        'duration_ms' => 'int',
        'created_at'  => 'int',
    ];

    /**
     * 时间 2026-02-10
     * @title 获取日志列表
     * @desc 支持多条件筛选及分页
     * @author silveridc
     * @version v1
     * @param array $param
     * @return array
     */
    public function apiCallLogList($param)
    {
        $page = !empty($param['page']) ? max(1, intval($param['page'])) : 1;
        $pageSize = !empty($param['page_size']) ? min(200, max(1, intval($param['page_size']))) : 20;

        $query = Db::name($this->name);

        // 时间范围
        if (!empty($param['from'])) {
            $query->where('created_at', '>=', intval($param['from']));
        }
        if (!empty($param['to'])) {
            $query->where('created_at', '<=', intval($param['to']));
        }

        // 精确筛选
        if (!empty($param['api_key_id'])) {
            $query->where('api_key_id', intval($param['api_key_id']));
        }
        if (!empty($param['status_code'])) {
            $query->where('status_code', intval($param['status_code']));
        }
        if (!empty($param['group_id'])) {
            $query->where('group_id', trim($param['group_id']));
        }
        if (!empty($param['user_id'])) {
            $query->where('user_id', trim($param['user_id']));
        }

        // 模糊筛选
        if (!empty($param['endpoint'])) {
            $query->where('endpoint', 'like', '%' . trim($param['endpoint']) . '%');
        }

        $total = $query->count();

        $rows = $query->order('id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'id'          => (int)($r['id'] ?? 0),
                'created_at'  => (int)($r['created_at'] ?? 0),
                'api_key_id'  => (int)($r['api_key_id'] ?? 0),
                'endpoint'    => (string)($r['endpoint'] ?? ''),
                'method'      => (string)($r['method'] ?? ''),
                'status_code' => (int)($r['status_code'] ?? 0),
                'group_id'    => (string)($r['group_id'] ?? ''),
                'user_id'     => (string)($r['user_id'] ?? ''),
                'ticket'      => (string)($r['ticket'] ?? ''),
                'code'        => (string)($r['code'] ?? ''),
                'ip'          => (string)($r['ip'] ?? ''),
                'user_agent'  => (string)($r['user_agent'] ?? ''),
                'duration_ms' => (int)($r['duration_ms'] ?? 0),
            ];
        }

        return [
            'list'      => $list,
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 时间 2026-02-10
     * @title 获取单条日志详情
     * @author silveridc
     * @version v1
     * @param int $id
     * @return array|null
     */
    public function indexApiCallLog($id)
    {
        $row = Db::name($this->name)->where('id', $id)->find();

        if (empty($row)) {
            return null;
        }

        return [
            'id'          => (int)($row['id'] ?? 0),
            'created_at'  => (int)($row['created_at'] ?? 0),
            'api_key_id'  => (int)($row['api_key_id'] ?? 0),
            'endpoint'    => (string)($row['endpoint'] ?? ''),
            'method'      => (string)($row['method'] ?? ''),
            'status_code' => (int)($row['status_code'] ?? 0),
            'group_id'    => (string)($row['group_id'] ?? ''),
            'user_id'     => (string)($row['user_id'] ?? ''),
            'ticket'      => (string)($row['ticket'] ?? ''),
            'code'        => (string)($row['code'] ?? ''),
            'ip'          => (string)($row['ip'] ?? ''),
            'user_agent'  => (string)($row['user_agent'] ?? ''),
            'duration_ms' => (int)($row['duration_ms'] ?? 0),
        ];
    }

    /**
     * 时间 2026-02-10
     * @title 写入日志
     * @desc 记录一条API调用日志
     * @author silveridc
     * @version v1
     * @param array $data
     * @return bool
     */
    public function addLog($data)
    {
        $insertData = [
            'api_key_id'  => intval($data['api_key_id'] ?? 0) ?: null,
            'endpoint'    => (string)($data['endpoint'] ?? '/'),
            'method'      => (string)($data['method'] ?? 'GET'),
            'status_code' => intval($data['status_code'] ?? 200),
            'group_id'    => !empty($data['group_id']) ? (string)$data['group_id'] : null,
            'user_id'     => !empty($data['user_id']) ? (string)$data['user_id'] : null,
            'ticket'      => !empty($data['ticket']) ? (string)$data['ticket'] : null,
            'code'        => !empty($data['code']) ? (string)$data['code'] : null,
            'ip'          => !empty($data['ip']) ? (string)$data['ip'] : null,
            'user_agent'  => !empty($data['user_agent']) ? (string)$data['user_agent'] : null,
            'duration_ms' => intval($data['duration_ms'] ?? 0),
            'created_at'  => time(),
        ];

        return Db::name($this->name)->insert($insertData) ? true : false;
    }

    /**
     * 时间 2026-02-10
     * @title 清理过期日志
     * @desc 删除指定天数之前的日志
     * @author silveridc
     * @version v1
     * @param int $days 保留天数
     * @return int 删除的记录数
     */
    public function cleanOldLogs($days = 30)
    {
        $expireTime = time() - ($days * 86400);
        return Db::name($this->name)->where('created_at', '<', $expireTime)->delete();
    }

    /**
     * 时间 2026-02-10
     * @title 统计24小时内调用数
     * @author silveridc
     * @version v1
     * @return int
     */
    public function count24h()
    {
        $from = time() - 86400;
        return (int)Db::name($this->name)->where('created_at', '>=', $from)->count();
    }

    /**
     * 时间 2026-02-10
     * @title 统计24小时内错误数
     * @author silveridc
     * @version v1
     * @return int
     */
    public function countError24h()
    {
        $from = time() - 86400;
        return (int)Db::name($this->name)
            ->where('created_at', '>=', $from)
            ->where('status_code', '>=', 400)
            ->count();
    }

    /**
     * 时间 2026-02-10
     * @title 按接口统计调用数
     * @author silveridc
     * @version v1
     * @param int $limit
     * @return array
     */
    public function topEndpoints($limit = 10)
    {
        $from = time() - 86400;

        try {
            $rows = Db::name($this->name)
                ->field('endpoint, COUNT(1) AS cnt')
                ->where('created_at', '>=', $from)
                ->group('endpoint')
                ->order('cnt', 'desc')
                ->limit($limit)
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
     * 时间 2026-02-10
     * @title 按分组统计调用数
     * @author silveridc
     * @version v1
     * @param int $limit
     * @return array
     */
    public function topGroups($limit = 10)
    {
        $from = time() - 86400;

        try {
            $rows = Db::name($this->name)
                ->field('group_id, COUNT(1) AS cnt')
                ->where('created_at', '>=', $from)
                ->where('group_id', '<>', '')
                ->group('group_id')
                ->order('cnt', 'desc')
                ->limit($limit)
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
     * 时间 2026-02-10
     * @title 获取最近调用记录
     * @author silveridc
     * @version v1
     * @param int $limit
     * @return array
     */
    public function recentLogs($limit = 20)
    {
        try {
            $rows = Db::name($this->name)
                ->order('id', 'desc')
                ->limit($limit)
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
}
