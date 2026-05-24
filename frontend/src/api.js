export const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL || 'https://api.hiada.my.id').replace(/\/+$/, '');
const TOKEN_STORAGE_KEY = 'warehouse_stock_token';
const USER_STORAGE_KEY = 'warehouse_stock_user';

export function getStoredSession() {
  const token = localStorage.getItem(TOKEN_STORAGE_KEY) || '';
  const username = localStorage.getItem(USER_STORAGE_KEY) || '';

  return { token, username };
}

export function saveSession(token, username) {
  localStorage.setItem(TOKEN_STORAGE_KEY, token);
  localStorage.setItem(USER_STORAGE_KEY, username);
}

export function clearSession() {
  localStorage.removeItem(TOKEN_STORAGE_KEY);
  localStorage.removeItem(USER_STORAGE_KEY);
}

export async function loginRequest(username, password) {
  return apiRequest('/api/auth/login', {
    method: 'POST',
    skipAuth: true,
    body: JSON.stringify({ username, password }),
  });
}

export async function apiRequest(path, options = {}) {
  const token = options.skipAuth ? '' : getStoredSession().token;
  const headers = {
    Accept: 'application/json',
    ...(options.body ? { 'Content-Type': 'application/json' } : {}),
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...(options.headers || {}),
  };

  const { skipAuth, ...requestOptions } = options;
  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...requestOptions,
    headers,
  });
  const text = await response.text();
  const data = text ? JSON.parse(text) : null;

  if (!response.ok || data?.success === false) {
    const error = new Error(data?.message || `Request failed with status ${response.status}`);
    error.status = response.status;
    throw error;
  }

  return data;
}
