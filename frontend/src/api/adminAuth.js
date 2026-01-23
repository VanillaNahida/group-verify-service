import request from '@/utils/request';

export function login(data) {
  return request({
    url: '/admin/auth/login',
    method: 'post',
    data
  });
}

