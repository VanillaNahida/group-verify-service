<?php
namespace app\controller;

use app\BaseController;
use think\facade\Db;

class AdminApiKeysController extends BaseController
{
    protected static bool $settingsReady = false;
    protected static bool $apiKeysReady = false;
    protected static ?int $cachedDefaultId = null;
    protected static int $cachedDefaultIdAt = 0;

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

    protected function getSettingRaw(string $key): ?string
    {
        $this->ensureSettingsReady();
        try {
            try {
                $value = Db::name('settings')->where('name', $key)->value('value');
            } catch (\Throwable $e) {
                $value = Db::name('settings')->where('key', $key)->value('value');
            }
            return $value !== null ? (string)$value : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function deleteSetting(string $key): void
    {
        $this->ensureSettingsReady();
        try {
            try {
                Db::name('settings')->where('name', $key)->delete();
            } catch (\Throwable $e) {
                Db::name('settings')->where('key', $key)->delete();
            }
        } catch (\Throwable $e) {
        }
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

    protected function maskSecret(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }
        if (mb_strlen($v) <= 8) {
            return '******';
        }
        return mb_substr($v, 0, 4) . '...' . mb_substr($v, -4);
    }

    protected function ensureApiKeysMigrated(): void
    {
        $this->ensureApiKeysReady();
        try {
            $any = Db::name('api_keys')->where('id', '>', 0)->limit(1)->value('id');
            if ($any !== null) {
                return;
            }
        } catch (\Throwable $e) {
        }

        $raw = $this->getSettingRaw('API_KEY');
        if ($raw === null || trim((string)$raw) === '') {
            $envValue = env('API_KEY', null);
            $raw = $envValue !== null ? (string)$envValue : '';
        }

        $keys = $this->parseApiKeys((string)$raw);
        if (!$keys) {
            return;
        }

        $ts = time();
        foreach ($keys as $k) {
            try {
                Db::name('api_keys')->insert([
                    'value' => $k,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]);
            } catch (\Throwable $e) {
            }
        }

        $this->deleteSetting('API_KEY');
    }

    protected function getDefaultApiKeyId(): int
    {
        $this->ensureApiKeysMigrated();
        $now = time();
        if (self::$cachedDefaultId !== null && ($now - self::$cachedDefaultIdAt) <= 3) {
            return (int)self::$cachedDefaultId;
        }
        $id = 0;
        try {
            $v = Db::name('api_keys')->order('id', 'asc')->value('id');
            $id = $v !== null ? (int)$v : 0;
        } catch (\Throwable $e) {
            $id = 0;
        }
        self::$cachedDefaultId = $id;
        self::$cachedDefaultIdAt = $now;
        return $id;
    }

    protected function getJsonBody(): array
    {
        $raw = (string)$this->request->getInput();
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public function list()
    {
        $this->ensureApiKeysMigrated();
        $defaultId = $this->getDefaultApiKeyId();

        $sid = '';
        try {
            $sid = trim((string)$this->request->get('id', ''));
        } catch (\Throwable $e) {
            $sid = '';
        }
        $filterId = 0;
        if ($sid !== '') {
            if (!ctype_digit($sid)) {
                return json(['code' => 400, 'msg' => '参数错误'], 400);
            }
            $filterId = (int)$sid;
        }

        try {
            $query = Db::name('api_keys');
            if ($filterId > 0) {
                $query = $query->where('id', $filterId);
            }
            $rows = $query->order('id', 'desc')->select()->toArray();
        } catch (\Throwable $e) {
            $rows = [];
        }

        $items = [];
        foreach ($rows ?: [] as $r) {
            $id = (int)($r['id'] ?? 0);
            $v = (string)($r['value'] ?? '');
            $items[] = [
                'id' => $id,
                'is_default' => $defaultId > 0 && $id === $defaultId,
                'masked' => $this->maskSecret($v),
                'created_at' => (int)($r['created_at'] ?? 0),
                'updated_at' => (int)($r['updated_at'] ?? 0),
            ];
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => ['items' => $items]]);
    }

    public function create()
    {
        $this->ensureApiKeysMigrated();
        $body = $this->getJsonBody();
        $value = trim((string)($body['value'] ?? ''));

        if ($value === '') {
            try {
                $value = bin2hex(random_bytes(32));
            } catch (\Throwable $e) {
                $value = hash('sha256', uniqid('api_key', true) . microtime(true));
            }
        }

        if (mb_strlen($value) < 16) {
            return json(['code' => 400, 'msg' => '密钥长度至少 16 位'], 400);
        }

        $ts = time();
        try {
            Db::name('api_keys')->insert([
                'value' => $value,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]);
        } catch (\Throwable $e) {
            try {
                $exists = Db::name('api_keys')->where('value', $value)->value('id');
                if ($exists !== null) {
                    return json(['code' => 409, 'msg' => '密钥已存在'], 409);
                }
            } catch (\Throwable $e2) {
            }
            return json(['code' => 500, 'msg' => '创建失败'], 500);
        }

        $id = 0;
        try {
            $id = (int)Db::name('api_keys')->where('value', $value)->value('id');
        } catch (\Throwable $e) {
        }

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'id' => $id,
                'value' => $value,
                'masked' => $this->maskSecret($value),
                'created_at' => $ts,
                'updated_at' => $ts,
            ],
        ]);
    }

    public function delete($id)
    {
        $this->ensureApiKeysMigrated();
        $sid = '';
        try {
            $sid = trim((string)$this->request->route('id', ''));
        } catch (\Throwable $e) {
            $sid = '';
        }
        if ($sid === '') {
            try {
                $path = trim((string)$this->request->pathinfo());
                if ($path !== '' && preg_match('~(?:^|/)admin/api-keys/(\d+)$~', $path, $m)) {
                    $sid = (string)($m[1] ?? '');
                }
            } catch (\Throwable $e) {
                $sid = '';
            }
        }
        if ($sid === '') {
            $sid = trim((string)$id);
        }
        if ($sid === '' || !ctype_digit($sid)) {
            return json(['code' => 400, 'msg' => '参数错误'], 400);
        }

        $defaultId = $this->getDefaultApiKeyId();
        if ($defaultId > 0 && (int)$sid === $defaultId) {
            return json(['code' => 403, 'msg' => '当前使用的 API Key 不可删除'], 403);
        }

        $n = 0;
        try {
            $n = (int)Db::name('api_keys')->where('id', (int)$sid)->delete();
        } catch (\Throwable $e) {
            $n = 0;
        }

        if ($n <= 0) {
            return json(['code' => 404, 'msg' => '不存在'], 404);
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => ['deleted' => $n]]);
    }

    public function reset($id)
    {
        $this->ensureApiKeysMigrated();
        $sid = '';
        try {
            $sid = trim((string)$this->request->route('id', ''));
        } catch (\Throwable $e) {
            $sid = '';
        }
        if ($sid === '') {
            try {
                $path = trim((string)$this->request->pathinfo());
                if ($path !== '' && preg_match('~(?:^|/)admin/api-keys/(\d+)/reset$~', $path, $m)) {
                    $sid = (string)($m[1] ?? '');
                }
            } catch (\Throwable $e) {
                $sid = '';
            }
        }
        if ($sid === '') {
            $sid = trim((string)$id);
        }
        if ($sid === '' || !ctype_digit($sid)) {
            return json(['code' => 400, 'msg' => '参数错误'], 400);
        }

        $targetId = (int)$sid;
        if ($targetId <= 0) {
            return json(['code' => 400, 'msg' => '参数错误'], 400);
        }

        $newValue = '';
        $updated = 0;
        $ts = time();
        for ($i = 0; $i < 3; $i++) {
            try {
                try {
                    $newValue = bin2hex(random_bytes(32));
                } catch (\Throwable $e) {
                    $newValue = hash('sha256', uniqid('api_key', true) . microtime(true) . $i);
                }

                $updated = (int)Db::name('api_keys')->where('id', $targetId)->update([
                    'value' => $newValue,
                    'updated_at' => $ts,
                ]);
                if ($updated > 0) {
                    break;
                }
            } catch (\Throwable $e) {
                $updated = 0;
            }
        }

        if ($updated <= 0 || $newValue === '') {
            return json(['code' => 500, 'msg' => '重置失败'], 500);
        }

        self::$cachedDefaultIdAt = 0;

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'id' => $targetId,
                'value' => $newValue,
                'masked' => $this->maskSecret($newValue),
                'updated_at' => $ts,
            ],
        ]);
    }
}
