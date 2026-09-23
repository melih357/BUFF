(() => {
  const mode = localStorage.getItem('logi_theme_mode') || localStorage.getItem('logi_theme') || 'dark';
  const resolved = mode === 'system' ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : mode;
  document.documentElement.dataset.themeMode = mode;
  document.documentElement.dataset.theme = resolved;
  document.documentElement.dataset.accent = localStorage.getItem('logi_accent') || 'buff';
  if (localStorage.getItem('logi_reduce_motion') === '1') document.documentElement.dataset.reduceMotion = '1';
  document.documentElement.classList.add('js');
})()

window.LogiApp = (() => {
  const API_BASE = '../backend/api';

  async function api(path, options = {}) {
    const opts = {
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        ...(options.body && !(options.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
        ...(options.headers || {}),
      },
      ...options,
    };
    const response = await fetch(`${API_BASE}${path}`, opts);
    const raw = await response.text();
    let data = {};
    try { data = raw ? JSON.parse(raw) : {}; } catch (_) { if(response.ok)throw new Error('Sunucu yanıtı okunamadı. Aynı işlemi tekrar deneyin.'); data = {}; }
    if (data && typeof data.message === 'string') data.message = window.LogiI18n?.translatePhrase?.(data.message) || data.message;
    if (!response.ok) {
      const plain = raw && !data.message ? raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 500) : '';
      const error = new Error(data.message || plain || `Sunucu hatası (HTTP ${response.status}).`); error.status = response.status; throw error;
    }
    return data;
  }

  function getQuery(name) { return new URLSearchParams(window.location.search).get(name); }
  function formatNumber(value) {
    const map = { tr: 'tr-TR', en: 'en-US', de: 'de-DE', ar: 'ar', es: 'es-ES' };
    const lang = window.LogiI18n?.currentLanguage?.() || 'tr';
    return new Intl.NumberFormat(map[lang] || 'tr-TR').format(Number(value || 0));
  }
  function escapeHtml(str) { const div = document.createElement('div'); div.textContent = String(str || ''); return div.innerHTML; }
  function initials(value) { const clean = String(value || 'L').trim(); return clean ? clean.split(/\s+/).slice(0, 2).map(x => x[0] || '').join('').toUpperCase() : 'L'; }
  function avatarMarkup(userOrName, className = 'avatar-circle') {
    const user = typeof userOrName === 'object' && userOrName ? userOrName : { displayName: String(userOrName || 'BUFF'), avatarUrl: null };
    const name = user.displayName || user.streamerName || user.username || 'BUFF';
    if (user.avatarUrl) return `<span class="${className} avatar-photo"><img src="${escapeHtml(user.avatarUrl)}" alt="${escapeHtml(name)}"></span>`;
    return `<span class="${className}">${initials(name)}</span>`;
  }
  function badgeMarkup(user) {
    const badges = [];
    if (user?.isOg) badges.push('<span class="user-badge badge-og">OG</span>');
    if (user?.isVip) {
      const tier = String(user.vipTier || 'plus').toUpperCase();
      badges.push(`<span class="user-badge badge-vip badge-${escapeHtml(String(user.vipTier || 'plus'))}">${escapeHtml(tier)}</span>`);
    }
    return badges.join('');
  }
  function readableChatColor(color) {
    const raw = String(color || '#E7ECFF').toUpperCase();
    if ((document.documentElement.dataset.theme || 'light') === 'dark') return raw;
    const map = {
      '#E7ECFF':'#30343B','#7AF0C7':'#217A57','#8FC7FF':'#2B6BA6','#C9A7FF':'#6C4FA3','#FF9BCB':'#A94B72','#FFD166':'#8A5B12','#FF8B7A':'#A94E43'
    };
    return map[raw] || '#30343B';
  }

  function timeAgo(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value);
    const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
    const lang = window.LogiI18n?.currentLanguage?.() || 'tr';
    const table = {
      tr: { now:'az önce', min:n=>`${n} dk önce`, hour:n=>`${n} sa önce`, day:n=>`${n} gün önce` },
      en: { now:'just now', min:n=>`${n} min ago`, hour:n=>`${n} hr ago`, day:n=>`${n} day${n===1?'':'s'} ago` },
      de: { now:'gerade eben', min:n=>`vor ${n} Min.`, hour:n=>`vor ${n} Std.`, day:n=>`vor ${n} Tag${n===1?'':'en'}` },
      es: { now:'ahora mismo', min:n=>`hace ${n} min`, hour:n=>`hace ${n} h`, day:n=>`hace ${n} día${n===1?'':'s'}` },
      ar: { now:'الآن', min:n=>`منذ ${n} د`, hour:n=>`منذ ${n} س`, day:n=>`منذ ${n} يوم` }
    };
    const x=table[lang]||table.tr;
    if (seconds < 60) return x.now;
    if (seconds < 3600) return x.min(Math.floor(seconds / 60));
    if (seconds < 86400) return x.hour(Math.floor(seconds / 3600));
    return x.day(Math.floor(seconds / 86400));
  }

  function ensureToastRoot() {
    let root = document.getElementById('toastRoot');
    if (!root) { root = document.createElement('div'); root.id = 'toastRoot'; root.className = 'toast-root'; document.body.appendChild(root); }
    return root;
  }
  function toast(message, type = 'info', duration = 3200) {
    const root = ensureToastRoot();
    const item = document.createElement('div');
    item.className = `app-toast toast-${type}`;
    const localized = window.LogiI18n?.translatePhrase?.(message) || message;
    item.innerHTML = `<span class="toast-dot"></span><div>${escapeHtml(localized)}</div>`;
    root.appendChild(item);
    requestAnimationFrame(() => item.classList.add('show'));
    setTimeout(() => { item.classList.remove('show'); setTimeout(() => item.remove(), 260); }, duration);
  }

  async function getCurrentUser() { try { return (await api('/auth/me.php')).user; } catch (_) { return null; } }
  function closeMenus(except = null) { document.querySelectorAll('.floating-menu.open').forEach(menu => { if (menu !== except) menu.classList.remove('open'); }); }

  async function renderNotificationMenu(root) {
    if (!root) return;
    try {
      const data = await api('/notifications/list.php');
      const unread = Number(data.unread || 0);
      const iconFor = type => type === 'gift' ? '🎁' : type === 'follow' ? '✦' : type?.startsWith('refund') ? '↩' : type === 'moderator' ? '🛡' : '●';
      root.innerHTML = `<div class="floating-menu notification-menu" id="notificationMenu"><button class="icon-action" id="notificationTrigger" type="button" aria-label="Bildirimler" aria-expanded="false"><span>◔</span>${unread > 0 ? `<b>${unread > 9 ? '9+' : unread}</b>` : ''}</button><div class="floating-panel notification-panel"><div class="floating-panel-head"><strong>Bildirimler</strong><span class="notification-head-actions">${unread ? '<button id="markAllRead" type="button">Tümünü oku</button>' : ''}<button class="notification-panel-close" id="notificationCloseBtn" type="button" aria-label="Bildirimleri kapat">×</button></span></div><div class="notification-list">${(data.notifications || []).length ? data.notifications.map(item => `<a class="notification-item ${item.isRead ? '' : 'unread'}" href="${escapeHtml(item.linkUrl || './profile.php')}" data-notification-id="${item.id}"><span class="notification-symbol">${iconFor(item.type)}</span><span><strong>${escapeHtml(window.LogiI18n?.translatePhrase?.(item.title)||item.title)}</strong><small>${escapeHtml(window.LogiI18n?.translatePhrase?.(item.body)||item.body)}</small><em>${escapeHtml(timeAgo(item.createdAt))}</em></span></a>`).join('') : '<div class="menu-empty">Henüz bildirimin yok.</div>'}</div></div></div>`;
      const menu = root.querySelector('#notificationMenu');
      const trigger = root.querySelector('#notificationTrigger');
      const setOpen = open => { if (!menu) return; menu.classList.toggle('open', !!open); trigger?.setAttribute('aria-expanded', open ? 'true' : 'false'); };
      trigger?.addEventListener('click', event => { event.preventDefault(); event.stopPropagation(); const opening = !menu.classList.contains('open'); closeMenus(menu); setOpen(opening); });
      menu?.querySelector('.notification-panel')?.addEventListener('click', event => event.stopPropagation());
      root.querySelector('#notificationCloseBtn')?.addEventListener('click', event => { event.preventDefault(); event.stopPropagation(); setOpen(false); });
      root.querySelector('#markAllRead')?.addEventListener('click', async event => { event.preventDefault(); event.stopPropagation(); await api('/notifications/read.php', { method: 'POST', body: '{}' }); setOpen(false); await renderNotificationMenu(root); });
      root.querySelectorAll('[data-notification-id]').forEach(link => link.addEventListener('click', async event => {
        event.preventDefault();
        event.stopPropagation();
        const href = link.getAttribute('href') || './profile.php';
        try { await api('/notifications/read.php', { method: 'POST', body: JSON.stringify({ id: Number(link.dataset.notificationId) }) }); } catch (_) {}
        try { await renderNotificationMenu(root); } catch (_) {}
        const target = new URL(href, window.location.href);
        const samePage = target.pathname === window.location.pathname;
        if (samePage) {
          history.replaceState(null, '', target.pathname + target.search + target.hash);
          window.dispatchEvent(new HashChangeEvent('hashchange'));
          document.dispatchEvent(new CustomEvent('buff:notificationnavigate', { detail: { href: target.href, hash: target.hash, search: target.search } }));
          closeMenus();
        } else {
          window.location.assign(target.href);
        }
      }));
    } catch (_) { root.innerHTML = ''; }
  }

  let lastNotificationRealtimeId = 0;
  window.addEventListener('buff:realtime', event => {
    const msg = event.detail || {};
    if (msg.event !== 'notification.created') return;
    const payload = msg.payload || {};
    const nid = Number(payload.id || msg.id || 0);
    if (nid && nid === lastNotificationRealtimeId) return;
    if (nid) lastNotificationRealtimeId = nid;
    const root = document.getElementById('notificationActions');
    if (root) renderNotificationMenu(root);
    const text = payload.body || payload.title || 'Yeni bildirimin var.';
    toast(text, 'info', 5200);
    document.dispatchEvent(new CustomEvent('buff:notificationcreated', { detail: payload }));
  });


  async function saveLanguage(lang, user) {
    window.LogiI18n?.setLanguage(lang);
    if (user) api('/profile/language.php', { method: 'POST', body: JSON.stringify({ language: lang }) }).catch(() => {});
  }

  function resolveTheme(mode) {
    return mode === 'system' ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : mode;
  }

  let preferencesReady = false;
  function applyUserPreferences(user) {
    if (!user || preferencesReady) return;
    preferencesReady = true;
    const mode = user.themeMode || localStorage.getItem('logi_theme_mode') || 'dark';
    const accent = user.accentTheme || localStorage.getItem('logi_accent') || 'buff';
    document.documentElement.dataset.themeMode = mode;
    document.documentElement.dataset.theme = resolveTheme(mode);
    document.documentElement.dataset.accent = accent;
    document.documentElement.dataset.reduceMotion = user.reduceMotion ? '1' : '0';
    localStorage.setItem('logi_theme_mode', mode);
    localStorage.setItem('logi_accent', accent);
    localStorage.setItem('logi_reduce_motion', user.reduceMotion ? '1' : '0');
    localStorage.setItem('logi_sidebar_collapsed', user.sidebarCollapsed ? '1' : '0');
    if (document.body) document.body.classList.toggle('sidebar-collapsed', !!user.sidebarCollapsed);
  }

  function themeToggle() {
    const theme = document.documentElement.dataset.theme || 'dark';
    return `<button class="theme-toggle" id="globalThemeToggle" type="button" aria-label="Tema değiştir" title="${theme === 'dark' ? 'Açık moda geç' : 'Koyu moda geç'}"><span>${theme === 'dark' ? '☀' : '☾'}</span></button>`;
  }

  function bindThemeToggle() {
    const btn = document.getElementById('globalThemeToggle');
    if (!btn) return;
    btn.addEventListener('click', async () => {
      const next = (document.documentElement.dataset.theme || 'dark') === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = next;
      localStorage.setItem('logi_theme', next);
      localStorage.setItem('logi_theme_mode', next);
      document.documentElement.dataset.themeMode = next;
      btn.innerHTML = `<span>${next === 'dark' ? '☀' : '☾'}</span>`;
      btn.title = next === 'dark' ? 'Açık moda geç' : 'Koyu moda geç';
      try {
        const me = await getCurrentUser();
        if (me) await api('/settings/preferences.php',{method:'POST',body:JSON.stringify({themeMode:next,accentTheme:me.accentTheme||'buff',sidebarCollapsed:!!me.sidebarCollapsed,defaultStreamDelay:me.defaultStreamDelay||5,reduceMotion:!!me.reduceMotion})});
      } catch (_) {}
    });
  }

  function languageSelect(user = null) {
    const current = window.LogiI18n?.currentLanguage?.() || user?.preferredLanguage || 'tr';
    return `<label class="language-select-wrap" title="Dil"><span>🌐</span><select id="globalLanguageSelect"><option value="tr" ${current === 'tr' ? 'selected' : ''}>TR</option><option value="en" ${current === 'en' ? 'selected' : ''}>EN</option><option value="de" ${current === 'de' ? 'selected' : ''}>DE</option><option value="ar" ${current === 'ar' ? 'selected' : ''}>AR</option><option value="es" ${current === 'es' ? 'selected' : ''}>ES</option></select></label>`;
  }

  async function renderAuthActions() {
    const root = document.getElementById('authActions');
    if (!root) return null;
    const user = await getCurrentUser();
    if (user && user.preferredLanguage && !localStorage.getItem('logi_language')) window.LogiI18n?.setLanguage(user.preferredLanguage);
    if (user) applyUserPreferences(user);

    if (user) {
      root.innerHTML = `${themeToggle()}${languageSelect(user)}<div id="notificationActions"></div><div class="floating-menu wallet-menu" id="walletMenu"><button class="wallet-pill" id="walletTrigger" type="button" aria-label="Coin ve üyelik menüsü"><span>◈</span><strong>${formatNumber(user.walletBalance)}</strong><small>coin</small></button><div class="floating-panel wallet-panel"><div class="floating-panel-head"><strong>Cüzdan</strong><span>${formatNumber(user.walletBalance)} coin</span></div><a href="./payment.php"><span>◈</span><div><strong>Coin Satın Al</strong><small>Kart ile güvenli ödeme</small></div></a><a href="./settings.php#membership"><span>✦</span><div><strong>Plus / Pro / Premium</strong><small>Üyelik paketlerini görüntüle</small></div></a><a href="./settings.php#payments"><span>₺</span><div><strong>Ödeme Ayarları</strong><small>Kart, iletişim ve yayıncı çekimi</small></div></a></div></div><div class="floating-menu account-menu" id="accountMenu"><button class="account-trigger" id="accountTrigger" type="button">${avatarMarkup(user, 'avatar-circle')}<span class="account-name"><strong>${escapeHtml(user.displayName)} ${badgeMarkup(user)}</strong><small>@${escapeHtml(user.username)}</small></span><span class="chevron">⌄</span></button><div class="floating-panel account-panel"><div class="account-summary">${avatarMarkup(user, 'avatar-circle large')}<div><strong>${escapeHtml(user.displayName)} ${badgeMarkup(user)}</strong><small>@${escapeHtml(user.username)} • ${escapeHtml(user.publicId || '')}</small></div></div><div class="account-metrics"><div><span>Coin</span><strong>${formatNumber(user.walletBalance)}</strong></div><div><span>Yayıncı kazancı</span><strong>${formatNumber(user.creatorEarnings || 0)}</strong></div></div><div class="account-mode-inline"><span>Hesap modu</span><button type="button" id="accountModeSwitch" class="mode-switch-btn">${user.accountMode === 'creator' ? 'Yayıncı' : 'İzleyici'} ⇄</button></div><a href="./profile.php"><span>👤</span> Profilim</a><a href="./messages.php"><span>💬</span> Mesajlar</a>${user.accountMode === 'creator' ? '<a href="./channel.php"><span>▤</span> Kanalım</a>' : ''}<a href="./settings.php#membership"><span>✦</span> Plus / Pro / Premium</a><a href="./settings.php#payments"><span>₺</span> Kazanç & Para Çekme</a><a href="./profile.php#gifts"><span>🎁</span> Gelen hediyeler</a>${user.accountMode === 'creator' ? '<a href="./studio.php"><span>●</span> Yayın stüdyosu</a>' : ''}${user.isAdmin ? '<a href="./admin.php"><span>🛡</span> Admin Paneli</a>' : ''}<a href="./settings.php"><span>⚙</span> Ayarlar</a><button type="button" id="logoutBtn"><span>↪</span> Çıkış yap</button></div></div>`;
      await renderNotificationMenu(document.getElementById('notificationActions'));
      const menu = document.getElementById('accountMenu');
      const walletMenu = document.getElementById('walletMenu');
      const walletTrigger = document.getElementById('walletTrigger');
      walletTrigger?.addEventListener('click', event => { event.preventDefault(); event.stopPropagation(); const opening = !walletMenu.classList.contains('open'); closeMenus(); walletMenu.classList.toggle('open', opening); });
      walletTrigger?.addEventListener('dblclick', event => { event.preventDefault(); event.stopPropagation(); });
      document.getElementById('accountTrigger')?.addEventListener('click', event => { event.stopPropagation(); const opening = !menu.classList.contains('open'); closeMenus(); menu.classList.toggle('open', opening); });
      document.getElementById('logoutBtn')?.addEventListener('click', async () => { await api('/auth/logout.php', { method: 'POST' }); window.location.href = './index.php'; });
      document.getElementById('accountModeSwitch')?.addEventListener('click', async () => { const next=user.accountMode==='creator'?'viewer':'creator'; try { const res=await api('/account/mode.php',{method:'POST',body:JSON.stringify({mode:next})}); toast(res.message,'success'); setTimeout(()=>window.location.reload(),250); } catch(e){ toast(e.message,'error'); } });
      document.querySelectorAll('a[href="./studio.php"]').forEach(a=>{ if(user.accountMode!=='creator') a.classList.add('creator-only-hidden'); });
    } else {
      root.innerHTML = `${themeToggle()}${languageSelect()}<a class="btn btn-ghost" href="./login.php" data-i18n="login">Giriş Yap</a><a class="btn btn-primary" href="./register.php" data-i18n="register">Kayıt Ol</a>`;
    }
    document.getElementById('globalLanguageSelect')?.addEventListener('change', e => saveLanguage(e.target.value, user));
    bindThemeToggle();
    window.LogiI18n?.apply(root);
    return user;
  }

  function requireLoggedIn(user) { if (!user) { const next = encodeURIComponent(window.location.href); window.location.href = `./login.php?next=${next}`; return false; } return true; }
  async function copyText(text) { try { await navigator.clipboard.writeText(text); toast('Bağlantı panoya kopyalandı.', 'success'); } catch (_) { toast('Bağlantı kopyalanamadı.', 'error'); } }

  function openModal(id) { const modal = document.getElementById(id); if (modal) { modal.classList.add('open'); document.body.classList.add('modal-open'); } }
  function closeModal(id) { const modal = document.getElementById(id); if (modal) { modal.classList.remove('open'); modal.querySelectorAll('video,audio').forEach(v=>v.pause()); document.body.classList.toggle('modal-open',!!document.querySelector('.app-modal.open')); } }
  function initModals(){document.addEventListener('click',e=>{const open=e.target.closest('[data-open-modal]'),close=e.target.closest('[data-close-modal]');if(open)openModal(open.dataset.openModal);if(close)closeModal(close.dataset.closeModal);if(e.target.matches('.app-modal')&&!e.target.hasAttribute('data-static-modal'))closeModal(e.target.id);});document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.app-modal.open:not([data-static-modal])').forEach(m=>closeModal(m.id));});}

  function initBankPurchaseModal() {
    const form = document.getElementById('bankPurchaseForm');
    if (!form) return;
    const msg = document.getElementById('bankPurchaseMessage');
    form.addEventListener('submit', async e => {
      e.preventDefault();
      try {
        const data = Object.fromEntries(new FormData(form).entries());
        data.coinAmount = Number(data.coinAmount);
        const result = await api('/payments/bank-request.php', { method: 'POST', body: JSON.stringify(data) });
        msg.className = 'form-message success';
        msg.innerHTML = `Talep oluşturuldu. Referans: <strong>${escapeHtml(result.request.referenceCode)}</strong>. Banka/ödeme sağlayıcısı doğrulaması sonrası coin yüklenir.`;
        toast('Banka transferi talebi oluşturuldu.', 'success');
      } catch (error) {
        msg.className = 'form-message error'; msg.textContent = error.message;
      }
    });
  }

  function initSidebarToggle(){const sidebar=document.querySelector('.app-sidebar');if(!sidebar)return;const collapsed=localStorage.getItem('logi_sidebar_collapsed')==='1';document.body.classList.toggle('sidebar-collapsed',collapsed);document.getElementById('sidebarCollapseBtn')?.setAttribute('aria-expanded',String(!collapsed));}

  function initPageTransitions() {
    requestAnimationFrame(() => document.body.classList.add('page-ready'));
    document.addEventListener('click', e => {
      const link = e.target.closest('a[href]');
      if (!link || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || link.target === '_blank') return;
      const href = link.getAttribute('href');
      if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) return;
      let url;
      try { url = new URL(link.href, window.location.href); } catch (_) { return; }
      if (url.origin !== window.location.origin) return;
      if (document.documentElement.dataset.reduceMotion === '1') return;
      e.preventDefault();
      document.body.classList.add('page-leaving');
      setTimeout(() => { window.location.href = url.href; }, 140);
    });
  }

  document.addEventListener('click', event => { if (!event.target.closest('.floating-menu')) closeMenus(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenus(); });
  window.addEventListener('blur', () => closeMenus());
  window.addEventListener('pagehide', () => closeMenus());
  document.addEventListener('DOMContentLoaded', () => { initModals(); initBankPurchaseModal(); initSidebarToggle(); initPageTransitions(); document.querySelectorAll('[data-language-select]').forEach(sel => { sel.value = window.LogiI18n?.currentLanguage?.() || 'tr'; sel.addEventListener('change', e => window.LogiI18n?.setLanguage(e.target.value)); }); });

  return { api, getQuery, formatNumber, escapeHtml, initials, avatarMarkup, badgeMarkup, readableChatColor, timeAgo, toast, getCurrentUser, renderAuthActions, requireLoggedIn, copyText, openModal, closeModal, applyUserPreferences };
})();

