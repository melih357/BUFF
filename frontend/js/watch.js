async function initWatch() {
  const params = new URLSearchParams(location.search);
  const id = params.get('id') || '1';
  try {
    const data = await API.request(`/api/streams/${encodeURIComponent(id)}`);
    const stream = data.stream;
    document.title = `${stream.streamerName} • Streamora`;
    document.getElementById('watchStreamer').textContent = stream.streamerName;
    document.getElementById('watchTitle').textContent = stream.title;
    document.getElementById('watchCategory').textContent = stream.category;
    document.getElementById('watchAvatar').textContent = initials(stream.streamerName);
    document.getElementById('watchViewers').textContent = formatViewers(stream.viewers);
    document.getElementById('watchTags').innerHTML = stream.tags.map(tag => `<span>${escapeHtml(tag)}</span>`).join('');
    document.getElementById('watchPlayer').className = `watch-player theme-${stream.theme || 'violet'}`;
  } catch (error) {
    document.querySelector('.watch-main').innerHTML = `<div class="error-box" style="margin:30px">${escapeHtml(error.message)}</div>`;
  }
}

initWatch();
