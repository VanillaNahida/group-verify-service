<template>
  <a-card title="管理后台" class="card">
    <a-spin :loading="submitting" style="width: 100%">
      <a-result v-if="errorText" status="error" title="加载失败" :subtitle="errorText" />
      <template v-else>
        <a-space direction="vertical" size="medium" fill>
          <a-alert
            type="info"
            :show-icon="true"
            title="登录说明"
          >输入默认 API_KEY 登录。</a-alert>
          <a-form layout="vertical">
            <a-form-item label="API_KEY">
              <a-input-password v-model="credential" placeholder="请输入默认 API_KEY" allow-clear />
            </a-form-item>
          </a-form>
          <a-button type="primary" long :loading="submitting" @click="onLogin">登录</a-button>
        </a-space>
      </template>
    </a-spin>
  </a-card>
</template>

<script setup>
import { computed, ref } from 'vue';
import { getDashboard } from '../../../api/adminSettings';
import { setAdminToken } from '../../../utils/storage';

const props = defineProps({
  onLoginSuccess: { type: Function, default: null }
});

const credential = ref('');
const isSubmitting = ref(false);
const error = ref('');

function setToken(nextToken) {
  const t = nextToken ? String(nextToken) : '';
  setAdminToken(t);
}

async function verifyApiKey(apiKey) {
  error.value = '';
  isSubmitting.value = true;
  try {
    const token = String(apiKey || '').trim();
    if (!token) {
      error.value = '请输入 API_KEY';
      return false;
    }

    const { data } = await getDashboard(token);

    if (data && data.code === 0) {
      setToken(token);
      return true;
    }

    error.value = (data && data.msg) || '登录失败';
    return false;
  } catch (e) {
    error.value = '网络异常，请稍后重试';
    return false;
  } finally {
    isSubmitting.value = false;
  }
}

const submitting = computed(() => isSubmitting.value);
const errorText = computed(() => error.value || '');

async function onLogin() {
  const raw = String(credential.value || '').trim();
  if (!raw) return;

  const ok = await verifyApiKey(raw);
  if (!ok) return;
  credential.value = '';
  if (props.onLoginSuccess) props.onLoginSuccess();
}
</script>

<style scoped>
.card {
  width: 100%;
  max-width: 720px;
}
</style>
