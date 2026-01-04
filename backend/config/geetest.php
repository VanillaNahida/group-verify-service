<?php
// 极验V4配置
return [
    'captcha_id' => env('GEETEST_CAPTCHA_ID', ''),
    'captcha_key' => env('GEETEST_CAPTCHA_KEY', ''),
    'api_server' => env('GEETEST_API_SERVER', 'https://gcaptcha4.geetest.com'),
    
    // 验证码有效期/s
    'code_expire' => env('GEETEST_CODE_EXPIRE', 300),
    
    'storage_path' => env('GEETEST_STORAGE_PATH', 'runtime/Geetest/'),
    
    // Ticket生成盐值
    'salt' => env('SALT', ''),
];
