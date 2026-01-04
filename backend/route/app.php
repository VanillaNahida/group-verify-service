<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});

Route::get('hello/:name', 'index/hello');
Route::any('setup', 'index/setup');

//验证路由
Route::group('verify', function () {
    // 需要API密钥认证的路由
    Route::post('create', 'VerifyController/create')->middleware(app\middleware\ApiAuth::class);      // Bot调用：生成验证链接
    Route::post('check', 'VerifyController/check')->middleware(app\middleware\ApiAuth::class);       // Bot调用：验证验证码
    Route::get('clean', 'VerifyController/clean')->middleware(app\middleware\ApiAuth::class);        // 定时任务：清理过期
    
    // 不需要API密钥认证的路由
    Route::post('callback', 'VerifyController/callback'); // 前端提交：极验验证回调
    Route::get('status/:ticket', 'VerifyController/status');
});

// 短链接路由
Route::get('v/:ticket', 'VerifyController/page');

