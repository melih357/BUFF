const API = {
  async request(path, options = {}) {
    const token = localStorage.getItem('streamora_token');
    const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
    if (token) headers.Authorization = `Bearer ${token}`;

    const response = await fetch(path, { ...options, headers });
    let data = {};
    try { data = await response.json(); } catch (_) {}

    if (!response.ok) {
      throw new Error(data.message || 'İstek başarısız oldu.');
    }
    return data;
  }
};

function escapeHtml(value = '') {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function formatViewers(value) {
  const n = Number(value || 0);
  if (n >= 1000000) return `${(n / 1000000).toFixed(n >= 10000000 ? 0 : 1).replace('.', ',')} Mn`;
  if (n >= 1000) return `${(n / 1000).toFixed(n >= 10000 ? 0 : 1).replace('.', ',')} B`;
  return String(n);
}

function initials(name = '?') {
  return String(name).trim().slice(0, 1).toUpperCase() || '?';
}

function logout() {
  localStorage.removeItem('streamora_token');
  localStorage.removeItem('streamora_user');
  location.href = '/';
}

function renderAuthActions() {
  const el = document.getElementById('authActions');
  if (!el) return;

  const token = localStorage.getItem('streamora_token');
  const raw = localStorage.getItem('streamora_user');
  if (!token || !raw) return;

  try {
    const user = JSON.parse(raw);
    const label = escapeHtml(user.displayName || user.username || 'Hesap');
    el.innerHTML = `
      <div class="user-chip"><span class="avatar">${initials(label)}</span><span>${label}</span></div>
      <button class="btn btn-ghost" id="logoutBtn" type="button">Çıkış</button>
    `;
    document.getElementById('logoutBtn').addEventListener('click', logout);
  } catch (_) {
    localStorage.removeItem('streamora_user');
  }
}

renderAuthActions();
