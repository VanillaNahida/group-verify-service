<template>
  <a-space direction="vertical" size="medium" fill>
    <a-alert
      type="warning"
      :show-icon="true"
    >列表仅显示脱敏值；新建/重置密钥只会在弹窗里展示一次，请及时保存。</a-alert>

    <a-space align="center" justify="space-between" fill>
      <a-space>
        <a-button :loading="submitting" @click="onRefresh">刷新</a-button>
        <a-button type="primary" :loading="submitting" @click="openCreate('random')">新增随机密钥</a-button>
        <a-button :loading="submitting" @click="openCreate('manual')">新增自定义密钥</a-button>
        <a-input
          v-model="searchId"
          placeholder="按 ID 查询"
          allow-clear
          style="width: 160px"
          @press-enter="onSearch"
        />
        <a-button :loading="submitting" @click="onSearch">查询</a-button>
        <a-button :loading="submitting" :disabled="!lastQueryId" @click="onClearSearch">清空</a-button>
      </a-space>
      <a-typography-text type="secondary">
        共 {{ (items || []).length }} 个{{ lastQueryId ? `（ID：${lastQueryId}）` : '' }}
      </a-typography-text>
    </a-space>

    <a-result v-if="errorText" status="error" title="加载失败" :subtitle="errorText" />

    <a-table
      v-else
      :columns="columns"
      :data="items"
      :loading="submitting"
      :pagination="false"
      row-key="id"
      size="medium"
    >
      <template #type="{ record }">
        <span>{{ record && record.is_default ? '当前使用' : '普通' }}</span>
      </template>
      <template #createdAt="{ record }">
        <span>{{ formatTime(record && record.created_at) }}</span>
      </template>
      <template #updatedAt="{ record }">
        <span>{{ formatTime(record && record.updated_at) }}</span>
      </template>
      <template #actions="{ record }">
        <a-space>
          <a-button
            status="warning"
            size="mini"
            :loading="submitting"
            @click="onReset(record)"
          >
            重置
          </a-button>
          <a-button
            status="danger"
            size="mini"
            :loading="submitting"
            :disabled="record && record.is_default"
            @click="onDelete(record)"
          >
            删除
          </a-button>
        </a-space>
      </template>
    </a-table>

    <a-modal
      v-model:visible="createVisible"
      :title="createMode === 'manual' ? '新增自定义密钥' : '新增随机密钥'"
      :mask-closable="false"
      :ok-loading="submitting"
      @ok="onCreate"
    >
      <a-space direction="vertical" size="medium" fill>
        <a-form v-if="createMode === 'manual'" layout="vertical">
          <a-form-item label="密钥">
            <a-input-password v-model="manualValue" placeholder="至少 16 位，建议随机强口令" allow-clear />
          </a-form-item>
        </a-form>
        <a-typography-paragraph v-else style="margin: 0">
          将自动生成一个高强度随机密钥。
        </a-typography-paragraph>
      </a-space>
    </a-modal>

    <a-modal v-model:visible="createdVisible" :title="createdTitle" :mask-closable="false" :footer="false">
      <a-space direction="vertical" size="medium" fill>
        <a-typography-paragraph style="margin: 0">
          {{ createdTip }}
        </a-typography-paragraph>
        <a-typography-text v-if="createdId" type="secondary">ID：{{ createdId }}</a-typography-text>
        <a-input v-model="createdValue" readonly />
        <a-space>
          <a-button type="primary" @click="copyCreated">复制</a-button>
          <a-button @click="closeCreated">关闭</a-button>
        </a-space>
      </a-space>
    </a-modal>
  </a-space>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Message, Modal } from '@arco-design/web-vue';
import { createApiKey, deleteApiKey, listApiKeys, listApiKeysById, resetApiKey } from '../../../api/adminApiKeys';

const props = defineProps({
  token: { type: String, default: '' },
  onUnauthorized: { type: Function, default: null }
});

