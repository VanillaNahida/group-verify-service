<?php
// 应用公共文件
use think\facade\Cache;
use think\facade\Db;

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
