# API 文档（group-verify-service）

## 约定

- Base URL：以部署域名为准，例如 `https://example.com`
- 返回结构：大部分接口返回 `code`、`msg`，并可选包含 `data`
- 频率限制：部分接口可能返回 `429`，并带 `Retry-After` 响应头（秒）

## 认证

### 1) 机器人调用（API Key）

需要认证的接口使用请求头：

```
Authorization: Bearer <API_KEY>
```

`API_KEY` 可在首次初始化 `/setup` 生成，或在管理后台里维护（支持多个 key）。

### 2) 管理后台（API Key）

管理后台接口同样使用 API Key 鉴权（为安全起见，仅允许默认 API Key，即 `api_keys` 表里 `id` 最小的那条）：

```
Authorization: Bearer <API_KEY>
```

## 验证相关接口

### 生成验证链接

- 方法：POST
- 路径：`/verify/create`
- 认证：需要 API Key

请求参数（表单或 JSON 都可）：

| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| group_id | string | 是 | 群/分组 ID（必须为纯数字字符串） |
| user_id | string | 是 | 用户 ID（必须为纯数字字符串） |

成功响应：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "ticket": "a5294bce...",
    "url": "https://example.com/v/a5294bce...",
    "expire": 300
  }
}
```

可能的错误响应：

- `400`：`参数错误` / `参数错误：group_id 和 user_id 必须为数字`
- `401`：`Unauthorized: Invalid Authorization header format` / `Unauthorized: Invalid API key`
- `429`：`请求过于频繁，请稍后重试`

### 打开验证页面（短链接）

- 方法：GET
- 路径：`/v/:ticket`
- 认证：无
- 返回：HTML（前端页面）

可能的错误响应：

- `400`：`无效的验证链接`
- `500`：`验证页面资源缺失`

### 查询 ticket 状态

- 方法：GET
- 路径：`/verify/status/:ticket`
- 认证：无

成功响应（未完成验证）：

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

成功响应（已完成验证）：

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

可能的错误响应：

- `400`：`参数错误`
- `404`：`验证链接已过期或不存在`

### 极验回调（提交验证结果）

- 方法：POST
- 路径：`/verify/callback`
- 认证：无

请求参数（表单或 JSON 都可）：

| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| ticket | string | 是 | ticket（验证令牌） |
| lot_number | string | 是 | 极验批次号 |
| captcha_output | string | 是 | 极验输出 |
| pass_token | string | 是 | 极验通行证 |
| gen_time | string | 是 | 极验生成时间 |

成功响应：

```json
{
  "code": 0,
  "msg": "验证成功",
  "data": {
    "code": "A3B5C7"
  }
}
```

可能的错误响应：

- `400`：`参数错误` / `验证失败，请重试`
- `404`：`验证链接已过期或不存在`
- `429`：`请求过于频繁，请稍后重试`

### 校验验证码（机器人核验）

- 方法：POST
- 路径：`/verify/check`
- 认证：需要 API Key

请求参数（表单或 JSON 都可）：

| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| group_id | string | 是 | 群/分组 ID（必须为纯数字字符串） |
| user_id | string | 否 | 用户 ID（必须为纯数字字符串） |
| code | string | 是 | 页面展示的 6 位验证码 |

成功响应：

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

可能的错误响应：

- `400`：`参数错误：缺少必填参数 group_id 或 code`
- `400`：`参数错误：group_id 必须为数字`
- `400`：`参数错误：user_id 必须为数字`
- `400`：`验证失败：验证码已使用`
- `400`：`验证失败：验证码已过期`
- `400`：`验证失败：验证码未完成验证`
- `400`：`验证失败：验证码不存在或已失效`
- `400`：`验证失败：用户ID不匹配`
- `401`：认证失败
- `429`：请求过于频繁（返回中 `passed=false`）

### 清理过期验证码

- 方法：GET
- 路径：`/verify/clean`
- 认证：需要 API Key（仅默认 key）

成功响应：

```json
{
  "code": 0,
  "msg": "清理了 5 个过期验证码"
}
```

可能的错误响应：

- `401`：认证失败
- `403`：`权限不足：该接口仅允许默认 API Key 调用`

### 重置当前 API Key

- 方法：POST
- 路径：`/verify/reset-key`
- 认证：需要 API Key（仅默认 key）

成功响应：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "id": 1,
    "value": "重置后的新密钥明文",
    "updated_at": 1730000000
  }
}
```

可能的错误响应：

