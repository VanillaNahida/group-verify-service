import request from '@/utils/request';

export function getStatus(ticket) {
  return request({
    url: '/verify/status/' + encodeURIComponent(String(ticket || '')),
    method: 'get'
  });
}

export function submitCallback(formBody) {
  return request({
    url: '/verify/callback',
    method: 'post',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    data: String(formBody || '')
  });
}

export function resetKey(apiKey) {
  return request({
    url: '/verify/reset-key',
    method: 'post',
    headers: { Authorization: 'Bearer ' + String(apiKey || '') }
  });
}
