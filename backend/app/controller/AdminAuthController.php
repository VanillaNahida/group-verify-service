<?php
namespace app\controller;

use app\BaseController;
use think\facade\Db;

class AdminAuthController extends BaseController
{
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

    protected function getJsonBody(): array
    {
        $raw = (string)$this->request->getInput();
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    protected function getSetting(string $key): ?string
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

    protected function getApiKey(): string
    {
        $apiKey = $this->getSetting('API_KEY');
        if ($apiKey !== null && $apiKey !== '') {
            return $apiKey;
        }

        $envApiKey = (string)env('API_KEY', '');
        if ($envApiKey !== '') {
            return $envApiKey;
        }

        return '';
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

    protected function readApiKeysFromTable(): array
    {
        $this->ensureApiKeysReady();
        try {
            $rows = Db::name('api_keys')->column('value');
            $keys = [];
            foreach ($rows ?: [] as $v) {
                $k = trim((string)$v);
                if ($k === '') {
                    continue;
                }
                $keys[$k] = true;
            }
            return array_keys($keys);
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

    protected function getApiKeys(): array
    {
        $fromTable = $this->readApiKeysFromTable();
        if ($fromTable) {
            return $fromTable;
        }

        $legacy = $this->parseApiKeys($this->getApiKey());
        if ($legacy) {
            $this->writeApiKeysToTable($legacy);
            $fromTable2 = $this->readApiKeysFromTable();
            return $fromTable2 ? $fromTable2 : $legacy;
        }

        return [];
    }

    protected function getDefaultApiKey(): string
    {
        $this->ensureApiKeysReady();
        try {
            $v = Db::name('api_keys')->order('id', 'asc')->value('value');
            $s = $v !== null ? trim((string)$v) : '';
            if ($s !== '') {
                return $s;
            }
        } catch (\Throwable $e) {
        }

        $legacy = $this->parseApiKeys($this->getApiKey());
        return $legacy ? trim((string)$legacy[0]) : '';
    }

    protected function hasApiKey(string $candidate): bool
    {
        $v = trim($candidate);
        if ($v === '') {
            return false;
        }

        $expected = $this->getApiKeys();
        foreach ($expected as $k) {
            if (hash_equals((string)$k, $v)) {
                return true;
            }
        }
        return false;
    }

    public function login()
    {
        return json(['code' => 410, 'msg' => '已停用：管理接口已统一使用 API Key 鉴权，请直接携带 Authorization: Bearer <API_KEY>'], 410);
    }
}