document.addEventListener('DOMContentLoaded', () => window.LogiApp.renderAuthActions());

// V10 unified Kick-style navigation + server-backed preferences
window.addEventListener('DOMContentLoaded', async () => {
  try {
    const me = await LogiApp.getCurrentUser();
    const page = document.body?.dataset?.page || '';
    if (me && page !== 'admin-v13') LogiApp.applyUserPreferences(me);
    if (!document.querySelector('.app-sidebar') && !['login','register','payment'].includes(page) && document.body?.classList.contains('app-body')) {
      const sidebar = document.createElement('aside');
      const isWatch = page === 'watch';
      sidebar.className = `app-sidebar kick-sidebar-v10 ${isWatch ? 'kick-watch-rail' : 'kick-full-sidebar'}`;
      if (isWatch) {
        sidebar.innerHTML = `
          <div class="watch-rail-links">
            <a href="./index.php" title="Ana Sayfa">⌂</a>
            <a href="./discover.php" title="Gözat">▣</a>
            <a href="./discover.php#followingSection" title="Takip">♡</a>
            ${me?.accountMode==='creator'?'<a href="./studio.php" title="Creator Studio">●</a>':''}
          </div>
          <div class="watch-rail-divider"></div>
          <div id="globalWatchRailChannels" class="watch-rail-channels"></div>`;
      } else {
        sidebar.innerHTML = `
          <div class="sidebar-main-links">
            <a class="sidebar-nav ${page==='feed'?'active':''}" href="./index.php"><span>⌂</span><div><strong>Ana Sayfa</strong></div></a>
            <a class="sidebar-nav ${page==='home'?'active':''}" href="./discover.php"><span>▣</span><div><strong>Gözat</strong></div></a>
            <a class="sidebar-nav" href="./moments.php"><span>◈</span><div><strong>Anlar</strong></div></a>
            <a class="sidebar-nav ${page==='messages'?'active':''}" href="./messages.php"><span>💬</span><div><strong>Mesajlar</strong></div></a>
            <a class="sidebar-nav" href="./discover.php#followingSection"><span>♡</span><div><strong>Takip Edilen Kanallar</strong></div></a>
            ${me?.accountMode==='creator'?'<a class="sidebar-nav" href="./studio.php"><span>●</span><div><strong>Creator Studio</strong></div></a>':''}
            <a class="sidebar-nav" href="./profile.php"><span>◎</span><div><strong>Profil</strong></div></a>
            <a class="sidebar-nav" href="./settings.php#membership"><span>✦</span><div><strong>Üyelik</strong></div></a>
            <a class="sidebar-nav" href="./settings.php"><span>⚙</span><div><strong>Ayarlar</strong></div></a>
          </div>
          <div class="sidebar-block"><div class="sidebar-title"><span>ÖNERİLEN KANALLAR</span></div><div id="globalRecommendedChannels" class="channel-list"><div class="sidebar-empty">Kanallar yükleniyor...</div></div></div>
          <div class="sidebar-block"><div class="sidebar-title"><span>KATEGORİLER</span></div><div class="sidebar-category-list"><a class="sidebar-category-link" href="./discover.php?q=Sohbet"><span>💬</span><span>Sohbet</span></a><a class="sidebar-category-link" href="./discover.php?q=Oyun"><span>🎮</span><span>Oyun</span></a><a class="sidebar-category-link" href="./discover.php?q=Müzik"><span>♫</span><span>Müzik</span></a><a class="sidebar-category-link" href="./discover.php?q=E-spor"><span>🏆</span><span>E-spor</span></a></div></div>`;
      }
      document.body.appendChild(sidebar);
      document.body.classList.add('has-v10-sidebar');
      try {
        const discover = await LogiApp.api('/discover.php');
        const live = discover.live || [];
        if (isWatch) {
          const root = document.getElementById('globalWatchRailChannels');
          if (root) root.innerHTML = live.slice(0,10).map(s => `<a href="./watch.php?id=${s.id}" title="${LogiApp.escapeHtml(s.streamerName)}">${LogiApp.avatarMarkup({displayName:s.streamerName,avatarUrl:s.avatarUrl},'watch-rail-avatar')}</a>`).join('');
        } else {
          const root = document.getElementById('globalRecommendedChannels');
          if (root) root.innerHTML = live.length ? live.slice(0,10).map(s => `<a class="sidebar-channel" href="./watch.php?id=${s.id}">${LogiApp.avatarMarkup({displayName:s.streamerName,avatarUrl:s.avatarUrl},'sidebar-avatar')}<span class="sidebar-channel-copy"><strong>${LogiApp.escapeHtml(s.streamerName)}</strong><small>${LogiApp.escapeHtml(s.category)}</small></span><span class="sidebar-viewers"><i class="online-dot"></i>${LogiApp.formatNumber(s.viewerCount)}</span></a>`).join('') : '<div class="sidebar-empty">Şu anda canlı kanal yok.</div>';
        }
      } catch (_) {}
    }
    if (me?.accountMode !== 'creator') document.querySelectorAll('a[href="./studio.php"]').forEach(a=>a.classList.add('creator-only-hidden'));
  } catch (_) {}
});
