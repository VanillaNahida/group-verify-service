<template>
  <div class="verify-container">
    <video class="background-video" autoplay loop muted playsinline>
      <source src="https://cdn.xcnahida.cn/api/random-video.php" type="video/webm">
      <!-- <source src="/back.webm" type="video/webm"> -->
    </video>
    <div class="background-shade" aria-hidden="true"></div>
    
    <div class="glass-card">
      <div class="card-header">
        <div class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 3L19 6V11.5C19 16.1 16.1 19.8 12 21C7.9 19.8 5 16.1 5 11.5V6L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            <path d="M9 12L11.1 14.1L15.2 10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h2 class="card-title">{{ title }}</h2>
      </div>
      
      <a-spin :loading="loading" style="width: 100%">
        <div v-if="error" class="error-state">
          <div class="error-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <h3 class="error-title">{{ t('load_failed') }}</h3>
          <p class="error-message">{{ error }}</p>
        </div>

        <template v-else>
          <div v-if="verified" class="success-state">
            <div class="success-icon">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <h3 class="success-title">{{ t('title_success') }}</h3>
            <p class="success-subtitle">{{ t('verify_success_subtitle') }}</p>
            
            <div class="code-display">{{ code }}</div>
            
            <p v-if="expireMinutes" class="expire-info">
              {{ expireTip }}
            </p>
            
            <div class="button-group">
              <button class="btn btn-primary" @click="copyCode">
                {{ t('copy_code') }}
              </button>
              <button class="btn btn-secondary" @click="refreshStatus">
                {{ t('refresh_status') }}
              </button>
            </div>
          </div>

          <div v-else class="verify-state">
            <div class="steps-box">
              {{ t('steps') }}
            </div>
            <div id="captcha" class="captcha-container"></div>
            <div class="button-group">
              <button
                class="btn btn-primary"
                :disabled="submitting || !captchaReady"
                @click="startCaptcha"
              >
                {{ captchaReady ? t('start_verify') : t('captcha_loading') }}
              </button>
              <button class="btn btn-secondary" :disabled="submitting" @click="refreshStatus">
                {{ t('refresh_status') }}
              </button>
            </div>
          </div>
        </template>
      </a-spin>
    </div>
    
    <div class="footer">
      <p class="footer-text">前端美化：<a href="https://github.com/FantasyNetworkCN" target="_blank">FantasyNetwork</a></p>
      <p class="footer-text">友联：<a href="https://music.cnmsb.xin/" target="_blank">Neko云音乐</a></p>
      <p v-if="icpInfo" class="footer-text icp-text">{{ icpInfo }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { Message, Modal } from '@arco-design/web-vue';
import { copyText } from '../../utils/clipboard';
import { toFormBody } from '../../utils/form';
import { parseTicketFromLocation } from '../../utils/url';
import { getStatus, submitCallback } from '../../api/verify';

const props = defineProps({
  t: { type: Function, default: (k) => String(k || '') }
});

const t = props.t;

const ticket = ref('');
const loading = ref(true);
const error = ref('');
const verified = ref(false);
const code = ref('');
const captchaId = ref('');
const expireMinutes = ref(null);
const captchaReady = ref(false);
const submitting = ref(false);
const icpInfo = ref('');

let captchaObj = null;

const title = computed(() => (verified.value ? t('title_success') : t('title_default')));
const expireTip = computed(() => (expireMinutes.value ? t('expire_tip', { minutes: expireMinutes.value }) : ''));

async function getIcpInfo() {
  try {
    const hostname = window.location.hostname;
    console.log('Current hostname:', hostname);
    
    // 尝试多个可能的路径
    const paths = ['/icp.json', '/static/verify/icp.json', './icp.json'];
    let icpData = null;
    
    for (const path of paths) {
      try {
        console.log('Trying path:', path);
        const response = await fetch(path);
        if (response.ok) {
          icpData = await response.json();
          console.log('Loaded ICP data:', icpData);
          break;
        }
      } catch (e) {
        console.log('Failed to load from path:', path, e);
        continue;
      }
    }
    
    if (icpData && icpData[hostname]) {
      icpInfo.value = icpData[hostname];
      console.log('Found ICP info for', hostname, ':', icpInfo.value);
    } else {
      console.log('No ICP info found for hostname:', hostname);
      icpInfo.value = '';
    }
  } catch (e) {
    console.error('Failed to load ICP info:', e);
    icpInfo.value = '';
  }
}

function showExpired() {
  error.value = t('link_expired_or_missing');
  verified.value = false;
  code.value = '';
  expireMinutes.value = null;
}

function initGeetest() {
  if (!captchaId.value) return;

  if (typeof window.initGeetest4 === 'undefined') {
    error.value = t('captcha_component_load_failed');
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
          error.value = t('captcha_init_failed');
          captchaReady.value = false;
        })
        .onSuccess(() => {
          const result = captchaObj && captchaObj.getValidate ? captchaObj.getValidate() : null;
          if (!result) {
            Message.error(t('please_complete_captcha'));
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
    Message.warning(t('captcha_loading_wait'));
    return;
  }
  try {
    captchaObj.showCaptcha();
  } catch (e) {
    Message.error(t('captcha_error_refresh'));
  }
}

async function submitVerification(geetestResult) {
  submitting.value = true;

  try {
    const { data } = await submitCallback(
      toFormBody({
        ticket: ticket.value,
        lot_number: geetestResult.lot_number,
        captcha_output: geetestResult.captcha_output,
        pass_token: geetestResult.pass_token,
        gen_time: geetestResult.gen_time
      })
    );
    if (data && data.code === 0 && data.data && data.data.code) {
      verified.value = true;
      code.value = String(data.data.code);
      Message.success(t('verify_success'));
      try {
        await copyText(code.value);
        Message.success(t('code_copied_paste'));
      } catch (e) {}
      return;
    }

    Message.error((data && data.msg) || t('verify_failed_retry'));
    submitting.value = false;
    if (captchaObj) {
      try {
        captchaObj.reset();
      } catch (e) {}
    }
  } catch (e) {
    Message.error(t('network_error_retry'));
    submitting.value = false;
  }
}

async function copyCode() {
  if (!code.value) return;
  try {
    await copyText(code.value);
    Message.success(t('code_copied'));
  } catch (e) {
    Modal.info({
      title: t('copy_failed'),
      content: t('copy_failed_manual_prefix') + code.value,
      hideCancel: true
    });
  }
}

async function refreshStatus() {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await getStatus(ticket.value);

    if (!data || typeof data.code === 'undefined') {
      error.value = t('server_response_invalid');
      return;
    }

    if (data.code === 404) {
      showExpired();
      return;
    }

    if (data.code !== 0) {
      error.value = data.msg || t('load_failed');
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
  ticket.value = parseTicketFromLocation();
  getIcpInfo();
  if (!ticket.value) {
    loading.value = false;
    error.value = t('invalid_link');
    return;
  }
  refreshStatus();
});
</script>

<style scoped>
:root {
  color-scheme: light;
}

.verify-container {
  --ink: #122235;
  --muted: #687789;
  --line: #e4ebf2;
  --accent: #0f766e;
  --accent-strong: #0b5f59;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 100vh;
  min-height: 100svh;
  padding: 32px 20px 112px;
  box-sizing: border-box;
  overflow: hidden;
  background: #0b1724;
}

.background-video,
.background-shade {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

.background-video {
  z-index: 0;
  object-fit: cover;
  filter: saturate(0.78) contrast(1.05);
}

.background-shade {
  z-index: 1;
  background:
    linear-gradient(135deg, rgba(5, 18, 30, 0.88), rgba(12, 45, 55, 0.62)),
    linear-gradient(180deg, rgba(8, 24, 40, 0.18), rgba(6, 18, 29, 0.72));
  pointer-events: none;
}

.glass-card {
  position: relative;
  z-index: 2;
  width: min(100%, 520px);
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.52);
  border-radius: 20px;
  background: rgba(235, 246, 248, 0.58);
  box-shadow: 0 24px 70px rgba(1, 12, 24, 0.34), 0 4px 16px rgba(1, 12, 24, 0.14), inset 0 1px 0 rgba(255, 255, 255, 0.7);
  -webkit-backdrop-filter: blur(28px) saturate(1.18);
  backdrop-filter: blur(28px) saturate(1.18);
  animation: cardFadeIn 0.45s ease-out both;
}

.glass-card::before {
  position: absolute;
  inset: 0;
  z-index: 3;
  content: '';
  pointer-events: none;
  background: linear-gradient(125deg, rgba(255, 255, 255, 0.28), transparent 28%, transparent 72%, rgba(255, 255, 255, 0.1));
}

@keyframes cardFadeIn {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}

.card-header {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 28px 32px 26px;
  color: #f8fffe;
  background: linear-gradient(140deg, rgba(11, 39, 57, 0.82), rgba(13, 74, 80, 0.7));
  -webkit-backdrop-filter: blur(18px) saturate(1.25);
  backdrop-filter: blur(18px) saturate(1.25);
}

.card-header::after {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  height: 3px;
  content: '';
  background: linear-gradient(90deg, #4fd1c5, #77b9ff);
}

.brand-mark {
  display: grid;
  width: 46px;
  height: 46px;
  place-items: center;
  color: #8be8dc;
  border: 1px solid rgba(139, 232, 220, 0.4);
  border-radius: 14px;
  background: rgba(139, 232, 220, 0.1);
}

.brand-mark svg { width: 26px; height: 26px; }

.card-title {
  position: relative;
  z-index: 1;
  margin: 0;
  color: #f7fbff;
  font-size: clamp(21px, 3vw, 26px);
  font-weight: 700;
  line-height: 1.25;
  letter-spacing: 0;
  text-align: center;
}

.error-state,
.success-state,
.verify-state {
  position: relative;
  padding: 32px;
  background: rgba(255, 255, 255, 0.72);
  -webkit-backdrop-filter: blur(16px);
  backdrop-filter: blur(16px);
}

.error-state::before,
.success-state::before,
.verify-state::before {
  position: absolute;
  top: 0;
  right: 0;
  left: 0;
  height: 1px;
  content: '';
  background: var(--line);
}

.error-icon,
.success-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 18px;
  border-radius: 50%;
}

.error-icon {
  width: 64px;
  height: 64px;
  color: #c2413b;
  background: #fff3f2;
  border: 1px solid #f2c5c1;
}

.success-icon {
  width: 72px;
  height: 72px;
  color: #16846f;
  background: #ecfbf5;
  border: 1px solid #b9ead7;
}

.error-title,
.success-title {
  margin: 0 0 8px;
  color: var(--ink);
  font-size: 21px;
  font-weight: 700;
  line-height: 1.35;
  text-align: center;
}

.error-message,
.success-subtitle {
  max-width: 390px;
  margin: 0 auto;
  color: var(--muted);
  font-size: 14px;
  line-height: 1.7;
  text-align: center;
}

.success-subtitle { margin-bottom: 22px; }

.code-display {
  position: relative;
  z-index: 0;
  overflow: hidden;
  margin-bottom: 16px;
  padding: 20px 18px;
  color: #754b08;
  border: 1px solid #f2cb65;
  border-radius: 12px;
  background: #fff8df;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
  font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
  font-size: clamp(30px, 7vw, 42px);
  font-weight: 800;
  letter-spacing: clamp(4px, 1.8vw, 9px);
  line-height: 1.1;
  text-align: center;
  word-break: break-all;
}

.code-display::after {
  position: absolute;
  top: 0;
  bottom: 0;
  left: 0;
  width: 4px;
  content: '';
  background: #e6ae2f;
}

.expire-info {
  margin: 0 0 22px;
  padding: 10px 14px;
  color: #75602c;
  border: 1px solid #f1dfab;
  border-radius: 8px;
  background: #fffaf0;
  font-size: 13px;
  line-height: 1.55;
  text-align: center;
}

.steps-box {
  margin-bottom: 18px;
  padding: 15px 18px;
  color: #24546a;
  border: 1px solid #cbe7ef;
  border-radius: 11px;
  background: rgba(243, 251, 253, 0.7);
  font-size: 14px;
  font-weight: 600;
  line-height: 1.8;
  text-align: left;
  white-space: pre-line;
}

.captcha-container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 220px;
  margin-bottom: 18px;
  overflow: hidden;
  border: 1px solid #dce7ec;
  border-radius: 11px;
  background: rgba(248, 251, 252, 0.72);
  -webkit-backdrop-filter: blur(10px);
  backdrop-filter: blur(10px);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.captcha-container:hover { border-color: #8acfc8; box-shadow: 0 0 0 3px rgba(79, 209, 197, 0.11); }

.button-group {
  display: flex;
  flex-direction: row-reverse;
  gap: 10px;
}

.btn {
  flex: 1;
  min-height: 48px;
  padding: 12px 18px;
  cursor: pointer;
  border: 1px solid transparent;
  border-radius: 9px;
  font-size: 15px;
  font-weight: 700;
  line-height: 1.2;
  transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, border-color 0.2s ease;
}

.btn:focus-visible { outline: 3px solid rgba(79, 209, 197, 0.4); outline-offset: 2px; }
.btn:active:not(:disabled) { transform: translateY(1px); }

.btn-primary {
  color: #fff;
  background: var(--accent);
  box-shadow: 0 6px 14px rgba(15, 118, 110, 0.22);
}

.btn-primary:hover:not(:disabled) { background: var(--accent-strong); box-shadow: 0 8px 18px rgba(15, 118, 110, 0.3); }

.btn-secondary {
  color: #315264;
  border-color: #c9d9e1;
  background: rgba(255, 255, 255, 0.58);
  -webkit-backdrop-filter: blur(8px);
  backdrop-filter: blur(8px);
}

.btn-secondary:hover:not(:disabled) { color: #1e4555; border-color: #8fcac5; background: #f3fbfa; }

.btn:disabled { cursor: not-allowed; opacity: 0.52; }

.footer {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 2;
  padding: 18px 20px calc(18px + env(safe-area-inset-bottom));
  text-align: center;
  pointer-events: none;
}

.footer-text {
  margin: 4px 0;
  color: rgba(235, 246, 250, 0.74);
  font-size: 12px;
  line-height: 1.45;
  pointer-events: auto;
}

.footer-text a { color: #8be8dc; text-decoration: none; }
.footer-text a:hover { color: #c2fff7; text-decoration: underline; }
.icp-text { color: rgba(235, 246, 250, 0.54); }

@media (max-width: 640px) {
  .verify-container {
    align-items: flex-start;
    justify-content: flex-start;
    padding: 18px 12px calc(104px + env(safe-area-inset-bottom));
    overflow-y: auto;
  }

  .glass-card { margin: auto 0; border-radius: 16px; }
  .card-header { padding: 22px 20px 21px; }
  .brand-mark { width: 42px; height: 42px; border-radius: 12px; }
  .error-state, .success-state, .verify-state { padding: 24px 18px; }
  .captcha-container { min-height: 190px; }
  .button-group { flex-direction: column; gap: 9px; }
  .btn { flex: none; min-height: 46px; }
  .footer { padding: 13px 14px calc(13px + env(safe-area-inset-bottom)); }
}

@media (max-width: 360px) {
  .verify-container { padding-right: 8px; padding-left: 8px; }
  .error-state, .success-state, .verify-state { padding-right: 14px; padding-left: 14px; }
  .steps-box { padding: 13px 14px; font-size: 13px; }
  .code-display { padding-right: 12px; padding-left: 12px; }
}

@media (prefers-reduced-motion: reduce) {
  .background-video { display: none; }
  .glass-card { animation: none; }
  .btn { transition: none; }
}
</style>
