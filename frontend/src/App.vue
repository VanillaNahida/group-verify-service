<template>
  <a-config-provider :locale="arcoLocale">
    <div class="page">
      <template v-if="adminMode">
        <AdminLoginView v-if="adminRoute === 'login'" :on-login-success="goAdminHome" />
        <AdminView v-else :on-logout="goAdminLogin" />
      </template>
      <VerifyView v-else :t="t" />
    </div>
  </a-config-provider>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminLoginView from './view/Admin/Login/index.vue';
import AdminView from './view/Admin/index.vue';
import VerifyView from './view/Verify/index.vue';
import { isAdminMode } from './utils/url';
import { createTranslator, getArcoLocale, getInitialLocale, setLocale } from './i18n';

const locale = ref(setLocale(getInitialLocale()));
const arcoLocale = computed(() => getArcoLocale(locale.value));
const t = createTranslator(() => locale.value);

function updateLocale(nextLocale) {
  locale.value = setLocale(nextLocale);
  return locale.value;
}

const adminMode = ref(false);
const currentPath = ref('');

function readPath() {
  currentPath.value = window.location.pathname || '';
}

function navigateTo(path) {
  const next = String(path || '/');
  if ((window.location.pathname || '') === next) return;
  window.history.pushState({}, '', next);
  readPath();
}

const adminRoute = computed(() => {
  const p = currentPath.value || '';
  if (p === '/admin/login' || p === '/admin/login/') return 'login';
  return 'home';
});

function goAdminLogin() {
  navigateTo('/admin/login');
}

function goAdminHome() {
  navigateTo('/admin');
}

onMounted(() => {
  adminMode.value = isAdminMode();
  readPath();
  window.addEventListener('popstate', readPath);
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
</style>
