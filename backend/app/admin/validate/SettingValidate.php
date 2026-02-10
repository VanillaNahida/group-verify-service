<?php
namespace app\admin\validate;

use think\Validate;

/**
 * @title 系统设置验证器
 * @desc 系统设置参数验证
 * @use app\admin\validate\SettingValidate
 */
class SettingValidate extends Validate
{
    //验证规则
    protected $rule = [
        'values' => 'require|array',
    ];

    //错误提示
    protected $message = [
        'values.require' => 'param_error',
        'values.array'   => 'param_error',
    ];

    //验证场景
    protected $scene = [
        'update' => ['values'],
    ];
}
