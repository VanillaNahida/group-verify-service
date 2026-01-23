export function createApiUrl(apiBase) {
  const base = apiBase ? String(apiBase) : '';
  return function apiUrl(path) {
    const p = String(path || '');
    if (!base) return p;
    if (base.endsWith('/') && p.startsWith('/')) return base.slice(0, -1) + p;
    if (!base.endsWith('/') && !p.startsWith('/')) return base + '/' + p;
    return base + p;
  };
}

export async function fetchJson(url, options) {
  const res = await fetch(url, options);
  let data = null;
  try {
    data = await res.json();
  } catch (e) {}
  return { res, data };
}

