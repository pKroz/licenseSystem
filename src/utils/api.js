const BASE_URL = 'http://localhost/licenseflow';

function getToken() { return localStorage.getItem('lf_token'); }
function getUser() { const u = localStorage.getItem('lf_user'); return u ? JSON.parse(u) : null; }
function setSession(token, user) { localStorage.setItem('lf_token', token); localStorage.setItem('lf_user', JSON.stringify(user)); }
function clearSession() { localStorage.removeItem('lf_token'); localStorage.removeItem('lf_user'); }

async function request(path, options = {}) {
  const token = getToken();
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers['Authorization'] = `Bearer ${token}`;
  const res = await fetch(`${BASE_URL}${path}`, { ...options, headers });
  if (res.status === 401) { clearSession(); window.location.href = '/login'; return; }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || `Error ${res.status}`);
  return data;
}

window._api = {
  login: (identifier, password) => request('/api/auth/login', { method: 'POST', body: JSON.stringify({ identifier, password }) }),
  forgotPassword: (email) => request('/api/auth/forgot-password', { method: 'POST', body: JSON.stringify({ email }) }),
  dashboard: () => request('/api/dashboard'),
  getUsers: () => request('/api/users'),
  createUser: (data) => request('/api/users', { method: 'POST', body: JSON.stringify(data) }),
  updateUser: (id, data) => request(`/api/users/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  deleteUser: (id) => request(`/api/users/${id}`, { method: 'DELETE' }),
  getClients: (search = '') => request(`/api/clients${search ? `?search=${encodeURIComponent(search)}` : ''}`),
  createClient: (data) => request('/api/clients', { method: 'POST', body: JSON.stringify(data) }),
  updateClient: (id, data) => request(`/api/clients/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  deleteClient: (id) => request(`/api/clients/${id}`, { method: 'DELETE' }),
  getProducts: () => request('/api/products'),
  createProduct: (data) => request('/api/products', { method: 'POST', body: JSON.stringify(data) }),
  updateProduct: (id, data) => request(`/api/products/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  deleteProduct: (id) => request(`/api/products/${id}`, { method: 'DELETE' }),
  getLicenses: (filters = {}) => { const p = new URLSearchParams(filters).toString(); return request(`/api/licenses${p ? `?${p}` : ''}`); },
  getLicense: (id) => request(`/api/licenses/${id}`),
  createLicense: (data) => request('/api/licenses', { method: 'POST', body: JSON.stringify(data) }),
  updateLicense: (id, data) => request(`/api/licenses/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  cancelLicense: (id) => request(`/api/licenses/${id}`, { method: 'DELETE' }),
  getAuditLogs: (filters = {}) => { const p = new URLSearchParams(filters).toString(); return request(`/api/audit${p ? `?${p}` : ''}`); },
  getToken, getUser, setSession, clearSession,
};
