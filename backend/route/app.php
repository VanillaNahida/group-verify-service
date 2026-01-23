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
    Route::post('reset-key', 'VerifyController/resetKey')->middleware(app\middleware\ApiAuth::class); // 自助：重置当前 API Key
    
    // 不需要API密钥认证的路由
    Route::post('callback', 'VerifyController/callback'); // 前端提交：极验验证回调
    Route::get('status/:ticket', 'VerifyController/status');
});

// 短链接路由
Route::get('v/:ticket', 'VerifyController/page');

Route::group('admin', function () {
    Route::post('auth/login', 'AdminAuthController/login');

    Route::get('dashboard', 'AdminSettingsController/dashboard')->middleware(app\middleware\AdminJwtAuth::class)->completeMatch(true);
    Route::get('api-call-logs', 'AdminSettingsController/apiCallLogs')->middleware(app\middleware\AdminJwtAuth::class)->completeMatch(true);

    Route::get('settings', 'AdminSettingsController/get')->middleware(app\middleware\AdminJwtAuth::class)->completeMatch(true);
    Route::put('settings', 'AdminSettingsController/update')->middleware(app\middleware\AdminJwtAuth::class)->completeMatch(true);

    Route::get('api-keys', 'AdminApiKeysController/list')->middleware(app\middleware\AdminJwtAuth::class)->completeMatch(true);
    Route::post('api-keys', 'AdminApiKeysController/create')->middleware(app\middleware\AdminJwtAuth::class)->completeMatch(true);
    Route::post('api-keys/:id/reset', 'AdminApiKeysController/reset')->middleware(app\middleware\AdminJwtAuth::class)->completeMatch(true);
    Route::delete('api-keys/:id', 'AdminApiKeysController/delete')->middleware(app\middleware\AdminJwtAuth::class)->completeMatch(true);
})->middleware(app\middleware\AdminCors::class)->completeMatch(true);

Route::get('admin', 'VerifyController/adminPage')->completeMatch(true)->removeSlash(true);
Route::get('admin/login', 'VerifyController/adminPage')->completeMatch(true)->removeSlash(true);

