<template>
  <div class="verify-container">
    <video class="background-video" autoplay loop muted playsinline>
      <source src="/back.webm" type="video/webm">
    </video>
    <!-- 这个虽然好看但是会造成卡顿，慎用。by：FantasyNetwork（github:Nyanyagulugulu） -->
    <!-- <div class="background-overlay"></div> -->
    
    <div class="glass-card">
      <div class="card-header">
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

let captchaObj = null;

const title = computed(() => (verified.value ? t('title_success') : t('title_default')));
const expireTip = computed(() => (expireMinutes.value ? t('expire_tip', { minutes: expireMinutes.value }) : ''));

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
  if (!ticket.value) {
    loading.value = false;
    error.value = t('invalid_link');
    return;
  }
  refreshStatus();
});
</script>

<style scoped>
.verify-container {
  width: 100%;
  min-height: 100vh;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.background-video {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: 0;
}

.background-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  z-index: 1;
}

.glass-card {
  position: relative;
  z-index: 10;
  width: 92%;
  max-width: 460px;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-radius: 24px;
  border: 2px solid rgba(255, 255, 255, 0.8);
  box-shadow: 
    0 20px 60px rgba(0, 0, 0, 0.15),
    0 8px 24px rgba(0, 0, 0, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.9),
    0 0 0 1px rgba(255, 255, 255, 0.5);
  overflow: hidden;
  animation: cardFadeIn 0.6s ease-out;
}

.glass-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: -50%;
  width: 200%;
  height: 100%;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255, 255, 255, 0.3),
    transparent
  );
  transform: skewX(-25deg);
  animation: cardShine 4s ease-in-out infinite;
  pointer-events: none;
}

@keyframes cardShine {
  0% {
    transform: translateX(-100%) skewX(-25deg);
  }
  100% {
    transform: translateX(200%) skewX(-25deg);
  }
}

@keyframes cardFadeIn {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.card-header {
  padding: 22px 32px 18px;
  background: linear-gradient(135deg, #c084fc 0%, #e879f9 50%, #f472b6 100%);
  position: relative;
  overflow: hidden;
}

.card-header::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(
    180deg,
    rgba(255, 255, 255, 0.2) 0%,
    transparent 100%
  );
  pointer-events: none;
}

.card-title {
  margin: 0;
  font-size: 24px;
  font-weight: 700;
  color: #ffffff;
  text-align: center;
  text-shadow: 
    0 2px 4px rgba(0, 0, 0, 0.15),
    0 0 20px rgba(255, 255, 255, 0.3);
  position: relative;
  z-index: 1;
}

.error-state,
.success-state,
.verify-state {
  padding: 32px;
  background: rgba(255, 255, 255, 0.98);
  position: relative;
}

.error-state::before,
.success-state::before,
.verify-state::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, #c084fc, #e879f9, #f472b6);
  background-size: 200% 100%;
  animation: gradientMove 3s linear infinite;
}

.error-icon {
  width: 60px;
  height: 60px;
  margin: 0 auto 18px;
  border-radius: 50%;
  background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
  border: 3px solid #ef4444;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 30px;
  color: #ef4444;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
}

.error-title {
  margin: 0 0 12px;
  font-size: 20px;
  font-weight: 700;
  color: #1f2937;
  text-align: center;
}

.error-message {
  margin: 0;
  font-size: 15px;
  color: #6b7280;
  text-align: center;
  line-height: 1.7;
}

.success-icon {
  width: 72px;
  height: 72px;
  margin: 0 auto 18px;
  border-radius: 50%;
  background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
  border: 3px solid #22c55e;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  color: #22c55e;
  box-shadow: 0 4px 12px rgba(34, 197, 94, 0.15);
  animation: successPulse 2s ease-in-out infinite;
}

@keyframes successPulse {
  0%, 100% {
    transform: scale(1);
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.15);
  }
  50% {
    transform: scale(1.02);
    box-shadow: 0 6px 20px rgba(34, 197, 94, 0.25);
  }
}

.success-title {
  margin: 0 0 8px;
  font-size: 22px;
  font-weight: 700;
  color: #1f2937;
  text-align: center;
}

.success-subtitle {
  margin: 0 0 20px;
  font-size: 15px;
  color: #6b7280;
  text-align: center;
  line-height: 1.7;
}

.code-display {
  background: linear-gradient(135deg, #fef9c3 0%, #fde047 50%, #facc15 100%);
  border-radius: 18px;
  padding: 28px 36px;
  margin-bottom: 24px;
  border: 3px solid #eab308;
  box-shadow: 
    0 8px 32px rgba(234, 179, 8, 0.4),
    inset 0 2px 4px rgba(255, 255, 255, 0.7),
    inset 0 -2px 4px rgba(234, 179, 8, 0.1);
  position: relative;
  overflow: hidden;
}

.code-display::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.4) 0%, transparent 70%);
  animation: codePulse 4s ease-in-out infinite;
  pointer-events: none;
}

@keyframes codePulse {
  0%, 100% {
    transform: scale(1);
    opacity: 0.6;
  }
  50% {
    transform: scale(1.2);
    opacity: 1;
  }
}

.code-display::after {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255, 255, 255, 0.6),
    transparent
  );
  animation: codeShine 3s ease-in-out infinite;
  pointer-events: none;
}

@keyframes codeShine {
  0% {
    left: -100%;
  }
  100% {
    left: 200%;
  }
}

