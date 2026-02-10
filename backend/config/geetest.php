<?php
//接口配置
return [
    //captcha配置
    'Captcha' => '9cfd862579c55ccf5f92f673a75cd38b',
    'CaptchaKey' => 'fd982638c8dac52157b2f8c6a26230de',
    //api服务端配置
    'ApiServer' => 'https://gcaptcha4.geetest.com',
    
    // 验证码有效期/s
    'notBefore' => 300,
    //表名
    'TableName'    => 'Validate',
    'cache_prefix' => 'geetest:token:',
    //缓存目录
    'storage_path' => 'runtime/Geetest/',
    //api密钥
    'api_keys' => ['key1','key2',],
    'salt' => 'qp8FhjzGHRUaPsBXSdu24CmD90EJ3l',
];