- `401`：`Unauthorized`
- `403`：`权限不足：该接口仅允许默认 API Key 调用`
- `429`：`请求过于频繁，请稍后重试`

## 管理后台接口

说明：

- 所有 `/admin/*` 管理接口均使用 API Key 鉴权（仅默认 key）

### 仪表盘概览

- 方法：GET
- 路径：`/admin/dashboard`
- 认证：需要 API Key（仅默认 key）

成功响应（示例）：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "now": 1730000000,
    "api_keys_total": 3,
    "tickets_total": 100,
    "tickets_verified_total": 80,
    "tickets_used_total": 50,
    "tickets_pending": 10,
    "tickets_expired_total": 40,
    "calls_24h_total": 2000,
    "calls_24h_error": 12,
    "calls_24h_by_endpoint": [
      { "endpoint": "/verify/create", "count": 1200 }
    ],
    "calls_24h_top_groups": [
      { "group_id": "123456", "count": 300 }
    ],
    "recent_calls": [
      { "id": 1, "created_at": 1730000000, "endpoint": "/verify/create", "method": "POST", "status_code": 200 }
    ]
  }
}
```

### 查询 API 调用日志

- 方法：GET
- 路径：`/admin/api-call-logs`
- 认证：需要 API Key（仅默认 key）

查询参数（均为可选）：

| 参数名 | 类型 | 说明 |
|---|---|---|
| page | int | 页码（默认 1） |
| page_size | int | 每页条数（默认 20，最大 200） |
| from | int | 开始时间戳（created_at >= from） |
| to | int | 结束时间戳（created_at <= to） |
| api_key_id | int | 按 api_key_id 过滤 |
| status_code | int | 按 status_code 过滤 |
| endpoint | string | endpoint 模糊匹配 |
| group_id | string | 按群号过滤 |
| user_id | string | 按用户过滤 |

### 获取配置项

- 方法：GET
- 路径：`/admin/settings`
- 认证：需要 API Key（仅默认 key）

成功响应（示例）：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "items": [
      { "key": "GEETEST_CAPTCHA_ID", "is_set": true, "value": "xxx", "masked": "", "source": "DB" },
      { "key": "GEETEST_CAPTCHA_KEY", "is_set": true, "value": "", "masked": "abcd...wxyz", "source": "DB" }
    ]
  }
}
```

说明：

- 敏感字段不会直接返回明文，只返回 `masked`
- `API_KEY` 在后台以多条记录维护，`source` 会是 `API_KEYS`

### 更新配置项

- 方法：PUT
- 路径：`/admin/settings`
- 认证：需要 API Key（仅默认 key）

请求体（JSON）：

```json
{
  "values": {
    "GEETEST_CAPTCHA_ID": "xxx",
    "GEETEST_CAPTCHA_KEY": "yyy",
    "GEETEST_API_SERVER": "https://gcaptcha4.geetest.com",
    "GEETEST_CODE_EXPIRE": "300",
    "API_KEY": "key1,key2",
    "SALT": "至少32位的随机字符串"
  }
}
```

说明：

- 仅更新非空值
- `API_KEY` 支持逗号/空格/分号分隔，或 JSON 数组文本（如 `["k1","k2"]`），更新时会替换全部 keys

可能的错误响应：

- `400`：校验失败（例如 `GEETEST_CODE_EXPIRE` 非整数，`SALT` 太短等）

### 列出 API Keys

- 方法：GET
- 路径：`/admin/api-keys`
- 认证：需要 API Key（仅默认 key）

查询参数：

| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| id | string | 否 | 仅返回指定 id（必须为数字字符串） |

成功响应：

```json
{
  "code": 0,
  "msg": "success",
  "data": { "items": [ { "id": 1, "is_default": true, "masked": "abcd...wxyz" } ] }
}
```

### 创建 API Key

- 方法：POST
- 路径：`/admin/api-keys`
- 认证：需要 API Key（仅默认 key）

请求体（JSON，可选）：

```json
{ "value": "自定义密钥（>=16位）" }
```

不传 `value` 时会自动生成。

成功响应会返回新建 key 的明文 `value`（只在创建/重置时返回）。

### 重置 API Key

- 方法：POST
- 路径：`/admin/api-keys/:id/reset`
- 认证：需要 API Key（仅默认 key）

### 删除 API Key

- 方法：DELETE
- 路径：`/admin/api-keys/:id`
- 认证：需要 API Key（仅默认 key）

限制：

- 默认 key 不可删除

## 管理后台页面路由

- `GET /admin`
- `GET /admin/login`

返回前端页面（与验证页共用同一份构建产物）。

