<template>
  <div class="page">
    <a-card :title="title" class="card">
      <a-spin :loading="loading" style="width: 100%">
        <a-result v-if="error" status="error" title="加载失败" :subtitle="error" />

        <template v-else>
          <a-result
            v-if="verified"
            status="success"
            title="验证成功"
            subtitle="请在群内发送下方验证码完成验证"
          >
            <template #extra>
              <div class="code">{{ code }}</div>
              <a-typography-paragraph v-if="expireMinutes" style="margin: 12px 0 0; text-align: center">
                绑定码{{ expireMinutes }}分钟内有效，请及时使用。
              </a-typography-paragraph>
              <a-space direction="vertical" size="medium" fill style="margin-top: 16px">
                <a-button type="primary" long @click="copyCode">复制绑定码</a-button>
                <a-button long @click="refreshStatus">刷新状态</a-button>
              </a-space>
            </template>
          </a-result>

          <template v-else>
            <a-typography-paragraph style="margin-top: 0; margin-bottom: 12px">
              1. 点击“开始验证”完成安全验证<br />
              2. 获取绑定码后，在群内发送即可
            </a-typography-paragraph>
            <div id="captcha" class="captcha-container"></div>
            <a-space direction="vertical" size="medium" fill>
              <a-button
                type="primary"
                long
                :loading="submitting"
                :disabled="submitting || !captchaReady"
                @click="startCaptcha"
              >
                {{ captchaReady ? '开始验证' : '验证码加载中…' }}
              </a-button>
              <a-button long :disabled="submitting" @click="refreshStatus">刷新状态</a-button>
            </a-space>
          </template>
        </template>
      </a-spin>
    </a-card>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { Message, Modal } from '@arco-design/web-vue';

const ticket = ref('');
const loading = ref(true);
const error = ref('');
const verified = ref(false);
const code = ref('');
const captchaId = ref('');
const expireMinutes = ref(null);
const captchaReady = ref(false);
const submitting = ref(false);

let captchaObj = null;

const title = computed(() => (verified.value ? '验证成功' : '入群验证'));

function parseTicketFromPath() {
  const search = window.location.search || '';
  const sp = new URLSearchParams(search);
  const q = sp.get('ticket');
  if (q) return q;

  const path = window.location.pathname || '';
  const m1 = path.match(/\/v\/([^/?#]+)/);
  if (m1 && m1[1]) return decodeURIComponent(m1[1]);

  const hash = window.location.hash || '';
  const m2 = hash.match(/#\/v\/([^/?#]+)/);
  return m2 && m2[1] ? decodeURIComponent(m2[1]) : '';
}

function toFormBody(obj) {
  const params = new URLSearchParams();
  Object.keys(obj).forEach((k) => params.append(k, obj[k] == null ? '' : String(obj[k])));
  return params.toString();
}

async function copyText(text) {
  if (navigator.clipboard && window.isSecureContext) {
    await navigator.clipboard.writeText(text);
    return;
  }

  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position = 'fixed';
  ta.style.opacity = '0';
  document.body.appendChild(ta);
  ta.focus();
  ta.select();
  const ok = document.execCommand('copy');
  document.body.removeChild(ta);
  if (!ok) throw new Error('copy failed');
}

function showExpired() {
  error.value = '验证链接已过期或不存在';
  verified.value = false;
  code.value = '';
  expireMinutes.value = null;
}

function initGeetest() {
  if (!captchaId.value) return;

  if (typeof window.initGeetest4 === 'undefined') {
    error.value = '验证码组件加载失败，请刷新页面重试';
    captchaReady.value = false;
    return;
  }

  captchaReady.value = false;
  captchaObj = null;
  const container = document.querySelector('#captcha');
  if (container) container.innerHTML = '';

  window.initGeetest4(
    {
      captchaId: captchaId.value,
      product: 'bind',
      language: 'zh-cn',
      timeout: 10000
    },
    (obj) => {
      captchaObj = obj;

      try {
        obj.appendTo('#captcha');
      } catch (e) {}

      obj
        .onReady(() => {
          captchaReady.value = true;
        })
        .onError(() => {
          error.value = '验证码初始化失败，请刷新页面重试';
          captchaReady.value = false;
        })
        .onSuccess(() => {
          const result = captchaObj && captchaObj.getValidate ? captchaObj.getValidate() : null;
          if (!result) {
            Message.error('请先完成验证');
            return;
          }
          submitVerification(result);
        })
        .onClose(() => {
          submitting.value = false;
        });
    }
  );
}

function startCaptcha() {
  if (!captchaReady.value || !captchaObj) {
    Message.warning('验证码正在加载，请稍候…');
    return;
  }
  try {
    captchaObj.showCaptcha();
  } catch (e) {
    Message.error('验证码出错，请刷新页面重试');
  }
}

async function submitVerification(geetestResult) {
  submitting.value = true;

  try {
    const res = await fetch('/verify/callback', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: toFormBody({
        ticket: ticket.value,
        lot_number: geetestResult.lot_number,
        captcha_output: geetestResult.captcha_output,
        pass_token: geetestResult.pass_token,
        gen_time: geetestResult.gen_time
      })
    });

    const data = await res.json();
    if (data && data.code === 0 && data.data && data.data.code) {
      verified.value = true;
      code.value = String(data.data.code);
      Message.success('验证成功');
      try {
        await copyText(code.value);
        Message.success('验证码已复制，可直接在群内粘贴发送');
      } catch (e) {}
      return;
    }

    Message.error((data && data.msg) || '验证失败，请重试');
    submitting.value = false;
    if (captchaObj) {
      try {
        captchaObj.reset();
      } catch (e) {}
    }
  } catch (e) {
    Message.error('网络异常，请稍后重试');
    submitting.value = false;
  }
}

async function copyCode() {
  if (!code.value) return;
  try {
    await copyText(code.value);
    Message.success('验证码已复制');
  } catch (e) {
    Modal.info({
      title: '复制失败',
      content: '请手动复制验证码：' + code.value,
      hideCancel: true
    });
  }
}

async function refreshStatus() {
  loading.value = true;
  error.value = '';

  try {
    const res = await fetch('/verify/status/' + encodeURIComponent(ticket.value), { method: 'GET' });
    const data = await res.json();

    if (!data || typeof data.code === 'undefined') {
      error.value = '服务器响应异常';
      return;
    }

    if (data.code === 404) {
      showExpired();
      return;
    }

    if (data.code !== 0) {
      error.value = data.msg || '加载失败';
      return;
    }

    const expire = data.data && typeof data.data.expire_minutes !== 'undefined' ? Number(data.data.expire_minutes) : null;
    expireMinutes.value = Number.isFinite(expire) && expire > 0 ? Math.ceil(expire) : null;

    if (data.data && data.data.verified) {
      verified.value = true;
      code.value = String(data.data.code || '');
      return;
    }

    verified.value = false;
    code.value = '';
    captchaId.value = (data.data && data.data.captcha_id) || '';
    initGeetest();
  } catch (e) {
    error.value = '网络异常，请稍后重试';
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  ticket.value = parseTicketFromPath();
  if (!ticket.value) {
    loading.value = false;
    error.value = '无效的验证链接';
    return;
  }
  refreshStatus();
});
</script>

<style scoped>
.page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-bg-1);
}

.card {
  width: 100%;
  max-width: 520px;
}

.code {
  font-size: 40px;
  font-weight: 800;
  letter-spacing: 10px;
  text-align: center;
  background: var(--color-bg-2);
  padding: 16px 18px;
}

.captcha-container {
  height: 0;
  overflow: hidden;
}
</style>
