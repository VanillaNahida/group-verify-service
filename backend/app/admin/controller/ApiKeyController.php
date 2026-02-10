<?php
namespace app\admin\controller;

use app\admin\model\ApiKeyModel;

use app\admin\validate\ApiKeyValidate;

/**
 * @title API密钥管理
 * @desc API密钥管理
 * @use app\admin\controller\ApiKeyController
 */
#[\AllowDynamicProperties]
class ApiKeyController extends AdminBaseController
{
    public function initialize()
    {
        parent::initialize();
        $this->validate = new ApiKeyValidate();
    }

    /**
     * 时间 2026-02-09
     * @title API密钥列表
     * @desc 获取API密钥列表
     * @url /admin/v1/api_key
     * @method GET
     * @author silveridc
     * @version v1
     * @param int id - 密钥ID(可选,用于筛选单个)
     * @param int page - 页数
     * @param int limit - 每页条数
     * @return array list - API密钥列表
     * @return int list[].id - 密钥ID
     * @return bool list[].is_default - 是否为默认密钥
     * @return string list[].masked - 脱敏后的密钥
     * @return int list[].created_at - 创建时间戳
     * @return int list[].updated_at - 更新时间戳
     * @return int count - 密钥总数
     */
    public function apiKeyList()
    {
        $param = array_merge($this->request->param(), [
            'page'  => $this->request->page,
            'limit' => $this->request->limit,
            'sort'  => $this->request->sort
        ]);

        $ApiKeyModel = new ApiKeyModel();

        $result = [
            'status' => 200,
            'msg'    => lang('success_message'),
            'data'   => $ApiKeyModel->apiKeyList($param)
        ];

        return json($result);
    }

    /**
     * 时间 2026-02-09
     * @title 获取单个API密钥
     * @desc 获取单个API密钥详情
     * @url /admin/v1/api_key/:id
     * @method GET
     * @author silveridc
     * @version v1
     * @param int id - 密钥ID required
     * @return object api_key - API密钥信息
     * @return int api_key.id - 密钥ID
     * @return bool api_key.is_default - 是否为默认密钥
     * @return string api_key.masked - 脱敏后的密钥
     * @return int api_key.created_at - 创建时间戳
     * @return int api_key.updated_at - 更新时间戳
     */
    public function index()
    {
        $param = $this->request->param();

        if (!$this->validate->scene('index')->check($param)) {
            return json(['status' => 400, 'msg' => lang($this->validate->getError())]);
        }

        $ApiKeyModel = new ApiKeyModel();

        $result = [
            'status' => 200,
            'msg'    => lang('success_message'),
            'data'   => [
                'api_key' => $ApiKeyModel->indexApiKey(intval($param['id']))
            ]
        ];

        return json($result);
    }

    /**
     * 时间 2026-02-09
     * @title 创建API密钥
     * @desc 创建新的API密钥
     * @url /admin/v1/api_key
     * @method post
     * @author silveridc
     * @version v1
     * @param string value - 密钥值(可选,不传则自动生成)
     * @return int id - 新创建的密钥ID
     * @return string value - 完整密钥值(仅创建时返回)
     * @return string masked - 脱敏后的密钥
     * @return int created_at - 创建时间戳
     * @return int updated_at - 更新时间戳
     */
    public function create()
    {
        $param = $this->request->param();

        if (!$this->validate->scene('create')->check($param)) {
            return json(['status' => 400, 'msg' => lang($this->validate->getError())]);
        }

        $ApiKeyModel = new ApiKeyModel();

        $result = $ApiKeyModel->createApiKey($param);

        return json($result);
    }

    /**
     * 时间 2026-02-09
     * @title 删除API密钥
     * @desc 删除指定的API密钥
     * @url /admin/v1/api_key/:id
     * @method DELETE
     * @author silveridc
     * @version v1
     * @param int id - 密钥ID required
     * @return int deleted - 删除的记录数
     */
    public function delete()
    {
        $param = $this->request->param();

        if (!$this->validate->scene('delete')->check($param)) {
            return json(['status' => 400, 'msg' => lang($this->validate->getError())]);
        }

        $ApiKeyModel = new ApiKeyModel();

        $result = $ApiKeyModel->deleteApiKey($param);

        return json($result);
    }

    /**
     * 时间 2026-02-09
     * @title 重置API密钥
     * @desc 重置指定API密钥的值
     * @url /admin/v1/api_key/:id/reset
     * @method PUT
     * @author silveridc
     * @version v1
     * @param int id - 密钥ID required
     * @return int id - 密钥ID
     * @return string value - 新的完整密钥值
     * @return string masked - 脱敏后的密钥
     * @return int updated_at - 更新时间戳
     */
    public function reset()
    {
        $param = $this->request->param();

        if (!$this->validate->scene('reset')->check($param)) {
            return json(['status' => 400, 'msg' => lang($this->validate->getError())]);
        }

        $ApiKeyModel = new ApiKeyModel();

        $result = $ApiKeyModel->resetApiKey($param);

        return json($result);
    }
}
