<?php

namespace app\controller;

use app\BaseController;

class Index extends BaseController
{
    public function index()
    {
        return response('', 403);
    }

    public function hello($name = 'ThinkPHP8')
    {
        return 'hello,' . $name;
    }

    public function setup()
    {
        $rootPath = root_path();
        $envPath = $rootPath . '.env';

        if (is_file($envPath)) {
            return response('', 404);
        }

        $defaults = [
            'app_debug'            => 'false',
            'geetest_captcha_id'   => '',
            'geetest_captcha_key'  => '',
            'geetest_api_server'   => 'https://gcaptcha4.geetest.com',
            'geetest_code_expire'  => '300',
            'api_key'              => '',
            'salt'                 => $this->generateSalt(),
            'db_sqlite_path'       => './database/geetest.db',
        ];

        $checks = $this->collectSetupChecks($rootPath);

        if (!$this->request->isPost()) {
            return $this->renderSetupForm([], $defaults, $checks);
        }

        $values = [
            'app_debug'            => (string)$this->request->post('app_debug', $defaults['app_debug']),
            'geetest_captcha_id'   => (string)$this->request->post('geetest_captcha_id', $defaults['geetest_captcha_id']),
            'geetest_captcha_key'  => (string)$this->request->post('geetest_captcha_key', $defaults['geetest_captcha_key']),
            'geetest_api_server'   => (string)$this->request->post('geetest_api_server', $defaults['geetest_api_server']),
            'geetest_code_expire'  => (string)$this->request->post('geetest_code_expire', $defaults['geetest_code_expire']),
            'api_key'              => (string)$this->request->post('api_key', $defaults['api_key']),
            'salt'                 => (string)$this->request->post('salt', $defaults['salt']),
            'db_sqlite_path'       => (string)$this->request->post('db_sqlite_path', $defaults['db_sqlite_path']),
        ];

        foreach ($values as $k => $v) {
            $values[$k] = $this->sanitizeEnvValue($v);
        }

        if ($values['salt'] === '') {
            $values['salt'] = $defaults['salt'];
        }

        $errors = $this->validateSetupValues($values);
        if ($errors) {
            return $this->renderSetupForm($errors, $values, $checks);
        }

        $databaseDir = $rootPath . 'database';
        $runtimeDir = $rootPath . 'runtime';

        if (!is_dir($databaseDir) && !@mkdir($databaseDir, 0775, true) && !is_dir($databaseDir)) {
            return $this->renderSetupForm(['无法创建 database 目录，请检查权限'], $values, $checks);
        }

        if (!is_dir($runtimeDir) && !@mkdir($runtimeDir, 0775, true) && !is_dir($runtimeDir)) {
            return $this->renderSetupForm(['无法创建 runtime 目录，请检查权限'], $values, $checks);
        }

        if (!is_writable($databaseDir)) {
            return $this->renderSetupForm(['database 目录不可写，请检查权限'], $values, $checks);
        }

        if (!is_writable($runtimeDir)) {
            return $this->renderSetupForm(['runtime 目录不可写，请检查权限'], $values, $checks);
        }

        if (!is_writable($rootPath)) {
            return $this->renderSetupForm(['项目根目录不可写，无法生成 .env，请检查权限'], $values, $checks);
        }

        $envContent = $this->buildEnvContent($values);
        if (@file_put_contents($envPath, $envContent, LOCK_EX) === false) {
            return $this->renderSetupForm(['写入 .env 失败，请检查权限'], $values, $checks);
        }

        $dbPath = $this->resolveSqlitePath($values['db_sqlite_path'], $rootPath);
        $initResult = $this->initSqliteDatabase($dbPath, $rootPath);
        if ($initResult !== true) {
            @unlink($envPath);
            return $this->renderSetupForm([$initResult], $values, $checks);
        }

        return $this->renderSetupSuccess();
    }

