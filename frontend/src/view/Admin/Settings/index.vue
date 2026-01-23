<template>
  <a-space direction="vertical" size="medium" fill>
    <a-alert
      type="info"
      :show-icon="true"
    >只填写需要修改的项，留空不会覆盖已有配置。</a-alert>

    <a-result v-if="errorText" status="error" title="加载失败" :subtitle="errorText" />

    <template v-else>
      <a-tabs default-active-key="geetest" type="rounded">
        <a-tab-pane key="geetest" title="验证码配置">
          <a-form layout="vertical">
            <a-form-item v-for="item in geetestItems" :key="item.key" :label="getFieldName(item)">
              <template v-if="isSecretKey(item.key)">
                <a-input-password v-model="edits[item.key]" :placeholder="item.masked || ''" allow-clear />
              </template>
              <template v-else>
                <a-input v-model="edits[item.key]" :placeholder="String(item.value || '')" allow-clear />
              </template>
              <template #help>
                {{ getFieldHelpLine(item) }}
              </template>
            </a-form-item>
          </a-form>
        </a-tab-pane>

        <a-tab-pane key="security" title="安全与密钥">
          <a-form layout="vertical">
            <a-form-item v-for="item in securityItems" :key="item.key" :label="getFieldName(item)">
              <a-input-password v-model="edits[item.key]" :placeholder="item.masked || ''" allow-clear />
              <template #help>
                {{ getFieldHelpLine(item) }}
              </template>
            </a-form-item>
          </a-form>
        </a-tab-pane>
      </a-tabs>

      <a-space direction="vertical" size="medium" fill>
        <a-space>
          <a-button :loading="submitting" @click="loadSettings">刷新</a-button>
          <a-button type="primary" :loading="submitting" :disabled="!hasChanges" @click="onSave">保存更改</a-button>
        </a-space>
      </a-space>
    </template>
  </a-space>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Message } from '@arco-design/web-vue';
import { getSettings, updateSettings } from '../../../api/adminSettings';

const props = defineProps({
  token: { type: String, default: '' },
  onUnauthorized: { type: Function, default: null }
});

const tokenRef = computed(() => String(props.token || ''));

const settings = ref([]);
const settingsSubmitting = ref(false);
const settingsError = ref('');

function resetSettings() {
  settings.value = [];
  settingsError.value = '';
}

function handleUnauthorized() {
  resetSettings();
  if (props.onUnauthorized) props.onUnauthorized();
}

async function loadSettings() {
  settingsError.value = '';
  settingsSubmitting.value = true;
  try {
    const token = tokenRef.value ? String(tokenRef.value) : '';
    if (!token) return false;
    const { data } = await getSettings(token);

    if (data && data.code === 0 && data.data && Array.isArray(data.data.items)) {
      settings.value = data.data.items;
      return true;
    }

    if (data && data.code === 401) {
      handleUnauthorized();
      settingsError.value = '登录已过期，请重新登录';
      return false;
    }

    settingsError.value = (data && data.msg) || '加载失败';
    return false;
  } catch (e) {
    settingsError.value = '网络异常，请稍后重试';
    return false;
  } finally {
    settingsSubmitting.value = false;
  }
}

async function saveSettings(values) {
  settingsError.value = '';
  settingsSubmitting.value = true;
  try {
    const token = tokenRef.value ? String(tokenRef.value) : '';
    if (!token) {
      settingsError.value = '请先登录';
      return { ok: false, unauthorized: true };
    }

    const { data } = await updateSettings(token, values || {});

    if (data && data.code === 0 && data.data && Array.isArray(data.data.items)) {
      settings.value = data.data.items;
      return { ok: true, unauthorized: false };
    }

    if (data && data.code === 401) {
      handleUnauthorized();
      settingsError.value = '登录已过期，请重新登录';
      return { ok: false, unauthorized: true };
    }

    settingsError.value = (data && data.msg) || '保存失败';
    return { ok: false, unauthorized: false };
  } catch (e) {
    settingsError.value = '网络异常，请稍后重试';
    return { ok: false, unauthorized: false };
  } finally {
    settingsSubmitting.value = false;
  }
}

const submitting = computed(() => settingsSubmitting.value);
const errorText = computed(() => settingsError.value || '');

const edits = ref({});

const fieldMeta = {
  GEETEST_CAPTCHA_ID: {
    name: '极验 CAPTCHA ID',
    help: '用于前端初始化极验验证码，从极验后台获取。'
  },
  GEETEST_CAPTCHA_KEY: {
    name: '极验 CAPTCHA KEY',
    help: '用于服务端校验的密钥，务必妥善保管。'
  },
  API_KEY: {
    name: '管理后台 API_KEY',
    help: '用于登录管理后台；支持多个密钥，用逗号/空格/换行分隔；每个密钥建议至少 16 位。'
  },
  SALT: {
    name: '系统 SALT',
    help: '用于安全相关的签名/校验；修改可能影响历史数据的校验结果。'
  }
};

function isSecretKey(key) {
  return ['GEETEST_CAPTCHA_KEY', 'API_KEY', 'SALT'].includes(String(key || ''));
}

function getFieldName(item) {
  const key = String(item && item.key ? item.key : '');
  const meta = fieldMeta[key];
  if (meta && meta.name) return meta.name;
  const label = item && item.label ? String(item.label) : '';
  return label || key || '未知字段';
}

function getFieldHelpLine(item) {
  const key = String(item && item.key ? item.key : '');
  const meta = fieldMeta[key];
  const baseHelp = meta && meta.help ? meta.help : '只填写需要修改的项，留空不会覆盖已有配置。';
  const source = item && item.source ? String(item.source) : '-';
  const isSetText = item && item.is_set ? '已设置' : '未设置';
  return `${baseHelp}（字段：${key || '-'}，来源：${source}，${isSetText}）`;
}

const geetestItems = computed(() => {
  return (settings.value || []).filter((it) => String(it.key || '').startsWith('GEETEST_'));
});

const securityItems = computed(() => {
  return (settings.value || []).filter((it) => !String(it.key || '').startsWith('GEETEST_'));
});

function resetEdits(nextSettings) {
  const next = {};
  (nextSettings || []).forEach((it) => {
    next[String(it.key)] = '';
  });
  edits.value = next;
}

watch(
  () => settings.value,
  (next) => resetEdits(next),
  { immediate: true }
);

const hasChanges = computed(() => {
  const m = edits.value || {};
  return Object.keys(m).some((k) => String(m[k] || '').trim() !== '');
});

async function onSave() {
  const values = {};
  Object.keys(edits.value || {}).forEach((k) => {
    const v = String(edits.value[k] || '').trim();
    if (v !== '') values[k] = v;
  });
  const res = await saveSettings(values);
  if (res.ok) {
    Message.success('保存成功');
    resetEdits(settings.value);
  }
}

onMounted(() => {
  if (!tokenRef.value) return;
  loadSettings();
});
</script>
