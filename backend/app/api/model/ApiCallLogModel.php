<?php
namespace app\api\model;

use think\facade\Db;
use think\Model;

/**
 * @title API调用日志模型
 * @desc 负责收集请求数据、关联业务并写入数据库
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
     * @title 判断是否需要记录
     * @desc 过滤不需要记录日志的静态资源或非核心接口
     */
    public function shouldLog($request)
    {
        $path = trim((string)$request->pathinfo(), '/');
        $targets = [
            'v1/verify/create',
            'v1/verify/check',
            'v1/verify/clean',
            'v1/verify/reset_key',
            'v1/verify/callback'
        ];
        return in_array($path, $targets);
    }

    /**
     * 时间 2026-02-10
     * @title 核心记录方法
     * @desc 整合请求、响应、时长及异常信息并入库
     */
    public function record($request, $response, $startTime, $exception = null)
    {
        $duration = (int)max(0, round((microtime(true) - $startTime) * 1000));
        
        // 1. 基础数据收集
        $data = [
            'api_key_id'  => (int)($request->api_key_id ?? 0),
            'endpoint'    => '/' . trim((string)$request->pathinfo(), '/'),
            'method'      => strtoupper((string)$request->method()),
            'status_code' => $exception ? 500 : ($response ? (int)$response->getCode() : 200),
            'ip'          => (string)$request->ip(),
            'user_agent'  => mb_substr((string)$request->header('user-agent'), 0, 500),
            'duration_ms' => $duration,
            'created_at'  => time(),
        ];

        // 2. 业务参数收集
        $data['group_id'] = (string)$request->post('group_id', '');
        $data['user_id']  = (string)$request->post('user_id', '');
        $data['ticket']   = (string)$request->post('ticket', '');
        $data['code']     = (string)$request->post('code', '');

        // 3. 数据自动补全 (如果 POST 里没传 group_id/user_id，尝试从 ticket 关联表查)
        $data = $this->enrichDataByTicket($data);

        // 4. 使用 Db 门面写入数据
        return Db::name($this->name)->insert($this->filterEmpty($data));
    }

    /**
     * 时间 2026-02-10
     * @title 数据补全
     * @desc 当请求参数不足时，通过 ticket 查找对应的 group_id 和 user_id
     */
    protected function enrichDataByTicket($data)
    {
        if (empty($data['ticket'])) {
            return $data;
        }

        // 如果已经有关键数据，则不查库
        if (!empty($data['group_id']) && !empty($data['user_id']) && $data['api_key_id'] > 0) {
            return $data;
        }

        $row = Db::name('GeetestTable')
            ->field('api_key_id, group_id, user_id')
            ->where('token', $data['ticket'])
            ->find();

        if (!empty($row)) {
            $data['api_key_id'] = $data['api_key_id'] ?: (int)$row['api_key_id'];
            $data['group_id']   = $data['group_id']   ?: (string)$row['group_id'];
            $data['user_id']    = $data['user_id']    ?: (string)$row['user_id'];
        }

        return $data;
    }

    protected function filterEmpty($data)
    {
        foreach ($data as $key => $val) {
            if ($val === '') {
                $data[$key] = null;
            }
        }
        return $data;
    }
}
