export function getStorageItem(key) {
  try {
    return localStorage.getItem(key) || '';
  } catch (e) {
    return '';
  }
}

export function setStorageItem(key, value) {
  try {
    localStorage.setItem(key, value);
  } catch (e) {}
}

export function removeStorageItem(key) {
  try {
    localStorage.removeItem(key);
  } catch (e) {}
}

export const ADMIN_TOKEN_KEY = 'admin_access_token';

export function getAdminToken() {
  return getStorageItem(ADMIN_TOKEN_KEY);
}

export function setAdminToken(value) {
  setStorageItem(ADMIN_TOKEN_KEY, String(value || ''));
}

export function clearAdminToken() {
  removeStorageItem(ADMIN_TOKEN_KEY);
}
