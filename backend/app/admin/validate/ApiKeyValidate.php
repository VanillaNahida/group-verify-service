<?php
namespace app\admin\validate;

use think\Validate;

/**
 * @title API密钥验证器
 * @desc API密钥参数验证
 * @use app\admin\validate\ApiKeyValidate
 */
class ApiKeyValidate extends Validate
{
    //验证规则
    protected $rule = [
        'id'    => 'require|integer|gt:0',
        'value' => 'min:16|max:128',
    ];

    //错误提示
    protected $message = [
        'id.require'  => 'api_key_id_require',
        'id.integer'  => 'api_key_id_error',
        'id.gt'       => 'api_key_id_error',
        'value.min'   => 'api_key_length_error',
        'value.max'   => 'api_key_length_error',
    ];

    //验证场景
    protected $scene = [
        'index'  => ['id'],
        'create' => ['value'],
        'delete' => ['id'],
        'reset'  => ['id'],
    ];
}
