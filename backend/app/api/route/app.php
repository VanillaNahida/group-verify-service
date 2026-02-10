<?php
use think\facade\Route;
use think\Response;

Route::pattern([
    'id'     => '\d+',
    'ticket' => '[\w\-]+',
    'any'    => '.*',
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

# 验证页面
Route::get('v/:ticket', 'VerifyController/page');                   // 用户验证页面

# 验证状态查询
Route::group('v1', function () {
    Route::get('verify/:ticket/status', 'VerifyController/status'); // 获取验证状态
})->allowCrossDomain($corsConfig);

# 极验回调
Route::group('v1', function () {
    Route::post('verify/callback', 'VerifyController/callback');    // 极验验证回调
})->allowCrossDomain($corsConfig);

# 验证相关 - 需要API Key认证
Route::group('v1', function () {
    Route::post('verify/create', 'VerifyController/create');        // 生成验证链接
    Route::post('verify/check', 'VerifyController/check');          // 验证验证码
    Route::post('verify/clean', 'VerifyController/clean');          // 清理过期验证码
    Route::post('verify/reset_key', 'VerifyController/resetKey');   // 重置API Key
})->allowCrossDomain($corsConfig)
  ->middleware(\app\api\middleware\ApiAuth::class)
  ->middleware(\app\api\middleware\ApiCallLogger::class);
