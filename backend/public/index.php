<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

use think\App;

// [ 应用入口文件 ]

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoloadPath)) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>缺少 vendor</title><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial;max-width:860px;margin:40px auto;padding:0 16px;color:#222;}code{background:#f6f8fa;padding:2px 6px;border-radius:6px;}pre{background:#f6f8fa;padding:12px;border-radius:8px;overflow:auto;}</style></head><body><h2>缺少 vendor/</h2><p>你选择了“服务器不安装依赖”，请上传包含 <code>vendor/</code> 的发布包。</p><p>在本地或 CI 构建发布包时执行（在项目根目录）：</p><pre>composer install --no-dev -o</pre><p>然后把整个项目目录（含 <code>vendor/</code>）上传到服务器，站点运行目录指向 <code>public/</code>。</p></body></html>';
    exit;
}

require $autoloadPath;

// 执行HTTP应用并响应
$http = (new App())->http;

$response = $http->run();

$response->send();

$http->end($response);
