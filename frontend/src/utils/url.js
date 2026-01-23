export function isAdminMode() {
  const path = window.location.pathname || '';
  const hash = window.location.hash || '';
  return path.startsWith('/admin') || hash.startsWith('#/admin');
}

export function parseTicketFromLocation() {
  const search = window.location.search || '';
  const sp = new URLSearchParams(search);
  const q = sp.get('ticket');
  if (q) return q;

  const path = window.location.pathname || '';
  const m1 = path.match(/\/v\/([^/?#]+)/);
  if (m1 && m1[1]) return decodeURIComponent(m1[1]);

  const hash = window.location.hash || '';
  const m2 = hash.match(/#\/v\/([^/?#]+)/);
  return m2 && m2[1] ? decodeURIComponent(m2[1]) : '';
}