const tokenRef = computed(() => String(props.token || ''));

const submitting = ref(false);
const errorText = ref('');
const items = ref([]);
const searchId = ref('');
const lastQueryId = ref(0);

const createVisible = ref(false);
const createMode = ref('random');
const manualValue = ref('');

const createdVisible = ref(false);
const createdValue = ref('');
const createdId = ref(0);
const createdKind = ref('create');
const createdTitle = computed(() => (createdKind.value === 'reset' ? '密钥重置成功' : '密钥创建成功'));
const createdTip = computed(() =>
  createdKind.value === 'reset'
    ? '下面是重置后的新密钥原文（仅展示一次），请立即复制保存：'
    : '下面是新建密钥原文（仅展示一次），请立即复制保存：'
);

const columns = [
  { title: 'ID', dataIndex: 'id', width: 90 },
  { title: '密钥', width: 180, dataIndex: 'masked' },
  { title: '类型', width: 120, slotName: 'type' },
  { title: '创建时间', width: 180, slotName: 'createdAt' },
  { title: '更新时间', width: 180, slotName: 'updatedAt' },
  { title: '操作', width: 200, slotName: 'actions' }
];

function formatTime(ts) {
  const n = Number(ts || 0);
  if (!n) return '-';
  try {
    return new Date(n * 1000).toLocaleString('zh-CN', { hour12: false });
  } catch (e) {
    return String(n);
  }
}

async function loadItems() {
  errorText.value = '';
  submitting.value = true;
  try {
    const token = tokenRef.value ? String(tokenRef.value) : '';
    if (!token) {
      if (props.onUnauthorized) props.onUnauthorized();
      errorText.value = '登录已过期，请重新登录';
      return false;
    }

    const qid = Number(lastQueryId.value || 0);
    const { data } = qid > 0 ? await listApiKeysById(token, qid) : await listApiKeys(token);
    if (data && data.code === 0 && data.data && Array.isArray(data.data.items)) {
      items.value = data.data.items;
      return true;
    }
    if (data && data.code === 401) {
      if (props.onUnauthorized) props.onUnauthorized();
      errorText.value = '登录已过期，请重新登录';
      return false;
    }
    errorText.value = (data && data.msg) || '加载失败';
    return false;
  } catch (e) {
    errorText.value = '网络异常，请稍后重试';
    return false;
  } finally {
    submitting.value = false;
  }
}

function normalizeSearchId(raw) {
  const s = String(raw || '').trim();
  if (!s) return 0;
  if (!/^\d+$/.test(s)) return -1;
  const n = Number(s);
  if (!Number.isFinite(n) || n <= 0) return -1;
  return Math.floor(n);
}

async function onSearch() {
  const id = normalizeSearchId(searchId.value);
  if (id === -1) {
    Message.error('请输入正确的 ID');
    return;
  }
  lastQueryId.value = id;
  await loadItems();
}

async function onClearSearch() {
  searchId.value = '';
  lastQueryId.value = 0;
  await loadItems();
}

async function onRefresh() {
  await loadItems();
}

function openCreate(mode) {
  createMode.value = mode === 'manual' ? 'manual' : 'random';
  manualValue.value = '';
  createVisible.value = true;
}

async function onCreate() {
  submitting.value = true;
  try {
    const token = tokenRef.value ? String(tokenRef.value) : '';
    if (!token) {
      if (props.onUnauthorized) props.onUnauthorized();
      Message.error('登录已过期，请重新登录');
      createVisible.value = false;
      return;
    }

    const v = createMode.value === 'manual' ? String(manualValue.value || '').trim() : undefined;
    const { data } = await createApiKey(token, v ? v : undefined);

    if (data && data.code === 0 && data.data && data.data.value) {
      createVisible.value = false;
      createdKind.value = 'create';
      createdId.value = Number(data.data.id || 0) || 0;
      createdValue.value = String(data.data.value);
      createdVisible.value = true;
      await loadItems();
      return;
    }

    if (data && data.code === 401) {
      if (props.onUnauthorized) props.onUnauthorized();
      Message.error('登录已过期，请重新登录');
      createVisible.value = false;
      return;
    }

    Message.error((data && data.msg) || '创建失败');
  } catch (e) {
    Message.error('网络异常，请稍后重试');
  } finally {
    submitting.value = false;
  }
}

