import { createApiUrl, fetchJson } from './api';

const apiBase = (import.meta && import.meta.env && import.meta.env.VITE_API_BASE) ? String(import.meta.env.VITE_API_BASE) : '';
const apiUrl = createApiUrl(apiBase);

function isPlainObject(v) {
  return Object.prototype.toString.call(v) === '[object Object]';
}

function normalizeMethod(method) {
  const m = String(method || 'get').toLowerCase();
  return m === 'post' || m === 'put' || m === 'patch' || m === 'delete' ? m : 'get';
}

function buildQuery(params) {
  if (!params || !isPlainObject(params)) return '';
  const sp = new URLSearchParams();
  Object.keys(params).forEach((k) => {
    const v = params[k];
    if (typeof v === 'undefined' || v === null) return;
    if (Array.isArray(v)) {
      v.forEach((it) => {
        if (typeof it === 'undefined' || it === null) return;
        sp.append(k, String(it));
      });
      return;
    }
    sp.set(k, String(v));
  });
  const s = sp.toString();
  return s ? `?${s}` : '';
}

export default function request(config) {
  const cfg = config || {};
  const method = normalizeMethod(cfg.method);
  const headers = Object.assign({}, cfg.headers || {});
  const base = apiUrl(String(cfg.url || ''));
  const query = buildQuery(cfg.params);
  const fullUrl = query ? (base.includes('?') ? `${base}&${query.slice(1)}` : `${base}${query}`) : base;

  const options = { method: method.toUpperCase(), headers };

  if (method !== 'get') {
    const data = cfg.data;
    if (typeof data !== 'undefined') {
      if (data instanceof FormData || typeof data === 'string') {
        options.body = data;
      } else if (isPlainObject(data) || Array.isArray(data)) {
        if (!('Content-Type' in headers) && !('content-type' in headers)) {
          options.headers = Object.assign({}, headers, { 'Content-Type': 'application/json' });
        }
        const ct = String((options.headers && (options.headers['Content-Type'] || options.headers['content-type'])) || '');
        if (ct.includes('application/x-www-form-urlencoded')) {
          const sp = new URLSearchParams();
          Object.keys(data || {}).forEach((k) => {
            const v = data[k];
            if (typeof v === 'undefined' || v === null) return;
            if (Array.isArray(v)) {
              v.forEach((it) => {
                if (typeof it === 'undefined' || it === null) return;
                sp.append(k, String(it));
              });
              return;
            }
            sp.set(k, String(v));
          });
          options.body = sp.toString();
        } else {
          options.body = JSON.stringify(data);
        }
      } else {
        options.body = String(data);
      }
    }
  }

  return fetchJson(fullUrl, options);
}

