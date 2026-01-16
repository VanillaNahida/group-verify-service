<?php
use think\facade\Db;

upgradeDataForGeetestSqlite();

function upgradeDataForGeetestSqlite()
{
	$sql = [
        "CREATE TABLE IF NOT EXISTS `GeetestTable` (
            `id` INTEGER PRIMARY KEY AUTOINCREMENT,
            `token` VARCHAR(64) NOT NULL UNIQUE,
            `group_id` VARCHAR(64) NOT NULL,
            `user_id` VARCHAR(64) NOT NULL,
            `code` VARCHAR(10) DEFAULT NULL,
            `verified` TINYINT(1) NOT NULL DEFAULT 0,
            `used` TINYINT(1) NOT NULL DEFAULT 0,
            `ip` VARCHAR(45) DEFAULT NULL,
            `user_agent` VARCHAR(500) DEFAULT NULL,
            `extra` TEXT DEFAULT NULL,
            `expire_at` INTEGER UNSIGNED NOT NULL,
            `verified_at` INTEGER UNSIGNED DEFAULT NULL,
            `used_at` INTEGER UNSIGNED DEFAULT NULL,
            `created_at` INTEGER UNSIGNED NOT NULL,
            `updated_at` INTEGER UNSIGNED DEFAULT NULL
        );",
        
        // 创建索引的语句是独立的
        "CREATE INDEX IF NOT EXISTS `idx_code_group` ON `GeetestTable` (`code`, `group_id`);",
        "CREATE INDEX IF NOT EXISTS `idx_group_user` ON `GeetestTable` (`group_id`, `user_id`);",
        "CREATE INDEX IF NOT EXISTS `idx_expire` ON `GeetestTable` (`expire_at`);"
    ];

    // 循环执行SQL语句
	foreach($sql as $v){
        try{
            // Db::execute 会将 SQL 发送给项目配置的数据库驱动（这里是 SQLite）
            Db::execute($v);
        }catch(\think\db\exception\PDOException $e){
            // 捕获异常，不做任何处理，继续执行下一条SQL
            // 这可以防止因为表或索引已存在等错误导致脚本中断
        }
    }

    // [可选] 升级完成后，更新系统版本号。请根据您的需要修改版本号并取消下面的注释。
    // Db::execute("update `sys_config` set `value`='1.0.0' where `setting`='sys_ver';");

    echo "Geetest table (SQLite) creation script executed successfully.\n";
}