function closeCreated() {
  createdVisible.value = false;
  createdValue.value = '';
  createdKind.value = 'create';
  createdId.value = 0;
}

async function copyCreated() {
  const v = String(createdValue.value || '');
  if (!v) return;
  try {
    await navigator.clipboard.writeText(v);
    Message.success('已复制');
  } catch (e) {
    Message.error('复制失败，请手动复制');
  }
}

function onDelete(record) {
  const id = record && record.id ? Number(record.id) : 0;
  if (!id) return;
  if (record && record.is_default) return;

  Modal.confirm({
    title: '确认删除',
    content: `删除后该密钥将立即失效（ID：${id}）。`,
    okText: '删除',
    cancelText: '取消',
    okButtonProps: { status: 'danger' },
    onOk: async () => {
      submitting.value = true;
      try {
        const token = tokenRef.value ? String(tokenRef.value) : '';
        if (!token) {
          if (props.onUnauthorized) props.onUnauthorized();
          Message.error('登录已过期，请重新登录');
          return;
        }

        const { data } = await deleteApiKey(token, id);
        if (data && data.code === 0) {
          Message.success('删除成功');
          await loadItems();
          return;
        }
        if (data && data.code === 401) {
          if (props.onUnauthorized) props.onUnauthorized();
          Message.error('登录已过期，请重新登录');
          return;
        }
        Message.error((data && data.msg) || '删除失败');
      } catch (e) {
        Message.error('网络异常，请稍后重试');
      } finally {
        submitting.value = false;
      }
    }
  });
}

function onReset(record) {
  const id = record && record.id ? Number(record.id) : 0;
  if (!id) return;

  Modal.confirm({
    title: '确认重置',
    content: `将替换该密钥的值，旧密钥将立即失效（ID：${id}）。`,
    okText: '重置',
    cancelText: '取消',
    okButtonProps: { status: 'warning' },
    onOk: async () => {
      submitting.value = true;
      try {
        const token = tokenRef.value ? String(tokenRef.value) : '';
        if (!token) {
          if (props.onUnauthorized) props.onUnauthorized();
          Message.error('登录已过期，请重新登录');
          return;
        }

        const { data } = await resetApiKey(token, id);
        if (data && data.code === 0 && data.data && data.data.value) {
          const returnedId = Number(data.data.id || 0) || 0;
          if (returnedId && returnedId !== id) {
            Message.error(`重置接口返回的 ID 异常：期望 ${id}，实际 ${returnedId}`);
            return;
          }
          createdKind.value = 'reset';
          createdId.value = id;
          createdValue.value = String(data.data.value);
          createdVisible.value = true;
          Message.success('重置成功');
          await loadItems();
          return;
        }
        if (data && data.code === 401) {
          if (props.onUnauthorized) props.onUnauthorized();
          Message.error('登录已过期，请重新登录');
          return;
        }
        Message.error((data && data.msg) || '重置失败');
      } catch (e) {
        Message.error('网络异常，请稍后重试');
      } finally {
        submitting.value = false;
      }
    }
  });
}

watch(
  () => tokenRef.value,
  (t) => {
    if (t) loadItems();
    else items.value = [];
  },
  { immediate: true }
);

watch(
  () => searchId.value,
  (v) => {
    const s = String(v || '').trim();
    if (s !== '') return;
    if (!lastQueryId.value) return;
    lastQueryId.value = 0;
    loadItems();
  }
);

onMounted(() => {
  if (!tokenRef.value) return;
  loadItems();
});
</script>