.code-display {
  font-size: 44px;
  font-weight: 900;
  letter-spacing: 10px;
  text-align: center;
  color: #854d0e;
  font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
  text-shadow: 
    0 2px 4px rgba(255, 255, 255, 0.9),
    0 0 30px rgba(234, 179, 8, 0.3);
  position: relative;
  z-index: 1;
}

.expire-info {
  margin: 0 0 20px;
  font-size: 14px;
  color: #6b7280;
  text-align: center;
  background: #f3f4f6;
  padding: 10px 16px;
  border-radius: 8px;
}

.steps-box {
  background: linear-gradient(135deg, #fdf4ff 0%, #fae8ff 50%, #f5d0fe 100%);
  border-radius: 16px;
  padding: 22px 28px;
  margin-bottom: 24px;
  border: 2px solid #e9d5ff;
  color: #7c3aed;
  font-size: 15px;
  line-height: 1.8;
  text-align: center;
  font-weight: 600;
  box-shadow: 
    0 6px 20px rgba(192, 132, 252, 0.2),
    inset 0 2px 0 rgba(255, 255, 255, 0.9),
    inset 0 -2px 4px rgba(192, 132, 252, 0.05);
  position: relative;
  overflow: hidden;
}

.steps-box::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #c084fc, #e879f9, #f472b6);
  background-size: 200% 100%;
  animation: gradientMove 3s linear infinite;
}

.steps-box::after {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(192, 132, 252, 0.1) 0%, transparent 60%);
  animation: stepsPulse 5s ease-in-out infinite;
  pointer-events: none;
}

@keyframes stepsPulse {
  0%, 100% {
    transform: scale(1);
    opacity: 0.5;
  }
  50% {
    transform: scale(1.3);
    opacity: 1;
  }
}

.captcha-container {
  min-height: 220px;
  margin-bottom: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fafafa;
  border-radius: 12px;
  border: 2px dashed #e5e7eb;
}

.button-group {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.btn {
  width: 100%;
  padding: 16px 24px;
  border-radius: 12px;
  border: none;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
  overflow: hidden;
}

.btn:active {
  transform: scale(0.98);
}

.btn-primary {
  background: linear-gradient(135deg, #c084fc 0%, #e879f9 50%, #f472b6 100%);
  color: white;
  box-shadow: 
    0 4px 12px rgba(192, 132, 252, 0.4),
    inset 0 1px 0 rgba(255, 255, 255, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.3);
  position: relative;
  overflow: hidden;
}

.btn-primary::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(
    180deg,
    rgba(255, 255, 255, 0.2) 0%,
    transparent 100%
  );
  pointer-events: none;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 
    0 6px 20px rgba(192, 132, 252, 0.5),
    inset 0 1px 0 rgba(255, 255, 255, 0.4);
}

.btn-primary:active:not(:disabled) {
  transform: translateY(0px);
  box-shadow: 
    0 2px 8px rgba(192, 132, 252, 0.4),
    inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.btn-secondary {
  background: linear-gradient(135deg, #fdf4ff 0%, #fae8ff 100%);
  color: #7c3aed;
  border: 2px solid #e9d5ff;
  box-shadow: 0 2px 8px rgba(192, 132, 252, 0.15);
}

.btn-secondary:hover:not(:disabled) {
  background: linear-gradient(135deg, #fae8ff 0%, #f5d0fe 100%);
  transform: translateY(-2px);
  border-color: #d8b4fe;
  box-shadow: 0 4px 12px rgba(192, 132, 252, 0.25);
}

.btn-secondary:active:not(:disabled) {
  transform: translateY(0px);
  background: linear-gradient(135deg, #f5d0fe 0%, #f0abfc 100%);
}

.btn-secondary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

@media (max-width: 768px) {
  .character-left,
  .character-right {
    width: 160px;
  }
  
  .glass-card {
    width: 95%;
    border-radius: 18px;
  }
  
  .card-header {
    padding: 18px 24px 14px;
  }
  
  .card-title {
    font-size: 20px;
  }
  
  .error-state,
  .success-state,
  .verify-state {
    padding: 24px;
  }
  
  .code-display {
    font-size: 32px;
    letter-spacing: 5px;
    padding: 18px 24px;
  }
  
  .btn {
    padding: 14px 20px;
    font-size: 15px;
  }
}

@media (max-width: 480px) {
  .character-left {
    left: 1%;
    width: 100px;
  }
  
  .character-right {
    right: 1%;
    width: 100px;
  }
  
  .glass-card {
    width: 97%;
    border-radius: 16px;
  }
  
  .code-display {
    font-size: 28px;
    letter-spacing: 4px;
    padding: 16px 20px;
  }
  
  .success-icon,
  .error-icon {
    width: 52px;
    height: 52px;
    font-size: 26px;
  }
  
  .error-state,
  .success-state,
  .verify-state {
    padding: 20px;
  }
}

.footer {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 24px;
  text-align: center;
  z-index: 5;
  pointer-events: none;
  background: linear-gradient(180deg, transparent 0%, rgba(88, 28, 135, 0.3) 100%);
}

.footer::before {
  content: '';
  position: absolute;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 60%;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
}

.footer-text {
  margin: 0;
  font-size: 14px;
  color: rgba(255, 255, 255, 0.9);
  font-weight: 500;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
  letter-spacing: 1px;
  position: relative;
}

.footer-text::before {
  content: '✦';
  margin-right: 8px;
  color: rgba(255, 255, 255, 0.7);
}

.footer-text::after {
  content: '✦';
  margin-left: 8px;
  color: rgba(255, 255, 255, 0.7);
}
</style>
