document.addEventListener('DOMContentLoaded', async () => {
  const heroStage = document.getElementById('heroStage');
  const heroRail = document.getElementById('heroRail');
  const grid = document.getElementById('streamGrid');
  const followingGrid = document.getElementById('followingGrid');
  const risingGrid = document.getElementById('risingGrid');
  const supportedGrid = document.getElementById('supportedGrid');
  const categoryGrid = document.getElementById('categoryGrid');
  const sidebar = document.getElementById('sidebarLiveChannels');
  const followingSidebar = document.getElementById('followingChannels');
  const searchInput = document.getElementById('searchInput');
  const filters = document.getElementById('discoveryFilters');
  let data = { live: [], following: [], rising: [], supported: [], categories: [] };
  let activeFilter = 'all';
  const initialQuery = new URLSearchParams(location.search).get('q') || '';
  if (searchInput && initialQuery) searchInput.value = initialQuery;

  const palette = ['violet', 'cyan', 'rose', 'amber', 'emerald', 'blue'];
  const color = i => palette[i % palette.length];
  const streamHaystack = s => [s.title, s.streamerName, s.username, s.category, ...(s.tags || [])].join(' ').toLocaleLowerCase('tr-TR');

  function streamCard(stream, index = 0, compact = false) {
    return `
      <article class="pro-stream-card ${compact ? 'compact-card' : ''}">
        <a class="pro-thumb thumb-${color(index)}" href="./watch.php?id=${stream.id}">
          <div class="thumb-glow"></div>
          <span class="live-label">CANLI</span>
          ${stream.avatarUrl ? `<span class="thumb-avatar thumb-avatar-photo"><img src="${LogiApp.escapeHtml(stream.avatarUrl)}" alt="${LogiApp.escapeHtml(stream.streamerName)}"></span>` : `<span class="thumb-avatar">${LogiApp.initials(stream.streamerName)}</span>`}
          <span class="thumb-play">▶</span>
          <span class="thumb-viewers">◉ ${LogiApp.formatNumber(stream.viewerCount)}</span>
          ${stream.giftTotal ? `<span class="thumb-support">🎁 ${LogiApp.formatNumber(stream.giftTotal)}</span>` : ''}
        </a>
        <div class="stream-card-info">
          ${stream.avatarUrl ? `<span class="creator-avatar avatar-photo"><img src="${LogiApp.escapeHtml(stream.avatarUrl)}" alt="${LogiApp.escapeHtml(stream.streamerName)}"></span>` : `<span class="creator-avatar avatar-${color(index)}">${LogiApp.initials(stream.streamerName)}</span>`}
          <div class="stream-copy">
            <a class="stream-title" href="./watch.php?id=${stream.id}">${LogiApp.escapeHtml(stream.title)}</a>
            <a class="creator-name" href="./watch.php?id=${stream.id}">${LogiApp.escapeHtml(stream.streamerName)}</a>
            <div class="stream-subline"><span>${LogiApp.escapeHtml(stream.category)}</span><span>•</span><span>${LogiApp.formatNumber(stream.followerCount || 0)} takipçi</span></div>
            <div class="mini-tags">${(stream.tags || []).slice(0, 3).map(t => `<span>${LogiApp.escapeHtml(t)}</span>`).join('')}</div>
          </div>
          <button class="card-more" type="button">•••</button>
        </div>
      </article>`;
  }

  function horizontal(items, emptyText) {
    if (!items.length) return `<div class="empty-strip">${emptyText}</div>`;
    return items.slice(0, 8).map((s, i) => streamCard(s, i, true)).join('');
  }

  function renderSidebar(items, root, countEl) {
    if (countEl) countEl.textContent = String(items.length);
    if (!items.length) {
      root.innerHTML = '<div class="sidebar-empty">Şu anda aktif kanal yok.</div>';
      return;
    }
    root.innerHTML = items.slice(0, 10).map((s, i) => `
      <a class="sidebar-channel" href="./watch.php?id=${s.id}">
        ${s.avatarUrl ? `<span class="sidebar-avatar avatar-photo"><img src="${LogiApp.escapeHtml(s.avatarUrl)}" alt="${LogiApp.escapeHtml(s.streamerName)}"><i></i></span>` : `<span class="sidebar-avatar avatar-${color(i)}">${LogiApp.initials(s.streamerName)}<i></i></span>`}
        <span class="sidebar-channel-copy"><strong>${LogiApp.escapeHtml(s.streamerName)}</strong><small>${LogiApp.escapeHtml(s.category)}</small></span>
        <span class="sidebar-viewers">${LogiApp.formatNumber(s.viewerCount)}</span>
      </a>`).join('');
  }

  function renderHero(items) {
    const top = items[0];
    if (!top) {
      heroStage.innerHTML = `<div class="hero-empty"><img src="./assets/img/buff-logo.png" alt="BUFF"><div><span class="section-kicker">BUFF LIVE</span><h1>Topluluğun ilk yayınını bekliyor</h1><p>Kameranı aç, yayını başlat ve keşfet sayfasında yerini al.</p><a class="btn btn-primary" href="./studio.php">Yayın Başlat</a></div></div>`;
      heroRail.innerHTML = '';
      return;
    }
    heroStage.innerHTML = `
      <a class="hero-feature hero-${color(0)}" href="./watch.php?id=${top.id}">
        <div class="hero-backdrop"><span>${LogiApp.initials(top.streamerName)}</span></div>
        <div class="hero-overlay"></div>
        <div class="hero-live"><i></i> CANLI</div>
        <div class="hero-content">
          <div class="hero-creator"><span class="creator-avatar avatar-${color(0)}">${LogiApp.initials(top.streamerName)}</span><div><strong>${LogiApp.escapeHtml(top.streamerName)}</strong><small>@${LogiApp.escapeHtml(top.username)}</small></div></div>
          <h1>${LogiApp.escapeHtml(top.title)}</h1>
          <p>${LogiApp.escapeHtml(top.description || 'Canlı yayına katıl ve topluluğun parçası ol.')}</p>
          <div class="hero-meta"><span>${LogiApp.escapeHtml(top.category)}</span><span>◉ ${LogiApp.formatNumber(top.viewerCount)} izleyici</span><span>🎁 ${LogiApp.formatNumber(top.giftTotal)} destek</span></div>
          <span class="hero-watch-btn">▶ Yayını İzle</span>
        </div>
      </a>`;
    heroRail.innerHTML = items.slice(1, 5).map((s, i) => `
      <a class="rail-card rail-${color(i + 1)}" href="./watch.php?id=${s.id}">
        <span class="rail-avatar">${LogiApp.initials(s.streamerName)}</span>
        <span class="rail-copy"><strong>${LogiApp.escapeHtml(s.streamerName)}</strong><small>${LogiApp.escapeHtml(s.title)}</small><em>${LogiApp.formatNumber(s.viewerCount)} izleyici</em></span>
      </a>`).join('');
  }

  function renderCategories(categories) {
    const defaults = [
      ['Sohbet', '💬'], ['Oyun', '🎮'], ['Müzik', '🎵'], ['E-spor', '🏆'], ['Sanat', '🎨'], ['Mobil', '📱']
    ];
    const items = categories.length ? categories.map(c => [c.name, '✦', c.liveCount]) : defaults.map(x => [x[0], x[1], 0]);
    categoryGrid.innerHTML = items.slice(0, 10).map((c, i) => `
      <button class="category-pro cat-${color(i)}" data-category="${LogiApp.escapeHtml(c[0])}">
        <span class="category-art">${c[1]}</span><span class="category-copy"><strong>${LogiApp.escapeHtml(c[0])}</strong><small>${LogiApp.formatNumber(c[2] || 0)} canlı yayın</small></span><span class="category-arrow">↗</span>
      </button>`).join('');
    categoryGrid.querySelectorAll('[data-category]').forEach(btn => btn.addEventListener('click', () => {
      searchInput.value = btn.dataset.category;
      applyFilters();
      document.getElementById('liveStreams').scrollIntoView({ behavior: 'smooth' });
    }));
  }

  function selectedBase() {
    if (activeFilter === 'trend') return [...data.live].sort((a, b) => (b.viewerCount + b.giftTotal / 20) - (a.viewerCount + a.giftTotal / 20));
    if (activeFilter === 'new') return data.rising;
    if (activeFilter === 'support') return data.supported;
    if (activeFilter === 'following') return data.following;
    return data.live;
  }

  function applyFilters() {
    const term = (searchInput.value || '').trim().toLocaleLowerCase('tr-TR');
    let items = selectedBase();
    if (term) items = items.filter(s => streamHaystack(s).includes(term));
    document.getElementById('resultCount').textContent = `${LogiApp.formatNumber(items.length)} sonuç`;
    grid.innerHTML = items.length ? items.map((s, i) => streamCard(s, i)).join('') : `<div class="pro-empty"><span>⌕</span><h3>Uygun yayın bulunamadı</h3><p>Arama veya filtreyi değiştirerek tekrar deneyebilirsin.</p></div>`;
  }

  grid.innerHTML = '<div class="pro-empty"><div class="loading-orbit"></div><p>Keşfet hazırlanıyor...</p></div>';
  try {
    data = await LogiApp.api('/discover.php');
    renderHero(data.live || []);
    renderSidebar(data.live || [], sidebar, document.getElementById('liveCount'));
    renderSidebar(data.following || [], followingSidebar, document.getElementById('followingLiveCount'));
    followingGrid.innerHTML = horizontal(data.following || [], 'Takip ettiğin canlı yayıncı bulunmuyor. Yayın izlerken Takip Et butonunu kullanabilirsin.');
    risingGrid.innerHTML = horizontal(data.rising || [], 'Henüz yeni yayın yok.');
    supportedGrid.innerHTML = horizontal(data.supported || [], 'Desteklenen canlı yayın henüz yok.');
    renderCategories(data.categories || []);
    applyFilters();
  } catch (error) {
    heroStage.innerHTML = `<div class="pro-empty"><h3>Keşfet yüklenemedi</h3><p>${LogiApp.escapeHtml(error.message)}</p><a class="btn btn-primary" href="../install.php">Kurulumu Güncelle</a></div>`;
    grid.innerHTML = '';
  }

  filters?.querySelectorAll('[data-filter]').forEach(btn => btn.addEventListener('click', () => {
    filters.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    activeFilter = btn.dataset.filter;
    applyFilters();
  }));
  searchInput?.addEventListener('input', applyFilters);
  document.querySelectorAll('[data-side-category]').forEach(link => link.addEventListener('click', e => {
    e.preventDefault();
    searchInput.value = link.dataset.sideCategory || '';
    applyFilters();
    document.getElementById('liveStreams')?.scrollIntoView({behavior:'smooth'});
  }));

  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      searchInput.focus();
    }
  });
});
