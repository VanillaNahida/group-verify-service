import request from '@/utils/request';

function authHeaders(token) {
  return { Authorization: 'Bearer ' + String(token || '') };
}

export function listApiKeys(token) {
  return request({
    url: '/admin/api-keys',
    method: 'get',
    headers: authHeaders(token)
  });
}

export function listApiKeysById(token, id) {
  const n = Number(id || 0);
  const params = Number.isFinite(n) && n > 0 ? { id: Math.floor(n) } : undefined;
  return request({
    url: '/admin/api-keys',
    method: 'get',
    headers: authHeaders(token),
    params
  });
}

export function createApiKey(token, value) {
  const v = typeof value === 'undefined' ? '' : String(value || '').trim();
  const data = v ? { value: v } : {};
  return request({
    url: '/admin/api-keys',
    method: 'post',
    headers: authHeaders(token),
    data
  });
}

export function deleteApiKey(token, id) {
  return request({
    url: `/admin/api-keys/${Number(id)}`,
    method: 'delete',
    headers: authHeaders(token)
  });
}

export function resetApiKey(token, id) {
  return request({
    url: `/admin/api-keys/${Number(id)}/reset`,
    method: 'post',
    headers: authHeaders(token)
  });
}
