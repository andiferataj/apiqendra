let csrf = '';
let workspace = '';
export function setWorkspace(id: string) { workspace = id; }
export function setCsrf(token: string) { csrf = token; }
export class ApiError extends Error { constructor(message: string, public status: number) { super(message); } }
export async function api<T>(path: string, options: RequestInit = {}, retry = true): Promise<T> {
 const headers = new Headers(options.headers);
 headers.set('Accept', 'application/json');
 if (options.body) headers.set('Content-Type', 'application/json');
 if (csrf) headers.set('X-CSRF-TOKEN', csrf);
 if (workspace) headers.set('X-Workspace-ID', workspace);
 const response = await fetch('/api/v1' + path, { ...options, credentials: 'same-origin', headers });
 if (response.status === 419 && retry) {
  const session = await api<{ csrf_token: string }>('/session', {}, false); setCsrf(session.csrf_token);
  return api<T>(path, options, false);
 }
 if (response.status === 401 && !['/login','/register','/session'].includes(path)) window.dispatchEvent(new Event('apiqendra-session-expired'));
 const body = await response.json().catch(() => ({ message: 'The server returned an unreadable response.' }));
 if (!response.ok) {
  const errors = body.errors as Record<string, string[]> | undefined;
  throw new ApiError(errors ? Object.values(errors).flat().join(' ') : body.message ?? 'Request failed.', response.status);
 }
 return body as T;
}
export const mutate = <T,>(path: string, body: unknown = {}, method = 'POST') => api<T>(path, { method, body: JSON.stringify(body) });
export async function downloadCsv(range: string) {
 const response = await fetch('/api/v1/deliveries/export?range=' + range, { credentials: 'same-origin', headers: { 'X-Workspace-ID': workspace, Accept: 'text/csv' } });
 if (!response.ok) throw new Error('Export failed. Check your workspace access.');
 const url = URL.createObjectURL(await response.blob()), a = document.createElement('a');
 a.href = url; a.download = 'apiqendra-deliveries.csv'; a.click(); URL.revokeObjectURL(url);
}
