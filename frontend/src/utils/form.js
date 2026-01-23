export function toFormBody(obj) {
  const params = new URLSearchParams();
  Object.keys(obj || {}).forEach((k) => params.append(k, obj[k] == null ? '' : String(obj[k])));
  return params.toString();
}

