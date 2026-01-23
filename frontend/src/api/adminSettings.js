import request from '@/utils/request';

export function getSettings(token) {
  return request({
    url: '/admin/settings',
    method: 'get',
    headers: { Authorization: 'Bearer ' + String(token || '') }
  });
}

export function updateSettings(token, values) {
  return request({
    url: '/admin/settings',
    method: 'put',
    headers: { Authorization: 'Bearer ' + String(token || '') },
    data: { values: values || {} }
  });
}

export function getDashboard(token) {
  return request({
    url: '/admin/dashboard',
    method: 'get',
    headers: { Authorization: 'Bearer ' + String(token || '') }
  });
}

export function listApiCallLogs(token, params) {
  return request({
    url: '/admin/api-call-logs',
    method: 'get',
    headers: { Authorization: 'Bearer ' + String(token || '') },
    params: params || {}
  });
}
