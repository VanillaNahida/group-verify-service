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
use think\facade\App;
Route::miss(function() {
    return '<link rel="icon" href="favicon.ico">
<style type="text/css">
	*{ padding: 0; margin: 0; } div{ padding: 4px 48px;} a{color:#2E5CD5;cursor: pointer;text-decoration: none} a:hover{text-decoration:underline; } body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px;} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.6em; font-size: 42px }
</style>
<div style="padding: 24px 48px;">
	<h1>:) </h1>
	<p> ThinkPHP V' . App::version() . '<br/><span style="font-size:30px;">18载初心不改 - 你值得信赖的PHP框架</span></p><span style="font-size:25px;"></span>
</div>
<think id="ee9b1aa918103c4fc"></think>
<div class="copyright"><span><a href="https://beian.miit.gov.cn/">陕ICP备2025072193号-3</a></span></div>';
});