    private function generateSalt(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            return hash('sha256', uniqid('', true) . microtime(true));
        }
    }

    private function collectSetupChecks(string $rootPath): array
    {
        $databaseDir = $rootPath . 'database';
        $runtimeDir = $rootPath . 'runtime';
        $vendorDir = $rootPath . 'vendor';

        $phpVersionOk = version_compare(PHP_VERSION, '8.0.0', '>=');

        $checks = [];
        $checks[] = ['title' => 'PHP 版本 >= 8.0', 'ok' => $phpVersionOk, 'detail' => PHP_VERSION];
        $checks[] = ['title' => '扩展：fileinfo', 'ok' => extension_loaded('fileinfo'), 'detail' => extension_loaded('fileinfo') ? '已启用' : '未启用'];
        $checks[] = ['title' => '扩展：sqlite3', 'ok' => extension_loaded('sqlite3'), 'detail' => extension_loaded('sqlite3') ? '已启用' : '未启用'];
        $checks[] = ['title' => '扩展：pdo_sqlite', 'ok' => extension_loaded('pdo_sqlite'), 'detail' => extension_loaded('pdo_sqlite') ? '已启用' : '未启用'];

        $checks[] = ['title' => '依赖目录 vendor/ 已上传', 'ok' => is_dir($vendorDir), 'detail' => $vendorDir];

        $checks[] = ['title' => 'database/ 可写（用于 SQLite）', 'ok' => is_dir($databaseDir) ? is_writable($databaseDir) : false, 'detail' => $databaseDir];
        $checks[] = ['title' => 'runtime/ 可写（用于日志/缓存）', 'ok' => is_dir($runtimeDir) ? is_writable($runtimeDir) : false, 'detail' => $runtimeDir];
        $checks[] = ['title' => '项目根目录可写（用于生成 .env）', 'ok' => is_writable($rootPath), 'detail' => $rootPath];

        return $checks;
    }

    private function validateSetupValues(array $values): array
    {
        $errors = [];

        if ($values['geetest_captcha_id'] === '') {
            $errors[] = 'GEETEST_CAPTCHA_ID 不能为空';
        }
        if ($values['geetest_captcha_key'] === '') {
            $errors[] = 'GEETEST_CAPTCHA_KEY 不能为空';
        }
        if ($values['api_key'] === '') {
            $errors[] = 'API_KEY 不能为空';
        }
        if (mb_strlen($values['api_key']) < 16) {
            $errors[] = 'API_KEY 建议至少 16 位';
        }
        if ($values['salt'] === '') {
            $errors[] = 'SALT 不能为空';
        }
        if (mb_strlen($values['salt']) < 32) {
            $errors[] = 'SALT 建议至少 32 位';
        }
        if ($values['geetest_api_server'] === '') {
            $errors[] = 'GEETEST_API_SERVER 不能为空';
        }
        if (!filter_var($values['geetest_api_server'], FILTER_VALIDATE_URL)) {
            $errors[] = 'GEETEST_API_SERVER 不是合法 URL';
        }
        if ($values['geetest_code_expire'] === '' || !ctype_digit($values['geetest_code_expire'])) {
            $errors[] = 'GEETEST_CODE_EXPIRE 必须是整数';
        } else {
            $expire = (int)$values['geetest_code_expire'];
            if ($expire < 30 || $expire > 3600) {
                $errors[] = 'GEETEST_CODE_EXPIRE 建议在 30~3600 之间';
            }
        }
        $appDebug = strtolower($values['app_debug']);
        if (!in_array($appDebug, ['true', 'false', '1', '0'], true)) {
            $errors[] = 'APP_DEBUG 只能是 true/false';
        }
        if ($values['db_sqlite_path'] === '') {
            $errors[] = 'DB_SQLITE_PATH 不能为空';
        }

        return $errors;
    }

    private function sanitizeEnvValue(string $value): string
    {
        $value = trim($value);
        $value = str_replace(["\r", "\n"], '', $value);
        return $value;
    }

    private function buildEnvContent(array $values): string
    {
        $appDebug = strtolower($values['app_debug']);
        if ($appDebug === '1') {
            $appDebug = 'true';
        }
        if ($appDebug === '0') {
            $appDebug = 'false';
        }

        $lines = [];
        $lines[] = 'APP_DEBUG = ' . $appDebug;
        $lines[] = 'WITH_ROUTE = true';
        $lines[] = 'DEFAULT_APP = index';
        $lines[] = 'DEFAULT_TIMEZONE = Asia/Shanghai';
        $lines[] = 'SHOW_ERROR_MSG = false';
        $lines[] = 'GEETEST_CAPTCHA_ID = ' . $this->envEncode($values['geetest_captcha_id']);
        $lines[] = 'GEETEST_CAPTCHA_KEY = ' . $this->envEncode($values['geetest_captcha_key']);
        $lines[] = 'GEETEST_API_SERVER = ' . $this->envEncode($values['geetest_api_server']);
        $lines[] = 'GEETEST_CODE_EXPIRE = ' . $values['geetest_code_expire'];
        $lines[] = 'API_KEY = ' . $this->envEncode($values['api_key']);
        $lines[] = 'SALT = ' . $this->envEncode($values['salt']);
        $lines[] = 'DB_DRIVER = sqlite';
        $lines[] = 'DB_SQLITE_PATH = ' . $this->envEncode($values['db_sqlite_path']);

        return implode("\n", $lines) . "\n";
    }

    private function envEncode(string $value): string
    {
        $value = $this->sanitizeEnvValue($value);
        if ($value === '') {
            return '""';
        }
        if (preg_match('/[\s#="\'\\\\]/', $value)) {
            $escaped = addcslashes($value, "\\\"");
            return '"' . $escaped . '"';
        }
        return $value;
    }

    private function resolveSqlitePath(string $sqlitePath, string $rootPath): string
    {
        $sqlitePath = trim($sqlitePath);
        if ($sqlitePath === '') {
            return $rootPath . 'database' . DIRECTORY_SEPARATOR . 'geetest.db';
        }

        if (strpos($sqlitePath, '/') === 0 || strpos($sqlitePath, '\\') === 0 || preg_match('/^[a-zA-Z]:/', $sqlitePath)) {
            return $sqlitePath;
        }

        $sqlitePath = ltrim($sqlitePath, '/\\');
        return $rootPath . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sqlitePath);
    }

    private function initSqliteDatabase(string $absoluteDbPath, string $rootPath)
    {
        $dir = dirname($absoluteDbPath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return '无法创建 SQLite 目录：' . $dir;
        }
        if (!is_writable($dir)) {
            return 'SQLite 目录不可写：' . $dir;
        }

        try {
            $pdo = new \PDO('sqlite:' . $absoluteDbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $sqlFile = $rootPath . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . 'Geetest_Table.sql';
            if (!is_file($sqlFile)) {
                return '找不到迁移文件：' . $sqlFile;
            }

            $sql = (string)file_get_contents($sqlFile);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $statement) {
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }
        } catch (\Throwable $e) {
            return '初始化数据库失败：' . $e->getMessage();
        }

        return true;
    }

    private function renderSetupDone(): string
    {
        $body = '
<div class="wizard">
  <ol class="steps">
    <li class="step-item done"><span class="step-index">1</span><span class="step-text">环境检查</span></li>
    <li class="step-item done"><span class="step-index">2</span><span class="step-text">填写配置</span></li>
    <li class="step-item done"><span class="step-index">3</span><span class="step-text">确认生成</span></li>
    <li class="step-item active"><span class="step-index">4</span><span class="step-text">完成</span></li>
  </ol>
  <section class="panel active">
    <h2>已完成</h2>
    <p class="desc"><span class="mono">.env</span> 已存在，已无需再次初始化。</p>
  </section>
</div>';

        return $this->renderHtml('初始化已完成', $body);
    }

    private function renderSetupSuccess(): string
    {
        $body = '
<div class="wizard">
  <ol class="steps">
    <li class="step-item done"><span class="step-index">1</span><span class="step-text">环境检查</span></li>
    <li class="step-item done"><span class="step-index">2</span><span class="step-text">填写配置</span></li>
    <li class="step-item done"><span class="step-index">3</span><span class="step-text">确认生成</span></li>
    <li class="step-item active"><span class="step-index">4</span><span class="step-text">完成</span></li>
  </ol>
  <section class="panel active">
    <h2>初始化成功</h2>
    <p class="desc">已生成 <span class="mono">.env</span> 并初始化 SQLite 数据库。</p>
  </section>
</div>';

        return $this->renderHtml('初始化成功', $body);
    }

    private function renderSetupForm(array $errors, array $values, array $checks = []): string
    {
        $errorHtml = '';
        if ($errors) {
            $items = '';
            foreach ($errors as $error) {
                $items .= '<li>' . htmlspecialchars((string)$error, ENT_QUOTES) . '</li>';
            }
            $errorHtml = '<div class="errors"><ul>' . $items . '</ul></div>';
        }

        $checkItems = '';
        foreach ($checks as $check) {
            $ok = (bool)($check['ok'] ?? false);
            $title = htmlspecialchars((string)($check['title'] ?? ''), ENT_QUOTES);
            $detail = htmlspecialchars((string)($check['detail'] ?? ''), ENT_QUOTES);
            $statusText = $ok ? '通过' : '未通过';
            $checkItems .= '<li class="check-item ' . ($ok ? 'ok' : 'bad') . '"><div class="check-main"><span class="check-title">' . $title . '</span><span class="check-status">' . $statusText . '</span></div><div class="check-detail">' . $detail . '</div></li>';
        }

        $appDebug = htmlspecialchars((string)$values['app_debug'], ENT_QUOTES);
        $captchaId = htmlspecialchars((string)$values['geetest_captcha_id'], ENT_QUOTES);
        $captchaKey = htmlspecialchars((string)$values['geetest_captcha_key'], ENT_QUOTES);
        $apiServer = htmlspecialchars((string)$values['geetest_api_server'], ENT_QUOTES);
        $codeExpire = htmlspecialchars((string)$values['geetest_code_expire'], ENT_QUOTES);
        $apiKey = htmlspecialchars((string)$values['api_key'], ENT_QUOTES);
        $salt = htmlspecialchars((string)$values['salt'], ENT_QUOTES);
        $dbSqlitePath = htmlspecialchars((string)$values['db_sqlite_path'], ENT_QUOTES);

        $initialStep = $errors ? '2' : '1';

        $body = $errorHtml . '
<noscript><div class="noscript">检测到浏览器禁用脚本：将以“单页表单”模式显示，填写后直接提交即可。</div></noscript>

<div class="wizard" data-initial-step="' . $initialStep . '">
  <ol class="steps" id="setupSteps">
    <li class="step-item" data-step="1"><span class="step-index">1</span><span class="step-text">环境检查</span></li>
    <li class="step-item" data-step="2"><span class="step-index">2</span><span class="step-text">填写配置</span></li>
    <li class="step-item" data-step="3"><span class="step-index">3</span><span class="step-text">确认生成</span></li>
    <li class="step-item" data-step="4"><span class="step-index">4</span><span class="step-text">完成</span></li>
  </ol>

  <form method="post" class="form" id="setupForm" autocomplete="off">
    <section class="panel" data-panel="1">
      <h2>步骤 1：环境检查</h2>
      <p class="desc">先把站点运行目录指向 <span class="mono">backend/public/</span>，并确保上传了 <span class="mono">vendor/</span> 依赖。下面是当前服务器的检测结果（未通过项请先处理）。</p>
      <ul class="checks">' . $checkItems . '</ul>
      <div class="tips">
        <p>常见修复：</p>
        <ul>
          <li>缺少扩展：在 PHP 配置中启用 <span class="mono">fileinfo / sqlite3 / pdo_sqlite</span></li>
          <li>目录不可写：为 <span class="mono">backend/runtime</span>、<span class="mono">backend/database</span>、以及项目根目录授予写权限</li>
          <li>vendor 缺失：请在本地/CI 执行 <span class="mono">composer install --no-dev -o</span> 后把 <span class="mono">backend/vendor</span> 一并上传</li>
        </ul>
      </div>
    </section>

    <section class="panel" data-panel="2">
      <h2>步骤 2：填写配置</h2>
      <p class="desc">这些配置会写入 <span class="mono">backend/.env</span>。提交后会自动初始化 SQLite 数据库（默认 <span class="mono">backend/database/geetest.db</span>）。</p>

      <div class="group">
        <div class="group-title">极验配置</div>
        <div class="field">
          <label>GEETEST_CAPTCHA_ID</label>
          <input name="geetest_captcha_id" value="' . $captchaId . '" required />
          <div class="hint">极验后台创建验证码后获得的 ID。</div>
        </div>
        <div class="field">
          <label>GEETEST_CAPTCHA_KEY</label>
          <input name="geetest_captcha_key" value="' . $captchaKey . '" required />
          <div class="hint">极验后台对应的 Key（请妥善保管）。</div>
        </div>
        <div class="field">
          <label>GEETEST_API_SERVER</label>
          <input name="geetest_api_server" value="' . $apiServer . '" placeholder="https://gcaptcha4.geetest.com" required />
          <div class="hint">一般保持默认即可；必须是合法 URL。</div>
        </div>
        <div class="field">
          <label>GEETEST_CODE_EXPIRE</label>
          <input name="geetest_code_expire" value="' . $codeExpire . '" inputmode="numeric" pattern="\\d+" required />
          <div class="hint">验证码有效期（秒），建议 30~3600。</div>
        </div>
      </div>

      <div class="group">
        <div class="group-title">接口认证与安全</div>
        <div class="field">
          <label>API_KEY</label>
          <input name="api_key" value="' . $apiKey . '" required />
          <div class="hint">Bot 调用后端接口用的密钥，建议至少 16 位，且随机性强。</div>
        </div>
        <div class="field">
          <label>SALT</label>
          <input name="salt" value="' . $salt . '" required />
          <div class="hint">用于生成内部 token 的盐值；已自动生成一个高强度值，建议不要改成短字符串。</div>
        </div>
      </div>

      <div class="group">
        <div class="group-title">数据库</div>
        <div class="field">
          <label>DB_SQLITE_PATH</label>
          <input name="db_sqlite_path" value="' . $dbSqlitePath . '" required />
          <div class="hint">SQLite 文件路径：相对路径以 <span class="mono">backend/</span> 为基准，或填写绝对路径。</div>
        </div>
      </div>

      <div class="group">
        <div class="group-title">其他</div>
        <div class="field">
          <label>APP_DEBUG</label>
          <input name="app_debug" value="' . $appDebug . '" placeholder="false" />
          <div class="hint">线上建议 <span class="mono">false</span>（true 会暴露更多错误信息）。</div>
        </div>
      </div>
    </section>

    <section class="panel" data-panel="3">
      <h2>步骤 3：确认生成</h2>
      <p class="desc">将生成 <span class="mono">backend/.env</span> 并初始化数据库。请确认配置无误。</p>
      <div class="confirm"><pre class="mono" id="confirmText"></pre></div>
      <div class="tips"><p>点击“生成”后，如果失败会显示具体错误原因；修正后重新提交即可。</p></div>
    </section>

    <div class="actions">
      <button type="button" class="btn" id="btnPrev">上一步</button>
      <button type="button" class="btn primary" id="btnNext">下一步</button>
      <button type="submit" class="btn primary" id="btnSubmit">生成 .env 并初始化</button>
    </div>
  </form>
</div>';

        return $this->renderHtml('首次初始化', $body);
    }

    private function renderHtml(string $title, string $body): string
    {
        $titleEscaped = htmlspecialchars($title, ENT_QUOTES);
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $titleEscaped . '</title><style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial;max-width:900px;margin:40px auto;padding:0 16px;color:#222;background:#fff;}
h1{font-size:22px;margin:0 0 16px;}
h2{font-size:16px;margin:0 0 12px;}
.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;}
.desc{margin:0 0 12px;color:#444;line-height:1.7;font-size:14px;}
.errors{background:#fff3f3;border:1px solid #ffd6d6;padding:12px;border-radius:10px;margin:12px 0;color:#9b1c1c;}
.errors ul{margin:0;padding-left:18px;}
.noscript{background:#fffbe6;border:1px solid #ffe58f;padding:10px 12px;border-radius:10px;margin:12px 0;color:#614700;}
.wizard{margin-top:10px;}
.steps{list-style:none;display:flex;gap:8px;padding:0;margin:0 0 14px;flex-wrap:wrap;}
.step-item{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #eee;border-radius:999px;background:#fafafa;color:#666;}
.step-item.active{border-color:#1677ff;color:#1677ff;background:#f0f7ff;}
.step-item.done{border-color:#d9f7be;color:#389e0d;background:#f6ffed;}
.step-index{width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:#eee;color:#666;font-size:12px;}
.step-item.active .step-index{background:#1677ff;color:#fff;}
.step-item.done .step-index{background:#52c41a;color:#fff;}
.panel{display:none;border:1px solid #eee;border-radius:12px;padding:14px 14px;background:#fff;}
.panel.active{display:block;}
.form{display:flex;flex-direction:column;gap:12px;}
.group{border:1px solid #f0f0f0;border-radius:12px;padding:12px;background:#fcfcfc;}
.group-title{font-size:13px;color:#555;margin:0 0 10px;font-weight:600;}
.field{display:flex;flex-direction:column;gap:6px;margin-bottom:10px;}
.field:last-child{margin-bottom:0;}
label{font-size:13px;color:#333;font-weight:600;}
input{padding:10px 12px;border:1px solid #ddd;border-radius:10px;font-size:14px;outline:none;background:#fff;}
input:focus{border-color:#1677ff;box-shadow:0 0 0 3px rgba(22,119,255,.12);}
.hint{color:#666;font-size:13px;line-height:1.6;}
.tips{margin-top:12px;color:#666;font-size:13px;line-height:1.8;}
.tips ul{margin:6px 0 0;padding-left:18px;}
.checks{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px;}
.check-item{border:1px solid #f0f0f0;border-radius:12px;padding:10px 12px;background:#fff;}
.check-item.ok{border-color:#d9f7be;background:#f6ffed;}
.check-item.bad{border-color:#ffd6d6;background:#fff1f0;}
.check-main{display:flex;align-items:center;justify-content:space-between;gap:10px;}
.check-title{font-size:13px;color:#222;font-weight:600;}
.check-status{font-size:12px;color:#666;}
.check-item.ok .check-status{color:#389e0d;}
.check-item.bad .check-status{color:#cf1322;}
.check-detail{margin-top:6px;color:#666;font-size:12px;word-break:break-all;}
.confirm{border:1px dashed #ddd;border-radius:12px;padding:12px;background:#fafafa;}
.confirm pre{margin:0;white-space:pre-wrap;word-break:break-word;font-size:13px;line-height:1.7;}
.actions{display:flex;gap:10px;align-items:center;justify-content:flex-end;margin-top:14px;}
.btn{padding:10px 12px;border:1px solid #ddd;border-radius:10px;background:#fff;color:#222;font-size:14px;cursor:pointer;}
.btn:hover{border-color:#cfcfcf;background:#fafafa;}
.btn.primary{border-color:#1677ff;background:#1677ff;color:#fff;}
.btn.primary:hover{background:#0958d9;border-color:#0958d9;}
.btn[disabled]{opacity:.55;cursor:not-allowed;}
a{color:#1677ff;text-decoration:none;}
a:hover{text-decoration:underline;}
</style></head><body><h1>' . $titleEscaped . '</h1>' . $body . '<script>
(function(){
  var wizard = document.querySelector(\".wizard[data-initial-step]\");
  if (!wizard) return;
  var form = document.getElementById(\"setupForm\");
  var steps = Array.prototype.slice.call(document.querySelectorAll(\"#setupSteps .step-item\"));
  var panels = Array.prototype.slice.call(document.querySelectorAll(\"#setupForm .panel\"));
  var btnPrev = document.getElementById(\"btnPrev\");
  var btnNext = document.getElementById(\"btnNext\");
  var btnSubmit = document.getElementById(\"btnSubmit\");
  var confirmText = document.getElementById(\"confirmText\");
  var current = parseInt(wizard.getAttribute(\"data-initial-step\") || \"1\", 10);

  function setActiveStep(n){
    current = n;
    steps.forEach(function(el){
      var s = parseInt(el.getAttribute(\"data-step\") || \"0\", 10);
      el.classList.toggle(\"active\", s === n);
    });
    panels.forEach(function(el){
      var p = parseInt(el.getAttribute(\"data-panel\") || \"0\", 10);
      el.classList.toggle(\"active\", p === n);
    });
    btnPrev.disabled = n <= 1;
    btnNext.style.display = n >= 3 ? \"none\" : \"inline-flex\";
    btnSubmit.style.display = n === 3 ? \"inline-flex\" : \"none\";
    if (n === 3) buildConfirm();
  }

  function mask(v){
    v = (v || \"\").trim();
    if (v.length <= 8) return \"******\";
    return v.slice(0, 4) + \"...\" + v.slice(-4);
  }

  function getValue(name){
    var el = form.querySelector(\"[name=\\\"\" + name + \"\\\"]\");
    return el ? (el.value || \"\") : \"\";
  }

  function buildConfirm(){
    var lines = [];
    lines.push(\"GEETEST_CAPTCHA_ID: \" + getValue(\"geetest_captcha_id\"));
    lines.push(\"GEETEST_CAPTCHA_KEY: \" + mask(getValue(\"geetest_captcha_key\")));
    lines.push(\"GEETEST_API_SERVER: \" + getValue(\"geetest_api_server\"));
    lines.push(\"GEETEST_CODE_EXPIRE: \" + getValue(\"geetest_code_expire\"));
    lines.push(\"API_KEY: \" + mask(getValue(\"api_key\")));
    lines.push(\"SALT: \" + mask(getValue(\"salt\")));
    lines.push(\"DB_SQLITE_PATH: \" + getValue(\"db_sqlite_path\"));
    lines.push(\"APP_DEBUG: \" + getValue(\"app_debug\"));
    confirmText.textContent = lines.join(\"\\n\");
  }

  btnPrev.addEventListener(\"click\", function(){
    if (current > 1) setActiveStep(current - 1);
  });

  btnNext.addEventListener(\"click\", function(){
    if (current === 2) {
      if (!form.reportValidity()) return;
    }
    if (current < 3) setActiveStep(current + 1);
  });

  setActiveStep(current);
})();
</script></body></html>';
    }
}
