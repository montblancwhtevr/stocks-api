const defaultBaseUrl = import.meta.env.VITE_API_BASE_URL || 'https://api.hiada.my.id';

export function getSettings() {
  return {
    apiBaseUrl: localStorage.getItem('apiBaseUrl') || defaultBaseUrl,
    token: localStorage.getItem('apiToken') || '',
  };
}

export function saveSettings(settings) {
  localStorage.setItem('apiBaseUrl', settings.apiBaseUrl.replace(/\/+$/, ''));
  localStorage.setItem('apiToken', settings.token);
}

export function clearToken() {
  localStorage.removeItem('apiToken');
}

export async function apiRequest(path, options = {}) {
  const { apiBaseUrl, token } = getSettings();
  const headers = {
    Accept: 'application/json',
    ...(options.body ? { 'Content-Type': 'application/json' } : {}),
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...(options.headers || {}),
  };

  const response = await fetch(`${apiBaseUrl}${path}`, {
    ...options,
    headers,
  });
  const text = await response.text();
  const data = text ? JSON.parse(text) : null;

  if (!response.ok || data?.success === false) {
    throw new Error(data?.message || `Request failed with status ${response.status}`);
  }

  return data;
}
