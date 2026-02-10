<?php

/**
 * 模拟 env 函数，防止独立运行时报错
 */
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

// 1. 加载配置
$configData = require __DIR__ . '/config/database.php';
$defaultConn = $configData['default'];
$config = $configData['connections'][$defaultConn];

$type = strtolower($config['type'] ?? 'mysql');

try {
    // 2. 建立 PDO 连接
    if (strpos($type, 'sqlite') !== false) {
        $dsn = "sqlite:" . $config['database'];
        $pdo = new PDO($dsn);
    } else {
        $dsn = "mysql:host={$config['hostname']};port={$config['hostport']};dbname={$config['database']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
    }
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "--- 已连接到 [{$type}] 数据库 ---\n";

    // 3. 根据类型处理语法差异
    $isMysql = (strpos($type, 'mysql') !== false);
    
    // 字段定义差异
    $pk = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT";
    $uint = $isMysql ? "INT UNSIGNED" : "INTEGER";
    $engine = $isMysql ? "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" : "";

    // 索引定义差异：MySQL 放在建表语句内，SQLite 独立创建
    $mysqlIndexes = $isMysql ? "
        INDEX `idx_code_group` (`code`, `group_id`),
        INDEX `idx_group_user` (`group_id`, `user_id`),
        INDEX `idx_expire` (`expire_at`),
        INDEX `idx_api_key_expire` (`api_key_id`, `expire_at` )" : "";

    $settingsIndex = $isMysql ? "INDEX `idx_settings_name` (`name` )" : "";

    // 4. 构建 SQL 数组
    $sqls = [];

    // 表 1: GeetestTable
    $sqls[] = "CREATE TABLE IF NOT EXISTS `GeetestTable` (
        `id` $pk,
        `api_key_id` INTEGER DEFAULT NULL,
        `token` VARCHAR(64) NOT NULL UNIQUE,
        `group_id` VARCHAR(64) NOT NULL,
        `user_id` VARCHAR(64) NOT NULL,
        `code` VARCHAR(10) DEFAULT NULL,
        `verified` TINYINT(1) NOT NULL DEFAULT 0,
        `used` TINYINT(1) NOT NULL DEFAULT 0,
        `ip` VARCHAR(45) DEFAULT NULL,
        `user_agent` VARCHAR(500) DEFAULT NULL,
        `extra` TEXT DEFAULT NULL,
        `expire_at` $uint NOT NULL,
        `verified_at` $uint DEFAULT NULL,
        `used_at` $uint DEFAULT NULL,
        `created_at` $uint NOT NULL,
        `updated_at` $uint DEFAULT NULL
        $mysqlIndexes
    ) $engine;";

    // 表 2: settings
    $sqls[] = "CREATE TABLE IF NOT EXISTS `settings` (
        `id` $pk,
        `name` VARCHAR(128) NOT NULL UNIQUE,
        `value` TEXT NOT NULL,
        `created_at` $uint NOT NULL,
        `updated_at` $uint NOT NULL
        $settingsIndex
    ) $engine;";

    // 如果是 SQLite，需要额外执行索引创建语句
    if (!$isMysql) {
        $sqls[] = "CREATE INDEX IF NOT EXISTS `idx_code_group` ON `GeetestTable` (`code`, `group_id`);";
        $sqls[] = "CREATE INDEX IF NOT EXISTS `idx_group_user` ON `GeetestTable` (`group_id`, `user_id`);";
        $sqls[] = "CREATE INDEX IF NOT EXISTS `idx_expire` ON `GeetestTable` (`expire_at`);";
        $sqls[] = "CREATE INDEX IF NOT EXISTS `idx_api_key_expire` ON `GeetestTable` (`api_key_id`, `expire_at`);";
        $sqls[] = "CREATE INDEX IF NOT EXISTS `idx_settings_name` ON `settings` (`name`);";
    }

    // 5. 执行导入
    foreach ($sqls as $sql) {
        $pdo->exec($sql);
    }

    echo "恭喜！数据库结构导入完成。\n";

} catch (PDOException $e) {
    die("❌ 导入失败: " . $e->getMessage() . "\n");
}