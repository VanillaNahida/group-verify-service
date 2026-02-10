<?php
use think\facade\Route;
use think\Response;

Route::pattern([
    'id'    => '\d+',
    'page'  => '\d+',
    'limit' => '\d+',
    'any'   => '.*',
]);

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

$corsConfig = [
    'Access-Control-Allow-Origin'      => $origin ?: '*',
    'Access-Control-Allow-Methods'     => 'GET,POST,PUT,DELETE,OPTIONS',
    'Access-Control-Allow-Headers'     => 'Authorization,Content-Type',
    'Access-Control-Allow-Credentials' => 'true',
    'Access-Control-Max-Age'           => 86400,
];

# CORS 预检请求
Route::options('v1/:any', function () {
    return Response::create('', 'html', 204);
})->allowCrossDomain($corsConfig);

# 认证相关
Route::group('v1', function () {
    Route::post('auth/verify', 'AuthController/verify');            // 验证API Key
})->allowCrossDomain($corsConfig);

# 认证信息 
Route::group('v1', function () {
    Route::get('auth/info', 'AuthController/info');                 // 获取认证信息
})->allowCrossDomain($corsConfig)
  ->middleware(\app\admin\middleware\AdminCheck::class);

# API密钥管理 
Route::group('v1', function () {
    Route::get('api_key', 'ApiKeyController/apiKeyList');           // API密钥列表
    Route::get('api_key/:id', 'ApiKeyController/index');            // API密钥详情
    Route::post('api_key', 'ApiKeyController/create');              // 创建API密钥
    Route::delete('api_key/:id', 'ApiKeyController/delete');        // 删除API密钥
    Route::put('api_key/:id/reset', 'ApiKeyController/reset');      // 重置API密钥
})->allowCrossDomain($corsConfig)
  ->middleware(\app\admin\middleware\AdminCheck::class);

# 系统设置 
Route::group('v1', function () {
    Route::get('setting', 'SettingController/settingList');         // 获取系统设置
    Route::put('setting', 'SettingController/update');              // 更新系统设置
    Route::get('dashboard', 'SettingController/dashboard');         // 仪表盘数据
    Route::get('api_call_log', 'SettingController/apiCallLogList'); // API调用日志列表
})->allowCrossDomain($corsConfig)
  ->middleware(\app\admin\middleware\AdminCheck::class);

# 验证管理 
Route::group('v1', function () {
    Route::get('verify/page', 'VerifyController/verifyPage');       // 验证管理页面
})->allowCrossDomain($corsConfig)
  ->middleware(\app\admin\middleware\AdminCheck::class);
