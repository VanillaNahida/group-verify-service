<?php
namespace app\middleware;

use think\facade\Db;

class ApiAuth
{
    protected static ?string $cachedApiKeyRaw = null;
    protected static ?array $cachedApiKeyRows = null;
    protected static int $cachedApiKeyRowsAt = 0;
    protected static bool $settingsReady = false;
    protected static bool $apiKeysReady = false;

    protected function ensureSettingsReady(): void
    {
        if (self::$settingsReady) {
            return;
        }
        ensure_settings_table();
        self::$settingsReady = true;
    }

    protected function ensureApiKeysReady(): void
    {
        if (self::$apiKeysReady) {
            return;
        }
        ensure_api_keys_table();
        self::$apiKeysReady = true;
    }

    protected function parseApiKeys(string $raw): array
    {
        $v = trim($raw);
        if ($v === '') {
            return [];
        }

        if (str_starts_with($v, '[') && str_ends_with($v, ']')) {
            $decoded = json_decode($v, true);
            if (is_array($decoded)) {
                $keys = [];
                foreach ($decoded as $it) {
                    if (!is_string($it)) {
                        continue;
                    }
                    $k = trim($it);
                    if ($k === '') {
                        continue;
                    }
                    $keys[$k] = true;
                }
                return array_keys($keys);
            }
        }

        $parts = preg_split('/[,\s;，；]+/u', $v) ?: [];
        $keys = [];
        foreach ($parts as $p) {
            $k = trim((string)$p);
            if ($k === '') {
                continue;
            }
            $keys[$k] = true;
        }
        return array_keys($keys);
    }

    protected function readApiKeyRowsFromTable(): array
    {
        $this->ensureApiKeysReady();
        try {
            $rows = Db::name('api_keys')->field('id,value')->select()->toArray();
            $items = [];
            foreach ($rows ?: [] as $r) {
                $id = (int)($r['id'] ?? 0);
                $v = trim((string)($r['value'] ?? ''));
                if ($id <= 0 || $v === '') {
                    continue;
                }
                $items[] = ['id' => $id, 'value' => $v];
            }
            return $items;
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function writeApiKeysToTable(array $keys): void
    {
        $this->ensureApiKeysReady();
        $ts = time();
        foreach ($keys as $v) {
            $k = trim((string)$v);
            if ($k === '') {
                continue;
            }
            try {
                Db::name('api_keys')->insert([
                    'value' => $k,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]);
            } catch (\Throwable $e) {
            }
        }
    }

    protected function getApiKeyRaw(): ?string
    {
        if (self::$cachedApiKeyRaw !== null) {
            return self::$cachedApiKeyRaw;
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
        }

        self::$cachedApiKeyRaw = $apiKey !== null ? (string)$apiKey : null;
        return self::$cachedApiKeyRaw;
    }

    protected function getDefaultApiKeyId(): int
    {
        $this->ensureApiKeysReady();
        try {
            $v = Db::name('api_keys')->order('id', 'asc')->value('id');
            return $v !== null ? (int)$v : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function getApiKeyRows(): array
    {
        $now = time();
        if (self::$cachedApiKeyRows !== null && ($now - self::$cachedApiKeyRowsAt) <= 3) {
            return self::$cachedApiKeyRows;
        }

        $fromTable = $this->readApiKeyRowsFromTable();
        if ($fromTable) {
            self::$cachedApiKeyRows = $fromTable;
            self::$cachedApiKeyRowsAt = $now;
            return self::$cachedApiKeyRows;
        }

        $raw = $this->getApiKeyRaw();
        $legacyKeys = $raw !== null ? $this->parseApiKeys($raw) : [];
        if ($legacyKeys) {
            $this->writeApiKeysToTable($legacyKeys);
            $fromTable2 = $this->readApiKeyRowsFromTable();
            self::$cachedApiKeyRows = $fromTable2 ?: [];
            self::$cachedApiKeyRowsAt = $now;
            return self::$cachedApiKeyRows;
        }

        self::$cachedApiKeyRows = [];
        self::$cachedApiKeyRowsAt = $now;
        return self::$cachedApiKeyRows;
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
        $rows = $this->getApiKeyRows();
        if (!$rows) {
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
        $providedKey = trim((string)$matches[1]);
        
        // 验证密钥
        $ok = false;
        $apiKeyId = 0;
        foreach ($rows as $r) {
            $k = (string)($r['value'] ?? '');
            if ($k !== '' && hash_equals($k, $providedKey)) {
                $ok = true;
                $apiKeyId = (int)($r['id'] ?? 0);
                break;
            }
        }
        if (!$ok) {
            return json([
                'code' => 401,
                'msg' => 'Unauthorized: Invalid API key'
            ], 401);
        }

        $defaultId = $this->getDefaultApiKeyId();
        $request->withMiddleware([
            'api_key_id' => $apiKeyId,
            'api_key_is_default' => $defaultId > 0 && $apiKeyId === $defaultId,
        ]);
        
        // 密钥验证通过，继续执行请求
        return $next($request);
    }
}
