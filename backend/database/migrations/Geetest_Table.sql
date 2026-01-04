-- 创建验证表
CREATE TABLE IF NOT EXISTS `GeetestTable` (
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
);

-- 创建索引
CREATE INDEX IF NOT EXISTS `idx_code_group` ON `GeetestTable` (`code`, `group_id`);
CREATE INDEX IF NOT EXISTS `idx_group_user` ON `GeetestTable` (`group_id`, `user_id`);
CREATE INDEX IF NOT EXISTS `idx_expire` ON `GeetestTable` (`expire_at`);
