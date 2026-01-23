<?php
// 应用公共文件
use think\facade\Cache;
use think\facade\Db;

/**
* @title CURL
* @desc 公共curl
* @author VanillaNahida
* @version v1
* @param string url - url地址 require
* @param array data [] 传递的参数
* @param string timeout 30 超时时间
* @param string request POST 请求类型
* @param array header [] 头部参数
* @param  bool curlFile false 是否curl上传文件
* @return int http_code - http状态码
* @return string error - 错误信息
* @return string content - 内容
*/
function curl($url, $data = [], $timeout = 30, $request = 'POST', $header = [], $curlFile = false)
{
    $curl = curl_init();
    $request = strtoupper($request);

    if($request == 'GET'){
        $s = '';
        if(!empty($data)){
            foreach($data as $k=>$v){
                if($v === ''){
                    $data[$k] = '';
                }
            }
            $s = http_build_query($data);
        }
        if(strpos($url, '?') !== false){
            if($s){
                $s = '&'.$s;
            }
        }else{
            if($s){
                $s = '?'.$s;
            }
        }
        curl_setopt($curl, CURLOPT_URL, $url.$s);
    }else{
        curl_setopt($curl, CURLOPT_URL, $url);
    }
    curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36');
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_REFERER, request() ->host());
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    if($request == 'GET'){
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPGET, 1);
    }
    if($request == 'POST'){
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        if(is_array($data) && !$curlFile){
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        }else{
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
    }
    if($request == 'PUT' || $request == 'DELETE'){
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request);
        if(is_array($data)){
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        }else{
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
    }
    if(!empty($header)){
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    }
    $content = curl_exec($curl);
    $error = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	return ['http_code'=>$http_code, 'error'=>$error , 'content' => $content];
}

function ensure_settings_table(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    try {
        Db::name('settings')->where('id', '>', 0)->limit(1)->value('id');
        $ready = true;
        return;
    } catch (\Throwable) {
    }

    try {
        try {
            Db::execute('CREATE TABLE IF NOT EXISTS `settings` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `name` VARCHAR(128) NOT NULL UNIQUE,
                `value` TEXT NOT NULL,
                `created_at` INTEGER UNSIGNED NOT NULL,
                `updated_at` INTEGER UNSIGNED NOT NULL
            )');
            Db::execute('CREATE INDEX IF NOT EXISTS `idx_settings_name` ON `settings` (`name`)');
        } catch (\Throwable) {
            Db::execute('CREATE TABLE IF NOT EXISTS `settings` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(128) NOT NULL UNIQUE,
                `value` TEXT NOT NULL,
                `created_at` INT UNSIGNED NOT NULL,
                `updated_at` INT UNSIGNED NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    } catch (\Throwable) {
    }

    $ready = true;
}

function ensure_api_keys_table(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    try {
        Db::name('api_keys')->where('id', '>', 0)->limit(1)->value('id');
        $ready = true;
        return;
    } catch (\Throwable) {
    }

    try {
        try {
            Db::execute('CREATE TABLE IF NOT EXISTS `api_keys` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `value` TEXT NOT NULL,
                `created_at` INTEGER UNSIGNED NOT NULL,
                `updated_at` INTEGER UNSIGNED NOT NULL
            )');
            Db::execute('CREATE UNIQUE INDEX IF NOT EXISTS `uniq_api_keys_value` ON `api_keys` (`value`)');
        } catch (\Throwable) {
            Db::execute('CREATE TABLE IF NOT EXISTS `api_keys` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `value` VARCHAR(255) NOT NULL,
                `created_at` INT UNSIGNED NOT NULL,
                `updated_at` INT UNSIGNED NOT NULL,
                UNIQUE KEY `uniq_api_keys_value` (`value`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    } catch (\Throwable) {
    }

    $ready = true;
}

function ensure_api_call_logs_table(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    try {
        Db::name('api_call_logs')->where('id', '>', 0)->limit(1)->value('id');
        $ready = true;
        return;
    } catch (\Throwable) {
    }

    try {
        try {
            Db::execute('CREATE TABLE IF NOT EXISTS `api_call_logs` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `api_key_id` INTEGER DEFAULT NULL,
                `endpoint` TEXT NOT NULL,
                `method` VARCHAR(8) NOT NULL,
                `status_code` INTEGER UNSIGNED NOT NULL,
                `group_id` VARCHAR(64) DEFAULT NULL,
                `user_id` VARCHAR(64) DEFAULT NULL,
                `ticket` VARCHAR(64) DEFAULT NULL,
                `code` VARCHAR(10) DEFAULT NULL,
                `ip` VARCHAR(45) DEFAULT NULL,
                `user_agent` VARCHAR(500) DEFAULT NULL,
                `duration_ms` INTEGER UNSIGNED NOT NULL DEFAULT 0,
                `created_at` INTEGER UNSIGNED NOT NULL
            )');
            Db::execute('CREATE INDEX IF NOT EXISTS `idx_api_call_logs_created_at` ON `api_call_logs` (`created_at`)');
            Db::execute('CREATE INDEX IF NOT EXISTS `idx_api_call_logs_api_key` ON `api_call_logs` (`api_key_id`, `created_at`)');
            Db::execute('CREATE INDEX IF NOT EXISTS `idx_api_call_logs_endpoint` ON `api_call_logs` (`created_at`, `endpoint`)');
            Db::execute('CREATE INDEX IF NOT EXISTS `idx_api_call_logs_group` ON `api_call_logs` (`group_id`, `created_at`)');
        } catch (\Throwable) {
            Db::execute('CREATE TABLE IF NOT EXISTS `api_call_logs` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `api_key_id` INT UNSIGNED NULL,
                `endpoint` VARCHAR(255) NOT NULL,
                `method` VARCHAR(8) NOT NULL,
                `status_code` INT UNSIGNED NOT NULL,
                `group_id` VARCHAR(64) NULL,
                `user_id` VARCHAR(64) NULL,
                `ticket` VARCHAR(64) NULL,
                `code` VARCHAR(10) NULL,
                `ip` VARCHAR(45) NULL,
                `user_agent` VARCHAR(500) NULL,
                `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` INT UNSIGNED NOT NULL,
                KEY `idx_api_call_logs_created_at` (`created_at`),
                KEY `idx_api_call_logs_api_key` (`api_key_id`, `created_at`),
                KEY `idx_api_call_logs_endpoint` (`created_at`, `endpoint`),
                KEY `idx_api_call_logs_group` (`group_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    } catch (\Throwable) {
    }

    $ready = true;
}

function rate_limit_hit(string $key, int $limit, int $windowSeconds): int
{
    $k = trim($key);
    if ($k === '' || $limit <= 0 || $windowSeconds <= 0) {
        return 0;
    }

    $now = time();
    $data = Cache::get($k, null);
    $count = 0;
    $expireAt = 0;
    if (is_array($data)) {
        $count = (int)($data['count'] ?? 0);
        $expireAt = (int)($data['expire_at'] ?? 0);
    }

    if ($expireAt <= $now) {
        $expireAt = $now + $windowSeconds;
        Cache::set($k, ['count' => 1, 'expire_at' => $expireAt], $windowSeconds);
        return 0;
    }

    if ($count >= $limit) {
        return max(1, $expireAt - $now);
    }

    Cache::set($k, ['count' => $count + 1, 'expire_at' => $expireAt], $expireAt - $now);
    return 0;
}
