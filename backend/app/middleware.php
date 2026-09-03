<?php
// 全局中间件定义文件
return [
    // Application pages and API responses must not be cached by a shared CDN.
    \app\middleware\NoCache::class,
    // 全局请求缓存
    // \think\middleware\CheckRequestCache::class,
    // 多语言加载
    // \think\middleware\LoadLangPack::class,
    // Session初始化
    // \think\middleware\SessionInit::class
    \app\middleware\ApiCallLogger::class,
];
