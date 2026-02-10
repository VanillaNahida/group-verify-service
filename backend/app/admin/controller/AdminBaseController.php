<?php
namespace app\admin\controller;

/**
 * Admin控制器基类
 */
class AdminBaseController extends BaseController
{
    protected function initialize()
    {
        parent::initialize();
    }
    // 可以在这个控制器中添加希望所有后台控制器共享的方法,需要继承AdminBaseController才可以使用
}