<template>
  <a-layout class="layout">
    <a-layout-header class="header">
      <a-space align="center" justify="space-between" fill>
        <a-space align="center">
          <a-typography-title :heading="5" style="margin: 0">管理后台</a-typography-title>
          <a-tag v-if="apiBaseText" color="blue">API：{{ apiBaseText }}</a-tag>
          <a-tag color="green">已登录</a-tag>
        </a-space>
        <a-button status="danger" :disabled="submitting" @click="onLogout">退出</a-button>
      </a-space>
    </a-layout-header>

    <a-layout>
      <a-layout-sider class="sider" :width="200" breakpoint="lg" collapsible>
        <a-menu :selected-keys="[activeKey]" @menu-item-click="onMenuClick">
          <a-menu-item key="overview">概览</a-menu-item>
          <a-menu-item key="apiKeys">API Key 管理</a-menu-item>
          <a-menu-item key="settings">配置管理</a-menu-item>
        </a-menu>
      </a-layout-sider>

      <a-layout-content class="content">
        <a-result v-if="errorText" status="error" title="加载失败" :subtitle="errorText" />
        <template v-else>
          <a-card v-if="activeKey === 'overview'" title="概览" class="card">
            <a-space direction="vertical" size="medium" fill>
              <a-alert
                type="info"
                :show-icon="true"
                title="提示"
              >从左侧进入“API Key 管理 / 配置管理”维护密钥与验证码配置。</a-alert>

              <a-space align="center" justify="space-between" fill>
                <a-space>
                  <a-button :loading="dashboardSubmitting" @click="loadDashboard">刷新概览</a-button>
                </a-space>
                <a-typography-text type="secondary">
                  {{ dashboardUpdatedAtText ? `更新时间：${dashboardUpdatedAtText}` : '' }}
                </a-typography-text>
              </a-space>

              <a-result v-if="dashboardError" status="error" title="概览加载失败" :subtitle="dashboardError" />

              <template v-else>
                <a-grid :cols="3" :col-gap="16" :row-gap="16">
                  <a-card title="API Key 数量" :loading="dashboardSubmitting">
                    <a-typography-title :heading="4" style="margin: 0">{{ dashboard.api_keys_total }}</a-typography-title>
                  </a-card>
                  <a-card title="验证票据总数" :loading="dashboardSubmitting">
                    <a-typography-title :heading="4" style="margin: 0">{{ dashboard.tickets_total }}</a-typography-title>
                  </a-card>
                  <a-card title="24h API 调用" :loading="dashboardSubmitting">
                    <a-typography-title :heading="4" style="margin: 0">{{ dashboard.calls_24h_total }}</a-typography-title>
                    <a-typography-text type="secondary">错误：{{ dashboard.calls_24h_error }}</a-typography-text>
                  </a-card>
                </a-grid>

                <a-grid :cols="2" :col-gap="16" :row-gap="16">
                  <a-card title="24h Endpoint Top" :loading="dashboardSubmitting">
                    <a-table
                      :columns="endpointColumns"
                      :data="dashboard.calls_24h_by_endpoint"
                      :pagination="false"
                      row-key="endpoint"
                      size="small"
                    />
                  </a-card>
                  <a-card title="24h 群 Top" :loading="dashboardSubmitting">
                    <a-table
                      :columns="groupColumns"
                      :data="dashboard.calls_24h_top_groups"
                      :pagination="false"
                      row-key="group_id"
                      size="small"
                    />
                  </a-card>
                </a-grid>

                <a-card title="最近 20 条调用" :loading="dashboardSubmitting">
                  <a-table
                    :columns="recentColumns"
                    :data="dashboard.recent_calls"
                    :pagination="false"
                    row-key="id"
                    size="small"
                  >
                    <template #createdAt="{ record }">
                      <span>{{ formatTime(record && record.created_at) }}</span>
                    </template>
                  </a-table>
                </a-card>
              </template>
            </a-space>
          </a-card>

          <a-card v-else-if="activeKey === 'apiKeys'" title="API Key 管理" class="card">
            <AdminApiKeysView :token="tokenText" :on-unauthorized="onUnauthorized" />
          </a-card>

          <a-card v-else title="配置管理" class="card">
            <AdminSettingsView :token="tokenText" :on-unauthorized="onUnauthorized" />
          </a-card>
        </template>
      </a-layout-content>
    </a-layout>
  </a-layout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Message } from '@arco-design/web-vue';
import { createApiUrl } from '../../utils/api';
import { clearAdminToken, getAdminToken, setAdminToken } from '../../utils/storage';
import { getDashboard } from '../../api/adminSettings';
import AdminSettingsView from './Settings/index.vue';
import AdminApiKeysView from './ApiKeys/index.vue';

const props = defineProps({
  onLogout: { type: Function, default: null }
});

const token = ref(getAdminToken());
const isAuthed = computed(() => !!token.value);
const authSubmitting = ref(false);
const authError = ref('');

function setToken(nextToken) {
  const t = nextToken ? String(nextToken) : '';
  token.value = t;
  if (!t) clearAdminToken();
  else setAdminToken(t);
}

function logout() {
  setToken('');
  authError.value = '';
}

const apiBase = (import.meta && import.meta.env && import.meta.env.VITE_API_BASE) ? String(import.meta.env.VITE_API_BASE) : '';
const apiUrl = createApiUrl(apiBase);

