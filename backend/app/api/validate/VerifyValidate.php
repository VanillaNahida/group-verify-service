<?php
namespace app\api\validate;

use think\Validate;

/**
 * @title 验证码验证器
 * @desc 验证码相关参数验证
 * @use app\api\validate\VerifyValidate
 */
class VerifyValidate extends Validate
{
    //验证规则
    protected $rule = [
        'group_id'       => 'require|number',
        'user_id'        => 'require|number',
        'code'           => 'require|length:6',
        'ticket'         => 'require|alphaNum',
        'lot_number'     => 'require',
        'captcha_output' => 'require',
        'pass_token'     => 'require',
        'gen_time'       => 'require',
    ];

    //错误提示
    protected $message = [
        'group_id.require'       => 'group_id_require',
        'group_id.number'        => 'group_id_must_be_number',
        'user_id.require'        => 'user_id_require',
        'user_id.number'         => 'user_id_must_be_number',
        'code.require'           => 'code_require',
        'code.length'            => 'code_length_error',
        'ticket.require'         => 'ticket_require',
        'ticket.alphaNum'        => 'ticket_format_error',
        'lot_number.require'     => 'lot_number_require',
        'captcha_output.require' => 'captcha_output_require',
        'pass_token.require'     => 'pass_token_require',
        'gen_time.require'       => 'gen_time_require',
    ];
    
    //验证场景
    protected $scene = [
        'create'   => ['group_id', 'user_id'],
        'callback' => ['ticket', 'lot_number', 'captcha_output', 'pass_token', 'gen_time'],
        'check'    => ['group_id', 'code'],
    ];
}
