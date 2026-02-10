<?php
namespace app\admin\controller;

use app\admin\model\SettingModel;
use app\admin\model\ApiCallLogModel;
use app\admin\validate\SettingValidate;

/**
 * @title 系统设置
 * @desc 系统配置管理
 * @use app\admin\controller\SettingController
 */
#[\AllowDynamicProperties]
class SettingController extends AdminBaseController
{
    public function initialize()
    {
        parent::initialize();
        $this->validate = new SettingValidate();
    }

    /**
     * 时间 2026-02-09
     * @title 获取系统设置
     * @desc 获取系统设置
     * @url /admin/v1/setting
     * @method GET
     * @author silveridc
     * @version v1
     * @return array list - 配置项列表
     * @return string list[].key - 配置键名
     * @return string list[].label - 配置标签
     * @return bool list[].is_set - 是否已设置
     * @return string list[].value - 配置值(敏感项为空)
     * @return string list[].masked - 脱敏后的值
     * @return string list[].source - 值来源(DB/ENV/DEFAULT)
     */
    public function settingList()
    {
        //实例化模型类
        $settingModel = new SettingModel;

        $result = [
            'status' => 200,
            'msg'    => lang('success_message'),
            'data'   => $settingModel->settingList()
        ];

        return json($result);
    }

    /**
     * 时间 2026-02-09
     * @title 更新系统设置
     * @desc 批量更新系统配置项
     * @url /admin/v1/setting
     * @method PUT
     * @author silveridc
     * @version v1
     * @param array values - 配置键值对 required
     * @return array list - 更新后的配置项列表
     */
    public function update()
    {
        $param = $this->request->param();
        
        //实例化模型类
        $settingModel = new SettingModel;

        // 参数验证
        if (!$this->validate->scene('update')->check($param)) {
            return json(['status' => 400, 'msg' => lang($this->validate->getError())]);
        }

        $result = $settingModel->updateSettings($param);

        return json($result);
    }

    /**
     * 时间 2026-02-09
     * @title 仪表盘数据
     * @desc 获取系统仪表盘统计数据
     * @url /admin/v1/dashboard
     * @method GET
     * @author silveridc
     * @version v1
     * @return int now - 当前时间戳
     * @return int api_keys_total - API密钥总数
     * @return int tickets_total - 票据总数
     * @return int tickets_verified_total - 已验证票据数
     * @return int tickets_used_total - 已使用票据数
     * @return int tickets_pending - 待验证票据数
     * @return int tickets_expired_total - 已过期票据数
     * @return int calls_24h_total - 24小时调用总数
     * @return int calls_24h_error - 24小时错误调用数
     * @return array calls_24h_by_endpoint - 按接口统计的调用数
     * @return array calls_24h_top_groups - 调用量最高的分组
     * @return array recent_calls - 最近的调用记录
     */
    public function dashboard()
    {
        //实例化模型类
        $settingModel = new SettingModel;

        $result = [
            'status' => 200,
            'msg'    => lang('success_message'),
            'data'   => $settingModel->getDashboardData()
        ];

        return json($result);
    }

    /**
     * 时间 2026-02-09
     * @title API调用日志列表
     * @desc 获取API调用日志列表
     * @url /admin/v1/api_call_log
     * @method GET
     * @author silveridc
     * @version v1
     * @param int page - 页数
     * @param int page_size - 每页条数
     * @param int from - 开始时间戳
     * @param int to - 结束时间戳
     * @param int api_key_id - API密钥ID
     * @param int status_code - 状态码
     * @param string endpoint - 接口路径
     * @param string group_id - 分组ID
     * @param string user_id - 用户ID
     * @return array list - 日志列表
     * @return int list[].id - 日志ID
     * @return int list[].created_at - 创建时间戳
     * @return int list[].api_key_id - API密钥ID
     * @return string list[].endpoint - 接口路径
     * @return string list[].method - 请求方法
     * @return int list[].status_code - 状态码
     * @return string list[].group_id - 分组ID
     * @return string list[].user_id - 用户ID
     * @return string list[].ticket - 票据
     * @return string list[].code - 响应码
     * @return string list[].ip - 请求IP
     * @return string list[].user_agent - 用户代理
     * @return int list[].duration_ms - 耗时(毫秒)
     * @return int total - 总数
     * @return int page - 当前页
     * @return int page_size - 每页条数
     */
    public function apiCallLogList()
    {
        //获取分页参数
        $param = array_merge($this->request->param(), [
            'page'      => $this->request->page ?? 1,
            'page_size' => $this->request->limit ?? 20,
        ]);
        
        //实例化模型类
        $ApiCallLogModel = new ApiCallLogModel();

        $result = [
            'status' => 200,
            'msg'    => lang('success_message'),
            'data'   => $ApiCallLogModel->apiCallLogList($param)
        ];

        return json($result);
    }
}