const submitting = computed(() => authSubmitting.value);
const errorText = computed(() => (authError.value ? authError.value : ''));
const tokenText = computed(() => String(token.value || ''));

const apiBaseText = computed(() => {
  try {
    const url = apiUrl('/_ping_');
    if (!url) return '';
    if (url.startsWith('/')) return '';
    const u = new URL(url);
    return u.origin;
  } catch (e) {
    return '';
  }
});

const activeKey = ref('overview');

const dashboardSubmitting = ref(false);
const dashboardError = ref('');
const dashboardUpdatedAt = ref(0);
const dashboardUpdatedAtText = computed(() => formatTime(dashboardUpdatedAt.value));
const dashboard = ref({
  now: 0,
  api_keys_total: 0,
  tickets_total: 0,
  tickets_verified_total: 0,
  tickets_used_total: 0,
  tickets_pending: 0,
  tickets_expired_total: 0,
  calls_24h_total: 0,
  calls_24h_error: 0,
  calls_24h_by_endpoint: [],
  calls_24h_top_groups: [],
  recent_calls: []
});

const endpointColumns = [
  { title: 'Endpoint', dataIndex: 'endpoint' },
  { title: '次数', dataIndex: 'count', width: 90 }
];

const groupColumns = [
  { title: '群号', dataIndex: 'group_id' },
  { title: '次数', dataIndex: 'count', width: 90 }
];

const recentColumns = [
  { title: '时间', width: 180, slotName: 'createdAt' },
  { title: 'Endpoint', dataIndex: 'endpoint', width: 180 },
  { title: '方法', dataIndex: 'method', width: 90 },
  { title: '状态', dataIndex: 'status_code', width: 90 },
  { title: '群号', dataIndex: 'group_id', width: 120 },
  { title: '用户', dataIndex: 'user_id', width: 120 },
  { title: 'KeyID', dataIndex: 'api_key_id', width: 90 },
  { title: '耗时(ms)', dataIndex: 'duration_ms', width: 110 }
];

function formatTime(ts) {
  const n = Number(ts || 0);
  if (!n) return '';
  try {
    return new Date(n * 1000).toLocaleString('zh-CN', { hour12: false });
  } catch (e) {
    return String(n);
  }
}

async function loadDashboard() {
  dashboardError.value = '';
  dashboardSubmitting.value = true;
  try {
    const token = tokenText.value ? String(tokenText.value) : '';
    if (!token) {
      onUnauthorized();
      dashboardError.value = '登录已过期，请重新登录';
      return false;
    }
    const { data } = await getDashboard(token);
    if (data && data.code === 0 && data.data) {
      dashboard.value = {
        ...dashboard.value,
        ...data.data
      };
      dashboardUpdatedAt.value = Number(data.data.now || 0) || Math.floor(Date.now() / 1000);
      return true;
    }
    if (data && data.code === 401) {
      onUnauthorized();
      dashboardError.value = '登录已过期，请重新登录';
      return false;
    }
    dashboardError.value = (data && data.msg) || '加载失败';
    return false;
  } catch (e) {
    dashboardError.value = '网络异常，请稍后重试';
    Message.error(dashboardError.value);
    return false;
  } finally {
    dashboardSubmitting.value = false;
  }
}

function readActiveKeyFromLocation() {
  try {
    const u = new URL(window.location.href);
    const k = String(u.searchParams.get('page') || '');
    if (k === 'settings') return 'settings';
    if (k === 'apiKeys') return 'apiKeys';
    return 'overview';
  } catch (e) {
    return 'overview';
  }
}

function writeActiveKeyToLocation(nextKey) {
  try {
    const u = new URL(window.location.href);
    if (nextKey === 'settings') u.searchParams.set('page', 'settings');
    else if (nextKey === 'apiKeys') u.searchParams.set('page', 'apiKeys');
    else u.searchParams.delete('page');
    window.history.pushState({}, '', u.pathname + u.search + u.hash);
  } catch (e) {}
}

function onMenuClick(key) {
  const nextKey = key === 'settings' ? 'settings' : key === 'apiKeys' ? 'apiKeys' : 'overview';
  activeKey.value = nextKey;
  writeActiveKeyToLocation(nextKey);
}

function onUnauthorized() {
  logout();
  if (props.onLogout) props.onLogout();
}

function onLogout() {
  logout();
  if (props.onLogout) props.onLogout();
}

onMounted(() => {
  if (isAuthed.value) {
    activeKey.value = readActiveKeyFromLocation();
    window.addEventListener('popstate', () => {
      activeKey.value = readActiveKeyFromLocation();
    });
    if (activeKey.value === 'overview') {
      loadDashboard();
    }
    return;
  }
  if (props.onLogout) props.onLogout();
});

watch(activeKey, (k) => {
  if (k === 'overview' && isAuthed.value) {
    loadDashboard();
  }
});
</script>

<style scoped>
.layout {
  width: 100%;
  height: 100vh;
  overflow: hidden;
  background: var(--color-bg-1);
}

.header {
  background: var(--color-bg-1);
  border-bottom: 1px solid var(--color-border-2);
  padding: 12px 16px;
  height: auto;
  line-height: normal;
}

.sider {
  background: var(--color-bg-1);
  border-right: 1px solid var(--color-border-2);
}

.content {
  padding: 16px;
  background: var(--color-bg-1);
}

.card {
  width: 100%;
}
</style>
