let allStreams = [];

function streamCard(stream) {
  const tags = (stream.tags || []).slice(0, 2).map(tag => `<span>${escapeHtml(tag)}</span>`).join('');
  return `
    <article class="stream-card" data-search="${escapeHtml(`${stream.streamerName} ${stream.title} ${stream.category}`.toLowerCase())}">
      <a href="/watch.html?id=${stream.id}">
        <div class="stream-thumb theme-${escapeHtml(stream.theme || 'violet')}">
          <div class="live-pill">CANLI</div>
          <div class="viewer-pill">${formatViewers(stream.viewers)} izleyici</div>
          <div class="player-center"><div class="play-circle">▶</div></div>
        </div>
      </a>
      <div class="stream-card-body">
        <div class="avatar">${initials(stream.streamerName)}</div>
        <div class="stream-card-copy">
          <h3 title="${escapeHtml(stream.title)}">${escapeHtml(stream.title)}</h3>
          <p>${escapeHtml(stream.streamerName)}</p>
          <p>${escapeHtml(stream.category)}</p>
          <div class="tag-row">${tags}</div>
        </div>
      </div>
    </article>`;
}

function renderStreams(streams) {
  const grid = document.getElementById('streamGrid');
  if (!streams.length) {
    grid.innerHTML = '<div class="empty-state">Aramana uygun canlı yayın bulunamadı.</div>';
    return;
  }
  grid.innerHTML = streams.map(streamCard).join('');
}

function renderSidebar(streams) {
  const list = document.getElementById('channelList');
  list.innerHTML = streams.slice(0, 8).map(stream => `
    <a class="channel-item" href="/watch.html?id=${stream.id}">
      <span class="avatar">${initials(stream.streamerName)}</span>
      <span class="channel-copy"><strong>${escapeHtml(stream.streamerName)}</strong><span>${escapeHtml(stream.category)}</span></span>
      <span class="live-count">${formatViewers(stream.viewers)}</span>
    </a>
  `).join('');
}

function renderHero(stream) {
  if (!stream) return;
  const player = document.querySelector('.hero-player');
  player.className = `hero-player theme-${stream.theme || 'violet'}`;
  player.style.cursor = 'pointer';
  player.onclick = () => location.href = `/watch.html?id=${stream.id}`;
  document.getElementById('heroStreamer').textContent = stream.streamerName;
  document.getElementById('heroStreamer').href = `/watch.html?id=${stream.id}`;
  document.getElementById('heroTitle').textContent = stream.title;
  document.getElementById('heroCategory').textContent = stream.category;
  document.getElementById('heroViewers').textContent = `${formatViewers(stream.viewers)} izleyici`;
  document.querySelector('.hero-info .avatar').textContent = initials(stream.streamerName);
  document.querySelector('.hero-info .tag-row').innerHTML = stream.tags.map(tag => `<span>${escapeHtml(tag)}</span>`).join('');
}

async function initHome() {
  const grid = document.getElementById('streamGrid');
  try {
    const data = await API.request('/api/streams');
    allStreams = data.streams || [];
    renderStreams(allStreams);
    renderSidebar(allStreams);
    renderHero(allStreams[0]);
  } catch (error) {
    grid.innerHTML = `<div class="error-box">${escapeHtml(error.message)} MySQL ve migration ayarlarını kontrol edin.</div>`;
    document.getElementById('channelList').innerHTML = '';
  }
}

function applySearch() {
  const q = document.getElementById('searchInput').value.trim().toLowerCase();
  if (!q) return renderStreams(allStreams);
  renderStreams(allStreams.filter(stream => `${stream.streamerName} ${stream.title} ${stream.category} ${(stream.tags || []).join(' ')}`.toLowerCase().includes(q)));
}

document.getElementById('searchInput')?.addEventListener('input', applySearch);
document.getElementById('searchBtn')?.addEventListener('click', applySearch);
initHome();
