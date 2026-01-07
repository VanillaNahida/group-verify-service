<?php
namespace app\model;

use think\facade\Request;
use think\facade\Db;

class GeetestModel
{
    protected static string $captchaId;
    protected static string $captchaKey;
    protected static string $apiServer;
    protected static int $codeExpire;
    protected static string $salt;
    protected static bool $initialized = false;
    protected static bool $settingsReady = false;

    protected static function ensureSettingsReady(): void
    {
        if (self::$settingsReady) {
            return;
        }

        try {
            Db::name('settings')->where('id', '>', 0)->limit(1)->value('id');
            self::$settingsReady = true;
            return;
        } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
                Db::execute('CREATE TABLE IF NOT EXISTS `settings` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(128) NOT NULL UNIQUE,
                    `value` TEXT NOT NULL,
                    `created_at` INT UNSIGNED NOT NULL,
                    `updated_at` INT UNSIGNED NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            }
        } catch (\Throwable $e) {
        }

        self::$settingsReady = true;
    }

    protected static function getSetting(string $key, $default = null)
    {
        self::ensureSettingsReady();
        try {
            try {
                $value = Db::name('settings')->where('name', $key)->value('value');
                if ($value !== null) {
                    return $value;
                }
            } catch (\Throwable $e) {
                $value = Db::name('settings')->where('key', $key)->value('value');
                if ($value !== null) {
                    return $value;
                }
            }
        } catch (\Throwable $e) {
        }

        $envValue = env($key, null);
        if ($envValue === null) {
            return $default;
        }

        $ts = time();
        try {
            try {
                Db::name('settings')->insert([
                    'name' => $key,
                    'value' => (string)$envValue,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]);
            } catch (\Throwable $e) {
                Db::name('settings')->insert([
                    'key' => $key,
                    'value' => (string)$envValue,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]);
            }
        } catch (\Throwable $e) {
        }

        return $envValue;
    }

    protected static function initConfig()
    {
        if (self::$initialized) {
            return;
        }

        self::$captchaId = (string)self::getSetting('GEETEST_CAPTCHA_ID', '');
        self::$captchaKey = (string)self::getSetting('GEETEST_CAPTCHA_KEY', '');
        self::$apiServer = (string)self::getSetting('GEETEST_API_SERVER', 'https://gcaptcha4.geetest.com');
        self::$codeExpire = (int)self::getSetting('GEETEST_CODE_EXPIRE', 300);
        self::$salt = (string)self::getSetting('SALT', '');

        self::$initialized = true;
    }

    public static function generateToken(string $gid, string $uid)
    {
        self::initConfig();
        $timestamp = time();
        return hash('sha256', $gid . $uid . $timestamp . self::$salt);
    }



    public static function generateCode()
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }

    public static function saveVerifyData(string $token, array $data)
    {
        self::initConfig();

        $validate = new GeetestTable();
        $validate->token = $token;
        $validate->group_id = $data['group_id'];
        $validate->user_id = $data['user_id'];
        $validate->code = $data['code'] ?? null;
        $validate->verified = $data['verified'] ?? 0;
        $validate->used = 0;
        $validate->ip = Request::ip();
        $validate->user_agent = Request::header('user-agent');
        $validate->extra = $data['extra'] ?? null;
        $validate->expire_at = time() + self::$codeExpire;
        $validate->created_at = time();
        $validate->updated_at = time();

        return $validate->save();
    }

    public static function getVerifyData(string $token)
    {
        self::initConfig();

        $validate = GeetestTable::where('token', $token)->find();

        if (!$validate) {
            return null;
        }

        if ($validate->expire_at < time()) {
            self::deleteVerifyData($token);
            return null;
        }

        return $validate->toArray();
    }

    public static function findByCode(string $code, string $gid)
    {
        self::initConfig();

        $validate = GeetestTable::where('code', $code)
            ->where('group_id', $gid)
            ->where('verified', 1)
            ->where('used', 0)
            ->where('expire_at', '>', time())
            ->find();

        if ($validate) {
            return $validate->toArray();
        }

        return null;
    }

    public static function findCodeByAllStatus(string $code, string $gid)
    {
        self::initConfig();

        $validate = GeetestTable::where('code', $code)
            ->where('group_id', $gid)
            ->where('verified', 1)
            ->find();

        if ($validate) {
            return $validate->toArray();
        }

        return null;
    }

    public static function updateVerifyData(string $token, array $newVerifyData)
    {
        self::initConfig();

        $validate = GeetestTable::where('token', $token)->find();

        if (!$validate) {
            return false;
        }

        $validate->code = $newVerifyData['code'] ?? $validate->code;
        $validate->verified = $newVerifyData['verified'] ?? $validate->verified;
        $validate->verified_at = $newVerifyData['verified_at'] ?? $validate->verified_at;
        $validate->updated_at = time();

        return $validate->save();
    }

    public static function deleteVerifyData(string $token)
    {
        self::initConfig();

        return GeetestTable::where('token', $token)->delete() > 0;
    }

    public static function deleteByCode(string $code, string $gid)
    {
        self::initConfig();

        return GeetestTable::where('code', $code)
            ->where('group_id', $gid)
            ->delete() > 0;
    }

    public static function verifyGeetest(array $params)
    {
        self::initConfig();

        $lotNumber = $params['lot_number'] ?? '';
        $captchaOutput = $params['captcha_output'] ?? '';
        $passToken = $params['pass_token'] ?? '';
        $genTime = $params['gen_time'] ?? '';

        if (empty($lotNumber) || empty($captchaOutput) || empty($passToken) || empty($genTime)) {
            return false;
        }

        $signToken = hash_hmac('sha256', $lotNumber, self::$captchaKey);

        $postData = [
            'lot_number' => $lotNumber,
            'captcha_output' => $captchaOutput,
            'pass_token' => $passToken,
            'gen_time' => $genTime,
            'sign_token' => $signToken,
            'captcha_id' => self::$captchaId,
        ];

        $url = self::$apiServer . '/validate?captcha_id=' . self::$captchaId;

        $result = curl($url, $postData, 10, 'POST');

        if ($result['http_code'] !== 200 || !empty($result['error'])) {
            return false;
        }

        $response = json_decode($result['content'], true);

        return isset($response['result']) && $response['result'] === 'success';
    }

    public static function getCaptchaId()
    {
        self::initConfig();
        return self::$captchaId;
    }

    public static function getCodeExpire()
    {
        self::initConfig();
        return self::$codeExpire;
    }

    public static function cleanExpiredCodes()
    {
        self::initConfig();

        return GeetestTable::where('expire_at', '<', time())->delete();
    }

    public static function markCodeAsUsed(string $code, string $gid)
    {
        self::initConfig();

        $validate = GeetestTable::where('code', $code)
            ->where('group_id', $gid)
            ->find();

        if ($validate) {
            $validate->used = 1;
            $validate->used_at = time();
            $validate->updated_at = time();
            $result = $validate->save();
            
            // 删除验证数据，使验证链接失效
            if ($result) {
                self::deleteVerifyData($validate->token);
            }
            
            return $result;
        }

        return false;
    }
}
