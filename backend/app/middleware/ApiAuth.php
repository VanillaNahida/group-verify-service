<?php
namespace app\middleware;

use think\facade\Db;

class ApiAuth
{
    protected static ?string $cachedApiKey = null;
    protected static bool $settingsReady = false;

    protected function ensureSettingsReady(): void
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

    protected function getApiKey(): ?string
    {
        if (self::$cachedApiKey !== null) {
            return self::$cachedApiKey;
        }

        $this->ensureSettingsReady();

        $apiKey = null;
        try {
            try {
                $apiKey = Db::name('settings')->where('name', 'API_KEY')->value('value');
            } catch (\Throwable $e) {
                $apiKey = Db::name('settings')->where('key', 'API_KEY')->value('value');
            }
        } catch (\Throwable $e) {
        }

        if ($apiKey === null) {
            $apiKey = env('API_KEY', null);
            if ($apiKey !== null) {
                $ts = time();
                try {
                    try {
                        Db::name('settings')->insert([
                            'name' => 'API_KEY',
                            'value' => (string)$apiKey,
                            'created_at' => $ts,
                            'updated_at' => $ts,
                        ]);
                    } catch (\Throwable $e) {
                        Db::name('settings')->insert([
                            'key' => 'API_KEY',
                            'value' => (string)$apiKey,
                            'created_at' => $ts,
                            'updated_at' => $ts,
                        ]);
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        self::$cachedApiKey = $apiKey !== null ? (string)$apiKey : null;
        return self::$cachedApiKey;
    }

    /**
     * 验证API密钥
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        $apiKey = $this->getApiKey();
        if ($apiKey === null || $apiKey === '') {
            return json([
                'code' => 500,
                'msg' => 'Service not initialized: API key missing'
            ], 500);
        }
        
        // 获取请求头中的Authorization
        $authorization = $request->header('Authorization');
        
        // 检查Authorization头格式是否正确
        if (empty($authorization) || !preg_match('/^Bearer\s+(.*)$/', $authorization, $matches)) {
            return json([
                'code' => 401,
                'msg' => 'Unauthorized: Invalid Authorization header format'
            ], 401);
        }
        
        // 提取密钥
        $providedKey = $matches[1];
        
        // 验证密钥
        if ($providedKey !== $apiKey) {
            return json([
                'code' => 401,
                'msg' => 'Unauthorized: Invalid API key'
            ], 401);
        }
        
        // 密钥验证通过，继续执行请求
        return $next($request);
    }
}
