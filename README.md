# 入群极验验证后端服务 (group-verify-service)

<div align="center">

![UGC Avatar](https://socialify.git.ci/VanillaNahida/group-verify-service/image?description=1&font=KoHo&forks=1&issues=1&language=1&name=1&owner=1&pattern=Circuit%20Board&pulls=1&stargazers=1&theme=Auto)

[![GitHub license](https://img.shields.io/github/license/VanillaNahida/group-verify-service?style=flat-square)](https://github.com/VanillaNahida/group-verify-service/blob/main/LICENSE)
[![GitHub stars](https://img.shields.io/github/stars/VanillaNahida/group-verify-service?style=flat-square)](https://github.com/VanillaNahida/group-verify-service/stargazers)
[![GitHub forks](https://img.shields.io/github/forks/VanillaNahida/group-verify-service?style=flat-square)](https://github.com/VanillaNahida/group-verify-service/network)
[![GitHub issues](https://img.shields.io/github/issues/VanillaNahida/group-verify-service?style=flat-square)](https://github.com/VanillaNahida/group-verify-service/issues)
[![php8](https://img.shields.io/badge/PHP-8.0+-blue.svg?style=flat-square)](https://www.php.net/)
[![Platform](https://img.shields.io/badge/Platform-Windows%20%7C%20Linux-brightgreen.svg?style=flat-square)]()

[📦 下载使用](#-安装步骤) | [📖 API文档](#-api-文档) | [💬 问题反馈](https://github.com/VanillaNahida/group-verify-service/issues)

</div>

## 项目简介

为项目[astrbot_plugin_group_geetest_verify](https://github.com/VanillaNahida/astrbot_plugin_group_geetest_verify)群聊入群验证插件开发的后端，使用极验Geetest V4实现入群人机验证处理，基于 ThinkPHP 8 框架开发的极验验证码服务，提供完整的人机验证解决方案，通过生成验证链接、受试者访问链接验证获取验证码，再把验证码发到群聊，机器人收到验证码后，调用后端接口验证验证码是否正确，若正确则允许入群，否则拒绝入群。

## 效果展示

<div align="center">

<img src="img/1.png" alt="效果图1" width="600" />

<br />

<img src="img/2.png" alt="效果图2" width="600" />

<br />

<img src="img/3.png" alt="效果图3" width="600" />

</div>

## 技术栈

- PHP 8.0+
- ThinkPHP 8.0
- ThinkORM 3.0/4.0
- SQLite 数据库
- Vue 3
- ArcoDesign Vue
- Vite

## 功能特性

- 生成唯一验证链接
- 集成极验 4.0 行为验证
- 生成 6 位数字+字母验证码
- 验证码有效期管理
- 验证码使用状态跟踪
- 验证码使用后自动失效
- 过期验证码自动清理
- API 密钥认证保护
- RESTful API 设计
- 使用SHA256(群聊ID+用户ID+时间戳+盐值)算法生成Ticket
- 详细的错误提示信息

## 安装步骤

### 即开即用部署（推荐，服务器无需安装依赖）

该方案的目标是：服务器端只做上传与配置运行目录，不在服务器上执行 composer。

1. 准备发布包

- 直接使用仓库自带的 `vendor/`（已包含依赖）
- 如果你更新了依赖，需要在本地/CI 重新生成 `vendor/`（在 `backend/` 目录执行）：

```bash
composer install --no-dev -o
```

本地/CI 的 PHP 需要启用以下扩展（否则依赖无法安装或运行时无法连接 SQLite）：

- fileinfo
- sqlite3
- pdo_sqlite

2. 上传到服务器

- 上传 `backend/` 目录下的所有文件（包含 `vendor/`）
- 站点运行目录/网站目录指向 `backend/public/`
- 确保 `runtime/`、`database/` 目录可写

> Nginx 用户请务必配置伪静态（否则 ThinkPHP 路由可能无法生效），并禁止直接访问运行目录。示例（放在 `server {}` 中）：
>
> ```nginx
> location ~* (runtime|application)/{
>     return 403;
> }
> location / {
>     if (!-e $request_filename){
>         rewrite  ^(.*)$  /index.php?s=$1  last;   break;
>     }
> }
> ```

3. 首次初始化

- 访问 `https://你的域名/setup`
- 按页面提示填写 `GEETEST_CAPTCHA_ID`、`GEETEST_CAPTCHA_KEY`、`API_KEY`、`SALT` 等
- 提交后会自动生成 `.env` 并初始化 SQLite 数据库
- 仅首次安装可用：当 `.env` 已存在时，`/setup` 会返回 404

### 前端构建产物（提交代码时自动同步到后端 public）

前端源码在 `frontend/`，构建产物输出到 `backend/public/static/verify/`，后端通过 `GET /v/:ticket` 提供验证页面入口。

如需“提交代码时自动构建并把产物写入后端 public”，可启用仓库内置的 git hook：

```bash
git config core.hooksPath githooks
```

启用后，当本次提交涉及前端相关变更时会自动执行：

```bash
cd frontend && npm run build
```

说明：
- 仅当本次提交的暂存区包含 `frontend/` 或 `backend/public/static/verify/` 的变更时才会执行构建
- 若需要构建但构建失败（或未安装 npm），会直接阻止提交

## 项目结构

```
backend/
├── app/
│   ├── command/                    # 命令行工具
│   │   └── InitDatabase.php        # 数据库初始化命令
│   ├── controller/                 # 控制器
│   │   ├── Index.php               # 默认控制器
│   │   └── VerifyController.php    # 验证码相关接口
│   ├── middleware/                 # 中间件
│   │   └── ApiAuth.php            # API 密钥认证中间件
│   ├── model/                     # 模型
│   │   ├── GeetestModel.php       # 极验验证业务逻辑
│   │   └── GeetestTable.php       # 数据库模型
│   ├── BaseController.php         # 基础控制器
│   ├── AppService.php             # 应用服务
│   ├── ExceptionHandle.php        # 异常处理
│   ├── Request.php                # 请求类
│   ├── common.php                 # 公共函数
│   ├── event.php                  # 事件定义
│   ├── middleware.php             # 中间件配置
│   ├── provider.php               # 服务提供者
│   └── service.php                # 服务定义
├── config/                       # 配置文件加载器
│   ├── app.php                   # 应用配置
│   ├── database.php              # 数据库配置
│   ├── geetest.php               # 极验配置
│   ├── cache.php                 # 缓存配置
│   ├── log.php                   # 日志配置
│   ├── middleware.php            # 中间件配置
│   ├── route.php                 # 路由配置
│   ├── session.php               # 会话配置
│   └── ...                       # 其他配置文件
├── database/                     # 数据库文件
│   ├── migrations/
│   │   └── Geetest_Table.sql     # 数据库迁移文件
│   └── .gitkeep
├── public/                       # 静态资源和入口文件
│   ├── index.php                 # 应用入口
│   ├── router.php                # 路由文件
│   └── static/                   # 静态资源
│       └── verify/               # 前端构建产物（Vue）
├── route/                       # 路由配置
│   └── app.php                   # 路由定义
├── extend/                     # 扩展类库
│   └── .gitignore
├── runtime/                    # 运行时缓存
│   └── .gitignore
├── .example.env                # 环境变量模板
├── .gitignore                  # Git 忽略文件
├── .htaccess                   # Apache 配置
├── .travis.yml                 # Travis CI 配置
├── composer.json               # Composer 配置
└── think                       # ThinkPHP 命令行工具

frontend/
├── index.html
├── package.json
├── vite.config.js
└── src/
    ├── main.js
    └── App.vue
```

## API 文档

**注意**：以下接口中标注需要 API 密钥认证的接口，必须在请求头中添加 `Authorization` 字段：

```
Authorization: Bearer 你的API密钥
```

### 1. 生成验证链接

**接口地址**：`POST /verify/create`

**认证方式**：需要 API 密钥认证

**请求参数**：

| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| group_id | string | 是 | 分组 ID（必须为数字） |
| user_id | string | 是 | 用户 ID（必须为数字） |

**返回示例**：

```json
{
    "code": 0,
    "msg": "success",
    "data": {
        "ticket": "a5294bcefc82bdc5b7990f577df8430274aefb116df1d6e337156ef9c17d56eb",
        "url": "http://localhost:8000/v/a5294bcefc82bdc5b7990f577df8430274aefb116df1d6e337156ef9c17d56eb",
        "expire": 300
    }
}
```

**错误示例**：

```json
{
    "code": 400,
    "msg": "参数错误：group_id 和 user_id 必须为数字"
}
```

### 2. 验证页面

**接口地址**：`GET /v/:ticket`

**认证方式**：无需认证

**请求参数**：

| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| ticket | string | 是 | 验证令牌（URL 路径参数） |

**返回**：HTML（SPA 入口页面，前端会调用状态接口查询 ticket 是否有效）

**错误响应**：
- ticket 为空：返回 400 状态码和"无效的验证链接"

### 2.1 票据状态

**接口地址**：`GET /verify/status/:ticket`

**认证方式**：无需认证

**返回示例**（未验证）：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "ticket": "xxx",
    "verified": false,
    "captcha_id": "你的captcha_id",
    "code_expire": 300,
    "expire_minutes": 5
  }
}
```

**返回示例**（已验证）：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "ticket": "xxx",
    "verified": true,
    "code": "A3B5C7",
    "code_expire": 300,
    "expire_minutes": 5
  }
}
```

### 3. 极验验证回调

**接口地址**：`POST /verify/callback`

**认证方式**：无需认证

**请求参数**：

| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| ticket | string | 是 | 验证令牌 |
| lot_number | string | 是 | 极验验证批次号 |
| captcha_output | string | 是 | 极验验证输出 |
| pass_token | string | 是 | 极验验证通行证 |
| gen_time | string | 是 | 极验验证生成时间 |

**返回示例**：

```json
{
    "code": 0,
    "msg": "验证成功",
    "data": {
        "code": "A3B5C7"
    }
}
```

**错误示例**：

```json
{
    "code": 400,
    "msg": "验证失败，请重试"
}
```

### 4. 验证验证码

**接口地址**：`POST /verify/check`

**认证方式**：需要 API 密钥认证

**请求参数**：

| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| group_id | string | 是 | 分组 ID（必须为数字） |
| user_id | string | 否 | 用户 ID（必须为数字） |
| code | string | 是 | 验证码（6 位数字+字母） |

**返回示例**：

```json
{
    "code": 0,
    "msg": "验证通过",
    "passed": true,
    "data": {
        "user_id": "33550336",
        "group_id": "33550336"
    }
}
```

**错误示例**：

```json
{
    "code": 400,
    "msg": "验证失败：验证码已使用",
    "passed": false
}
```

**可能的错误信息**：
- 参数错误：缺少必填参数 group_id 或 code
- 参数错误：group_id 必须为数字
- 参数错误：user_id 必须为数字
- 验证失败：验证码已使用
- 验证失败：验证码已过期
- 验证失败：验证码未完成验证
- 验证失败：验证码不存在或已失效
- 验证失败：用户ID不匹配

### 5. 清理过期验证码

**接口地址**：`GET /verify/clean`

**认证方式**：需要 API 密钥认证

**返回示例**：

```json
{
    "code": 0,
    "msg": "清理了 5 个过期验证码"
}
```

## 使用流程

1. **生成验证链接**：Bot 调用 `POST /verify/create` 接口（需在请求头添加 API 密钥），传入 `group_id` 和 `user_id`，获取验证链接和 `ticket`
2. **用户验证**：用户访问短链接 `http://domain/v/{ticket}`，完成极验行为验证
3. **获取验证码**：验证通过后，系统生成 6 位数字+字母验证码，页面显示验证码并提供一键复制功能
4. **提交验证码**：用户在群聊中发送验证码
5. **验证验证码**：Bot 调用 `POST /verify/check` 接口（需在请求头添加 API 密钥），验证验证码的有效性
6. **自动失效**：验证码验证通过后自动失效，无法重复使用

## 开发方式

### 1. 运行开发服务器

```bash
cd backend && php think run
```

### 2. 代码规范

- 遵循 PSR-4 自动加载规范
- 遵循 ThinkPHP 代码规范
- 使用 PHP 8.0+ 特性

### 4. 数据库操作

项目使用 ThinkORM 进行数据库操作，默认使用 SQLite 数据库，可在 `.env` 文件中修改为其他数据库。

#### 数据库表结构

**GeetestTable** - 验证码数据表

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | INTEGER | 主键，自增 |
| token | VARCHAR(64) | 验证令牌（唯一） |
| group_id | VARCHAR(64) | 分组 ID |
| user_id | VARCHAR(64) | 用户 ID |
| code | VARCHAR(10) | 6位验证码 |
| verified | TINYINT(1) | 是否已完成极验验证（0:未验证, 1:已验证） |
| used | TINYINT(1) | 验证码是否已使用（0:未使用, 1:已使用） |
| ip | VARCHAR(45) | 用户 IP 地址 |
| user_agent | VARCHAR(500) | 用户浏览器标识 |
| extra | TEXT | 额外数据（JSON 格式） |
| expire_at | INTEGER | 过期时间戳 |
| verified_at | INTEGER | 验证完成时间戳 |
| used_at | INTEGER | 验证码使用时间戳 |
| created_at | INTEGER | 创建时间戳 |
| updated_at | INTEGER | 更新时间戳 |

**索引说明**：
- `idx_code_group`: code + group_id 组合索引，用于快速查找验证码
- `idx_group_user`: group_id + user_id 组合索引，用于快速查找用户验证记录
- `idx_expire`: expire_at 索引，用于快速清理过期数据

### 5. 添加新功能

- 在 `app/controller/` 目录下添加控制器
- 在 `route/app.php` 文件中添加路由
- 在 `app/model/` 目录下添加模块

## 配置说明

首次部署时，如果还没有 `.env`，可以直接访问 `/setup` 走页面初始化生成 `.env` 并初始化 SQLite 数据库（`.env` 已存在时 `/setup` 会返回 404）。

### 极验配置

极验配置通过 `.env` 文件中的环境变量进行设置：

| 配置项 | 类型 | 默认值 | 描述 |
|--------|------|--------|------|
| GEETEST_CAPTCHA_ID | string | - | 极验验证码 ID |
| GEETEST_CAPTCHA_KEY | string | - | 极验验证码 Key |
| GEETEST_API_SERVER | string | https://gcaptcha4.geetest.com | 极验 API 服务器地址 |
| GEETEST_CODE_EXPIRE | int | 300 | 验证码有效期（秒） |

### API 密钥配置

| 变量名 | 类型 | 默认值 | 描述 |
|--------|------|--------|------|
| API_KEY | string | - | API 访问密钥，用于保护需要认证的接口 |

### Ticket 生成盐值配置

| 变量名 | 类型 | 默认值 | 描述 |
|--------|------|--------|------|
| SALT | string | - | Ticket 生成盐值，用于生成 ticket 的哈希值，建议使用复杂字符串，长度至少 32 位，不要泄露 |

**重要说明**：
- 盐值用于生成 ticket 的哈希值，算法为 `SHA256(群聊ID+用户ID+时间戳+盐值)`
- 建议使用随机字符串，越复杂越好
- 请妥善保管盐值，避免泄露给未授权的第三方
- 一旦盐值泄露，攻击者可能伪造有效的验证链接

### 数据库配置

| 变量名 | 类型 | 默认值 | 描述 |
|--------|------|--------|------|
| DB_DRIVER | string | sqlite | 数据库驱动类型（sqlite/mysql/pgsql） |
| DB_HOST | string | 127.0.0.1 | 数据库主机地址 |
| DB_NAME | string | - | 数据库名称 |
| DB_USER | string | root | 数据库用户名 |
| DB_PASS | string | - | 数据库密码 |
| DB_PORT | int | 3306 | 数据库端口 |
| DB_CHARSET | string | utf8mb4 | 数据库字符集 |
| DB_PREFIX | string | - | 数据库表前缀 |
| DB_SQLITE_PATH | string | ./database/geetest.db | SQLite 数据库文件路径 |

### 应用配置

| 变量名 | 类型 | 默认值 | 描述 |
|--------|------|--------|------|
| APP_DEBUG | bool | true | 是否开启调试模式 |
| APP_NAMESPACE | string | - | 应用命名空间 |
| WITH_ROUTE | bool | true | 是否启用路由 |
| DEFAULT_APP | string | index | 默认应用 |
| DEFAULT_TIMEZONE | string | Asia/Shanghai | 默认时区 |
| ERROR_MESSAGE | string | 页面错误！请稍后再试～ | 错误提示信息 |
| SHOW_ERROR_MSG | bool | false | 是否显示详细错误信息 |

## 注意事项

1. **请妥善保管好您的极验验证码 ID 和 Key、API 密钥和 Ticket 生成盐值（SALT），避免泄露给未授权的第三方**
2. 建议在生产环境中关闭调试模式（`APP_DEBUG = false`）
3. 虽然项目会定期清理过期的验证码，但你也可通过定时任务调用 `GET /verify/clean` 接口来手动清理。
4. 验证码有效期默认 5 分钟（300 秒），可根据实际需求调整 `GEETEST_CODE_EXPIRE`
5. 建议使用 HTTPS 协议部署服务
6. 验证码为 6 位数字+字母组合，验证通过后验证码和验证链接会自动失效，无法重复使用
7. `group_id` 和 `user_id` 参数必须为数字类型，否则会返回参数错误
8. 验证页面使用短链接格式 `http://你的域名/v/{ticket}`，便于分享

## 许可证

GPL-3.0 License

## 贡献

欢迎提交 Issue 和 Pull Request！
