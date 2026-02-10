<?php
namespace app\admin\controller;

use think\facade\View;
use think\facade\Config;
/**
 * @title 验证管理
 * @desc 验证码管理相关
 * @use app\admin\controller\VerifyController
 */
#[\AllowDynamicProperties]
class VerifyController extends AdminBaseController
{
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * 时间 2026-02-09
     * @title 管理后台验证页面
     * @desc 返回管理后台验证页面
     * @url /admin/verify/page
     * @method GET
     * @author silveridc
     * @version v1
     * @return html
     */
    public function verifyPage()
    {
        // 传递版本号
        View::assign('version', '1.0.0');

        // 传递极验配置到前端页面
        View::assign('captcha_id', Config::get('geetest.Captcha'));
        View::assign('captcha_key', Config::get('geetest.CaptchaKey'));
        View::assign('api_server', Config::get('geetest.ApiServer') ?: 'https://gcaptcha4.geetest.com');
        
        // 渲染并返回模板
        // 模板文件位置：app/admin/view/verify/verify_page.tpl
        return View::fetch('verify_page');
    }
}
