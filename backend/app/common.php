<?php
// 应用公共文件
use think\facade\Config;
/**
 * @title CURL
 * @desc 公共curl
 * @author silveridc
 * @version v1
 * @param string url - url地址 require
 * @param array data[] - 请求参数
 * @param string timeout - 超时时间
 * @param string request - 请求方式 GET,POST,PUT,DELETE
 * @param array header[] - 请求头
 * @param boolean curlFile - 是否使用curl上传文件
 * @return string error - 错误信息
 * @return string content - 返回内容
 */
function curl($url, $data = [], $timeout = 30, $request = 'GET', $header = [], $curlFile = false) {
    $curl = curl_init();
    $request = strtoupper($request);

    if($request == 'GET') {
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
    /**
     * curl设置
     */
    curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
    //ua
    $curl_ua = Config::get('app.curl.user_agent','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36');
    curl_setopt($curl, CURLOPT_USERAGENT, $curl_ua);
    //跟随重定向
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_REFERER, request() ->host());
    //ssl验证
    $sslVerify = Config::get('app.curl.ssl_verify',false);
    $sslVerifyHost = Config::get('app.curl.ssl_verify_host',false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $sslVerify);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $sslVerifyHost);
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
	curl_close($curl);
    $result = ['http_code'=>$http_code, 'error'=>$error , 'content' => $content];
	return $result;
}

/**
 * @title 获取客户端IP地址
 * @desc 获取客户端IP地址
 * @author silveridc
 * @version v1
 * @param int type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param bool adv  是否进行高级模式获取
 * @return string
 */
function get_client_ip($type = 0, $adv = true)
{
    static $ipWhiteList = null;
    // 获取 X-Forwarded-For 头部
    $ip = getenv('HTTP_X_FORWARDED_FOR');

    if ($ip) {
        if ($ipWhiteList === null) {
            $list = explode("\n", Config::get('app.ip_white_list'));
            $ipWhiteList = array_filter(array_map('trim', $list));
        }
        // 将 X-Forwarded-For 中的 IP 地址分割成数组并去掉多余的空格
        $ipList = array_map('trim', explode(',', $ip));
        // 当只有客户端ip时
        if (count($ipList)==1){
            // 代理ip是否在白名单
            if (ip_in_whitelist(request()->ip($type, $adv), $ipWhiteList)){
                return $ipList[0]; // 返回真实ip
            }
        }else{
            // 遍历 X-Forwarded-For 中的所有 IP 地址（从客户端到代理）
            foreach ($ipList as $index => $proxyIp) {
                // 从第二个 IP 地址开始，检查代理服务器 IP 是否在白名单中
                if ($index > 0 && ip_in_whitelist($proxyIp, $ipWhiteList)) {
                    // 如果代理 IP 在白名单中，则返回第一个 IP（客户端的真实 IP）
                    return $ipList[0];  // 真实客户端 IP 通常是第一个
                }
            }
        }

    }

    // 如果没有找到信任的代理或没有 X-Forwarded-For 头部，则使用默认方法获取客户端 IP
    return request()->ip($type, $adv);
}

/**
 * @title 检查IP是否在白名单中
 * @desc 支持单个IP、CIDR网段、IP范围三种格式
 * @param string $ip 要检查的IP地址
 * @param array $whitelist 白名单数组
 * @return bool
 */
function ip_in_whitelist($ip, $whitelist)
{
    // 验证IP格式
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }
    
    $ipLong = ip2long($ip);
    if ($ipLong === false) {
        return false;
    }
    
    foreach ($whitelist as $item) {
        if (empty($item)) {
            continue;
        }
        
        // CIDR格式: 192.168.3.0/24
        if (strpos($item, '/') !== false) {
            if (ip_in_cidr($ip, $item)) {
                return true;
            }
        }
        // IP范围格式: 192.168.3.1-192.168.3.5
        elseif (strpos($item, '-') !== false) {
            if (ip_in_range($ip, $item)) {
                return true;
            }
        }
        // 单个IP
        else {
            if ($ip === $item) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * @title 检查IP是否在CIDR网段中
 * @param string $ip 要检查的IP地址
 * @param string $cidr CIDR格式的网段 如: 192.168.3.0/24
 * @return bool
 */
function ip_in_cidr($ip, $cidr)
{
    list($subnet, $mask) = explode('/', $cidr);
    
    $ipLong = ip2long($ip);
    $subnetLong = ip2long($subnet);
    $maskLong = -1 << (32 - (int)$mask);
    
    if ($ipLong === false || $subnetLong === false) {
        return false;
    }
    
    return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
}

/**
 * @title 检查IP是否在IP范围中
 * @param string $ip 要检查的IP地址
 * @param string $range IP范围 如: 192.168.3.1-192.168.3.5
 * @return bool
 */
function ip_in_range($ip, $range)
{
    list($startIp, $endIp) = array_map('trim', explode('-', $range));
    
    $ipLong = ip2long($ip);
    $startLong = ip2long($startIp);
    $endLong = ip2long($endIp);
    
    if ($ipLong === false || $startLong === false || $endLong === false) {
        return false;
    }
    
    return $ipLong >= $startLong && $ipLong <= $endLong;
}
/**
 * @title 脱敏显示密钥
 * @desc 脱敏显示密钥
 * @author silveridc
 * @version v1
 * @param string $v 密钥值
 * @return string 脱敏后的密钥
 */
function maskSecret($v)
{
    $v = trim((string)$v);
    if (mb_strlen($v) <= 8) return '******';
    return mb_substr($v, 0, 4) . '...' . mb_substr($v, -4);
}