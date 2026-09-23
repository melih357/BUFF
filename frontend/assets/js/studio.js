document.addEventListener('DOMContentLoaded', async () => {
  if(document.body.dataset.buffStudioMounted)return;document.body.dataset.buffStudioMounted='1';
  let user = await LogiApp.getCurrentUser();
  if (!LogiApp.requireLoggedIn(user)) return;

  const localVideo = document.getElementById('localVideo');
  const form = document.getElementById('streamForm');
  const statusEl = document.getElementById('studioStatus');
  const messageEl = document.getElementById('studioMessage');
  const giftLayer = document.getElementById('studioGiftLayer');
  const chatList = document.getElementById('studioChatList');
  const giftFeed = document.getElementById('studioGiftFeed');
  const chatReader=window.BUFFChat.mount(chatList,{user,input:document.getElementById('studioCommandInput'),studio:true});
  let chatPollBusy=false,giftPollBusy=false;const seenGiftIds=new Set();

  const cameraSelect = document.getElementById('cameraSelect');
  const microphoneSelect = document.getElementById('microphoneSelect');
  let localStream = null;
  let screenStream = null;
  let activeStream = null;
  let lastChatId = 0;
  let lastGiftId = 0;
  let giftHistoryLoaded = false;
  let signalTimer = null;
  let heartbeatTimer = null;
  let monitorTimer = null;
  let giftTimer = null;
  let chatTimer = null;
  const peers = new Map();
  let mediaRecorder = null;
  let recordedChunks = [];
  let recordStartedAt = 0;
  let recordingStreamId = 0;
  let vodCanvas = null;
  let vodDrawRaf = 0;
  let pendingVod = null;
  let lastStatsBytes = 0;
  let lastStatsAt = performance.now();

  function setStatus(text, live = false) {
    statusEl.textContent = text;
    statusEl.className = `status-pill ${live ? 'live' : 'offline'}`;
    document.getElementById('previewLiveLabel').textContent = live ? 'CANLI' : 'ÖNİZLEME';
  }
  function setMessage(text, ok = false) {
    messageEl.textContent = text || '';
    messageEl.className = `form-message ${ok ? 'success' : 'error'}`;
  }


  async function populateDevices() {
    if (!navigator.mediaDevices?.enumerateDevices) return;
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      const currentCam = cameraSelect?.value || '';
      const currentMic = microphoneSelect?.value || '';
      if (cameraSelect) cameraSelect.innerHTML = '<option value="">Varsayılan kamera</option>' + devices.filter(d => d.kind === 'videoinput').map((d,i) => `<option value="${LogiApp.escapeHtml(d.deviceId)}">${LogiApp.escapeHtml(d.label || `Kamera ${i+1}`)}</option>`).join('');
      if (microphoneSelect) microphoneSelect.innerHTML = '<option value="">Varsayılan mikrofon</option>' + devices.filter(d => d.kind === 'audioinput').map((d,i) => `<option value="${LogiApp.escapeHtml(d.deviceId)}">${LogiApp.escapeHtml(d.label || `Mikrofon ${i+1}`)}</option>`).join('');
      if (cameraSelect && [...cameraSelect.options].some(o => o.value === currentCam)) cameraSelect.value = currentCam;
      if (microphoneSelect && [...microphoneSelect.options].some(o => o.value === currentMic)) microphoneSelect.value = currentMic;
    } catch (_) {}
  }

  async function restartPreviewForDeviceChange() {
    if (!localStream) return;
    localStream.getTracks().forEach(t => t.stop());
    localStream = null;
    await prepareCamera();
  }

  async function prepareCamera() {
    if (localStream) return localStream;
    if (!navigator.mediaDevices?.getUserMedia) throw new Error('Tarayıcı kamera erişimini desteklemiyor.');
    const videoDeviceId = cameraSelect?.value || '';
    const audioDeviceId = microphoneSelect?.value || '';
    localStream = await navigator.mediaDevices.getUserMedia({
      video: { width: { ideal: 1920 }, height: { ideal: 1080 }, frameRate: { ideal: 30 }, ...(videoDeviceId ? { deviceId: { exact: videoDeviceId } } : {}) },
      audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, ...(audioDeviceId ? { deviceId: { exact: audioDeviceId } } : {}) }
    });
    localVideo.srcObject = localStream;
    document.getElementById('cameraEmpty').classList.add('hidden');
    setStatus('HAZIR', false);
    setMessage('Kamera ve mikrofon hazır. Yayın bilgilerini kontrol edip yayını başlatabilirsin.', true);
    return localStream;
  }

  function createFallbackStream(reason = '') {
    const canvas = document.createElement('canvas');
    canvas.width = 1280; canvas.height = 720;
    const ctx = canvas.getContext('2d');
    const draw = () => {
      ctx.fillStyle = '#090a0d'; ctx.fillRect(0,0,canvas.width,canvas.height);
      const gradient = ctx.createLinearGradient(0,0,canvas.width,canvas.height); gradient.addColorStop(0,'rgba(231,55,43,.38)'); gradient.addColorStop(1,'rgba(255,134,35,.08)'); ctx.fillStyle=gradient; ctx.fillRect(0,0,canvas.width,canvas.height);
      ctx.fillStyle='#fff'; ctx.font='900 74px system-ui'; ctx.textAlign='center'; ctx.fillText('BUFF LIVE', canvas.width/2, 310);
      ctx.font='500 28px system-ui'; ctx.fillStyle='#c8cbd2'; ctx.fillText('Kamera kapalı • Yayın devam ediyor', canvas.width/2, 365);
      if (reason) { ctx.font='400 20px system-ui'; ctx.fillStyle='#8f949f'; ctx.fillText(String(reason).slice(0,90), canvas.width/2, 410); }
    };
    draw();
    const stream = canvas.captureStream ? canvas.captureStream(5) : new MediaStream();
    localStream = stream;
    localVideo.srcObject = localStream;
    document.getElementById('cameraEmpty')?.classList.add('hidden');
    setStatus('HAZIR', false);
    return stream;
  }

  function currentVideoTrack() {
    return (screenStream || localStream)?.getVideoTracks?.()[0] || null;
  }

  async function replaceVideoTrack(track) {
    for (const pc of peers.values()) {
      const sender = pc.getSenders().find(s => s.track?.kind === 'video');
      if (sender && track) await sender.replaceTrack(track).catch(() => {});
    }
  }

  document.getElementById('micBtn').addEventListener('click', async e => {
    await prepareCamera().catch(error => setMessage(error.message));
    const track = localStream?.getAudioTracks?.()[0];
    if (!track) return;
    track.enabled = !track.enabled;
    e.currentTarget.classList.toggle('active', track.enabled);
    e.currentTarget.textContent = track.enabled ? '🎙' : '🔇';
  });

  document.getElementById('cameraBtn').addEventListener('click', async e => {
    await prepareCamera().catch(error => setMessage(error.message));
    const track = localStream?.getVideoTracks?.()[0];
    if (!track) return;
    track.enabled = !track.enabled;
    e.currentTarget.classList.toggle('active', track.enabled);
  });

  document.getElementById('screenBtn').addEventListener('click', async e => {
    try {
      if (screenStream) {
        screenStream.getTracks().forEach(t => t.stop());
        screenStream = null;
        await replaceVideoTrack(localStream?.getVideoTracks?.()[0]);
        localVideo.srcObject = localStream;
        e.currentTarget.classList.remove('active');
        return;
      }
      await prepareCamera();
      screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
      const screenTrack = screenStream.getVideoTracks()[0];
      await replaceVideoTrack(screenTrack);
      localVideo.srcObject = screenStream;
      e.currentTarget.classList.add('active');
      screenTrack.addEventListener('ended', async () => {
        screenStream = null;
        await replaceVideoTrack(localStream?.getVideoTracks?.()[0]);
        localVideo.srcObject = localStream;
        e.currentTarget.classList.remove('active');
      });
    } catch (error) { setMessage(error.message); }
  });

  function captureVodThumbnail() {
    return new Promise(resolve => {
      try {
        if (!localVideo.videoWidth || !localVideo.videoHeight) return resolve(null);
        const canvas = document.createElement('canvas');
        canvas.width = 960; canvas.height = Math.max(540, Math.round(960 * localVideo.videoHeight / localVideo.videoWidth));
        canvas.getContext('2d').drawImage(localVideo, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(resolve, 'image/jpeg', .82);
      } catch (_) { resolve(null); }
    });
  }

  function bestRecorderMime() {
    const choices = ['video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm'];
    return choices.find(x => window.MediaRecorder?.isTypeSupported?.(x)) || '';
  }

  function stopVodCanvasLoop() {
    if (vodDrawRaf) cancelAnimationFrame(vodDrawRaf);
    vodDrawRaf = 0;
    vodCanvas = null;
  }

  function createVodCaptureStream() {
    if (!HTMLCanvasElement.prototype.captureStream) throw new Error('Bu tarayıcı yayın tekrar kaydını desteklemiyor.');
    vodCanvas = document.createElement('canvas');
    vodCanvas.width = 1280;
    vodCanvas.height = 720;
    const ctx = vodCanvas.getContext('2d', { alpha: false });
    const paint = () => {
      if (!vodCanvas || !ctx) return;
      ctx.fillStyle = '#08090b';
      ctx.fillRect(0, 0, vodCanvas.width, vodCanvas.height);
      try {
        const vw = localVideo.videoWidth || 0, vh = localVideo.videoHeight || 0;
        if (vw > 0 && vh > 0 && localVideo.readyState >= 2) {
          const scale = Math.min(vodCanvas.width / vw, vodCanvas.height / vh);
          const dw = Math.round(vw * scale), dh = Math.round(vh * scale);
          const dx = Math.round((vodCanvas.width - dw) / 2), dy = Math.round((vodCanvas.height - dh) / 2);
          ctx.drawImage(localVideo, dx, dy, dw, dh);
        } else {
          ctx.fillStyle = '#fff';
          ctx.font = '800 54px system-ui';
          ctx.textAlign = 'center';
          ctx.fillText('BUFF LIVE', vodCanvas.width / 2, vodCanvas.height / 2);
        }
      } catch (_) {}
      vodDrawRaf = requestAnimationFrame(paint);
    };
    paint();
    const capture = vodCanvas.captureStream(30);
    const audioTrack = localStream?.getAudioTracks?.()[0];
    if (audioTrack) capture.addTrack(audioTrack);
    return capture;
  }

  function startVodRecording(streamId) {
    if (!window.MediaRecorder) throw new Error('Tarayıcın yayın tekrar kaydını desteklemiyor.');
    const capture = createVodCaptureStream();
    const mimeType = bestRecorderMime();
    try {
      mediaRecorder = new MediaRecorder(capture, mimeType ? {mimeType, videoBitsPerSecond: 3500000, audioBitsPerSecond: 128000} : undefined);
      recordedChunks = [];
      recordStartedAt = Date.now();
      recordingStreamId = Number(streamId || 0);
      mediaRecorder.ondataavailable = e => { if (e.data?.size) recordedChunks.push(e.data); };
      mediaRecorder.onerror = () => LogiApp.toast('Yayın tekrar kaydında tarayıcı kaynaklı bir hata oluştu.', 'error');
      mediaRecorder.start(2000);
      document.getElementById('recordVodState').textContent = '● Kayıt alınıyor';
      document.getElementById('recordVodState').classList.add('recording');
      LogiApp.toast('Yayın tekrar kaydı başladı.', 'success');
      return true;
    } catch (error) {
      stopVodCanvasLoop();
      mediaRecorder = null;
      recordedChunks = [];
      throw error;
    }
  }

  async function uploadVodInChunks(blob, meta) {
    const chunkSize = 1024 * 1024; // PHP'nin düşük varsayılan upload limitlerinde de çalışsın.
    const totalChunks = Math.max(1, Math.ceil(blob.size / chunkSize));
    const random = window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    const uploadId = `vod-${meta.streamId}-${random}`.replace(/[^a-zA-Z0-9_-]/g, '');
    let result = null;
    for (let i = 0; i < totalChunks; i++) {
      const part = blob.slice(i * chunkSize, Math.min(blob.size, (i + 1) * chunkSize), blob.type || 'video/webm');
      const fd = new FormData();
      fd.append('uploadId', uploadId);
      fd.append('streamId', String(meta.streamId));
      fd.append('chunkIndex', String(i));
      fd.append('totalChunks', String(totalChunks));
      fd.append('durationSeconds', String(meta.durationSeconds));
      fd.append('title', meta.title);
      fd.append('visibility', meta.visibility);
      fd.append('mimeType', blob.type || 'video/webm');
      fd.append('videoChunk', part, `chunk-${i}.bin`);
      setMessage(`Yayın bitti. Tekrar kaydı yükleniyor… %${Math.round((i / totalChunks) * 100)}`, true);
      result = await LogiApp.api('/recordings/chunk.php', {method:'POST', body:fd});
    }
    setMessage('Tekrar kaydı işlendi ve Tekrarlar bölümünde yayınlandı.', true);
    return result;
  }

  async function stopVodToPending(streamSnapshot) {
    if (!mediaRecorder || mediaRecorder.state === 'inactive' || !recordingStreamId) return null;
    const recorder = mediaRecorder;
    const streamId = recordingStreamId;
    const stopped = new Promise(resolve => recorder.addEventListener('stop', resolve, {once:true}));
    recorder.stop();
    await stopped;
    const chunks = recordedChunks.slice();
    mediaRecorder = null;
    recordedChunks = [];
    recordingStreamId = 0;
    stopVodCanvasLoop();
    document.getElementById('recordVodState')?.classList.remove('recording');
    if (!chunks.length) return null;
    const blob = new Blob(chunks, {type: recorder.mimeType || 'video/webm'});
    const duration = Math.max(1, Math.round((Date.now() - recordStartedAt) / 1000));
    pendingVod = {
      blob,
      meta: {
        streamId,
        durationSeconds: duration,
        title: streamSnapshot?.title || form.elements.title?.value || 'Yayın Tekrarı',
        visibility: user.replayVisibility || 'private'
      }
    };
    return pendingVod;
  }

  function openVodDecision() {
    const modal = document.getElementById('vodDecisionModal');
    if (!modal || !pendingVod) return false;
    const duration = document.getElementById('vodDecisionDuration');
    if (duration) duration.textContent = `${Math.floor(pendingVod.meta.durationSeconds/60)} dk ${pendingVod.meta.durationSeconds%60} sn kayıt hazır`;
    LogiApp.openModal('vodDecisionModal');
    return true;
  }

  function createPeerForViewer(viewerId) {
    const pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
    peers.set(viewerId, pc);
    if (localStream) {
      const videoTrack = currentVideoTrack();
      if (videoTrack) pc.addTrack(videoTrack, screenStream || localStream);
      localStream.getAudioTracks().forEach(track => pc.addTrack(track, localStream));
    }
    pc.onicecandidate = event => {
      if (event.candidate && activeStream) LogiApp.api('/signal/send.php', { method: 'POST', body: JSON.stringify({ streamId: activeStream.id, recipientUserId: viewerId, type: 'candidate', payload: event.candidate.toJSON() }) }).catch(() => {});
    };
    pc.onconnectionstatechange = () => {
      if (['failed', 'closed', 'disconnected'].includes(pc.connectionState)) peers.delete(viewerId);
      document.getElementById('peerCountStat').textContent = String(peers.size);
    };
    document.getElementById('peerCountStat').textContent = String(peers.size);
    return pc;
  }

  async function pollSignals() {
    if (!activeStream) return;
    try {
      const data = await LogiApp.api(`/signal/poll.php?stream_id=${activeStream.id}`);
      for (const msg of data.messages || []) {
        if (msg.type === 'offer') {
          let pc = peers.get(msg.senderUserId);
          if (!pc) pc = createPeerForViewer(msg.senderUserId);
          await pc.setRemoteDescription(new RTCSessionDescription(msg.payload));
          const answer = await pc.createAnswer();
          await pc.setLocalDescription(answer);
          await LogiApp.api('/signal/send.php', { method: 'POST', body: JSON.stringify({ streamId: activeStream.id, recipientUserId: msg.senderUserId, type: 'answer', payload: answer }) });
        } else if (msg.type === 'candidate') {
          let pc = peers.get(msg.senderUserId);
          if (!pc) pc = createPeerForViewer(msg.senderUserId);
          try { await pc.addIceCandidate(new RTCIceCandidate(msg.payload)); } catch (_) {}
        }
      }
    } catch (_) {}
  }

  function addChat(msg) {
    chatList.querySelector('.chat-empty, .rail-empty')?.remove();
    const item = document.createElement('div');
    item.className = `chat-message ${msg.isOwner ? 'broadcaster' : ''} ${msg.isModerator ? 'moderator-message' : ''}`;
    const badges = [msg.isOwner ? '<em class="chat-role broadcaster-badge">YAYINCI</em>' : '', msg.isModerator ? '<em class="chat-role mod-badge">MOD</em>' : '', msg.isOg ? '<em class="chat-role og-badge">OG</em>' : '', msg.isVip ? `<em class="chat-role vip-badge tier-${LogiApp.escapeHtml(msg.vipTier || 'plus')}">${LogiApp.escapeHtml((msg.vipTier || 'VIP').toUpperCase())}</em>` : ''].join('');
    item.dataset.role = msg.isOwner ? 'owner' : msg.isModerator ? 'mod' : msg.isVip ? 'vip' : 'user';
    const body = msg.messageType === 'sticker' ? `<div class="chat-sticker">✨ ${LogiApp.escapeHtml(msg.stickerCode || 'VIP')}</div>` : `<p style="color:${LogiApp.escapeHtml(LogiApp.readableChatColor(msg.chatColor))}">${LogiApp.escapeHtml(msg.message)}</p>`;
    item.innerHTML = `${LogiApp.avatarMarkup({ displayName: msg.displayName, avatarUrl: msg.avatarUrl }, 'chat-avatar')}<div><div class="chat-meta"><strong>${LogiApp.escapeHtml(msg.displayName)}</strong>${badges}<small>${LogiApp.escapeHtml(LogiApp.timeAgo(msg.createdAt))}</small></div>${body}</div>`;
    chatReader.append(item,msg);
  }

  async function pollChat() {
    if (!activeStream||chatPollBusy) return;chatPollBusy=true;const pollingStream=activeStream.id;
    try {
      const data = await LogiApp.api(`/chat/list.php?stream_id=${activeStream.id}${lastChatId ? `&after_id=${lastChatId}` : ''}`);
      if(activeStream?.id!==pollingStream)return;
      if (data.reset) { lastChatId = 0; chatReader.clear(); return; }
      if (!lastChatId && (data.messages || []).length) chatReader.clear();
      (data.messages || []).forEach(msg => { addChat(msg); lastChatId = Math.max(lastChatId, Number(msg.id)); });
      document.getElementById('studioChatCount').textContent = `${chatList.querySelectorAll('.chat-message').length} mesaj`;
    } catch (_) {} finally {chatPollBusy=false;}
  }

  function playGift(event) {
    const id=Number(event.eventId||event.id);if(!Number.isSafeInteger(id)||id<1||seenGiftIds.has(id))return;seenGiftIds.add(id);
    if (window.BUFFMotion?.playGift && giftLayer) {
      window.BUFFMotion.playGift(giftLayer, event);
      return;
    }
    const burst = document.createElement('div');
    burst.className = `gift-burst ${event.totalPrice >= 2500 ? 'gift-legendary' : event.totalPrice >= 800 ? 'gift-rare' : 'gift-standard'}`;
    burst.innerHTML = `<div class="gift-burst-card"><span class="gift-burst-icon">${event.giftIcon}</span><div><small>${LogiApp.escapeHtml(event.senderName)}</small><strong>${LogiApp.escapeHtml(event.giftName)} x${event.quantity}</strong><em>${LogiApp.formatNumber(event.totalPrice)} coin</em></div></div>`;
    giftLayer.appendChild(burst);
    requestAnimationFrame(() => burst.classList.add('play'));
    if (event.audioUrl) { const audio = new Audio(event.audioUrl); audio.volume = 0.8; audio.play().catch(() => {}); }
    setTimeout(() => burst.remove(), 3600);
  }

  function aggregateStudioGifts(rows = []) {
    const map = new Map();
    rows.forEach(event => {
      const key = `${event.giftId}|${event.giftName}|${event.giftIcon}`;
      if (!map.has(key)) map.set(key, { ...event, quantity: 0, totalPrice: 0, senders: new Set(), latest: event.createdAt });
      const item = map.get(key);
      item.quantity += Number(event.quantity || 0);
      item.totalPrice += Number(event.totalPrice || 0);
      item.senders.add(event.senderName);
      if (new Date(event.createdAt) > new Date(item.latest)) item.latest = event.createdAt;
    });
    return [...map.values()].sort((a, b) => new Date(b.latest) - new Date(a.latest));
  }

  async function pollGifts() {
    if (!activeStream||giftPollBusy) return;giftPollBusy=true;const pollingStream=activeStream.id;
    try {
      const data = await LogiApp.api(`/gifts/events.php?stream_id=${activeStream.id}${lastGiftId ? `&after_id=${lastGiftId}` : ''}`);
      const events = data.events || [];
      if(activeStream?.id!==pollingStream)return;
      if (giftHistoryLoaded) events.forEach(playGift);else events.forEach(e=>seenGiftIds.add(Number(e.eventId||e.id)));
      giftHistoryLoaded = true;
      events.forEach(event => { lastGiftId = Math.max(lastGiftId, Number(event.id)); });
      if (events.length || !giftFeed.dataset.loaded) {
        const full = await LogiApp.api(`/gifts/events.php?stream_id=${activeStream.id}`);
        const rows = full.events || [];
        const aggregated = aggregateStudioGifts(rows).slice(0, 12);
        giftFeed.dataset.loaded = '1';
        giftFeed.innerHTML = aggregated.length ? aggregated.map(event => `<div class="gift-feed-item gift-summary-item"><span>${event.giftIcon}</span><div><strong>${LogiApp.escapeHtml(event.giftName)} ×${event.quantity}</strong><small>${event.senders.size} gönderici • ${LogiApp.escapeHtml(LogiApp.timeAgo(event.latest))}</small></div><em>+${LogiApp.formatNumber(event.totalPrice)}</em></div>`).join('') : '<div class="rail-empty">Henüz hediye yok.</div>';
        const total = rows.reduce((sum, e) => sum + Number(e.totalPrice || 0), 0);
        document.getElementById('studioGiftTotal').textContent = `${LogiApp.formatNumber(total)} coin`;
        document.getElementById('studioGiftStat').textContent = LogiApp.formatNumber(total);
      }
    } catch (_) {} finally {giftPollBusy=false;}
  }

  giftFeed?.addEventListener('click', e => { const btn = e.target.closest('[data-audio]'); if (btn) new Audio(btn.dataset.audio).play().catch(() => {}); });

  function formatStudioDuration(seconds) {
    const total = Math.max(0, Number(seconds || 0));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    if (hours) return `${hours} saat ${minutes} dk`;
    return `${minutes} dk`;
  }

  async function loadWeeklyStats() {
    try {
      const data = await LogiApp.api('/creator/weekly-stats.php');
      const stats = data.stats || {};
      const period = document.getElementById('weeklyPeriodLabel');
      const creatorName = document.getElementById('weeklyCreatorName');
      const creatorUsername = document.getElementById('weeklyCreatorUsername');
      if (!period || !creatorName || !creatorUsername) return;
      period.textContent = data.period?.label || 'Bu hafta';
      creatorName.textContent = data.creator?.displayName || user.displayName || 'BUFF yayıncısı';
      creatorUsername.textContent = `@${data.creator?.username || user.username || 'buff'}`;
      const avatar = document.getElementById('weeklyCreatorAvatar');
      if (avatar) {
        if (data.creator?.avatarUrl) avatar.innerHTML = `<img src="${LogiApp.escapeHtml(data.creator.avatarUrl)}" alt="">`;
        else avatar.textContent = String(data.creator?.displayName || user.displayName || 'B').charAt(0).toUpperCase();
      }
      document.getElementById('weeklyStreamCount').textContent = LogiApp.formatNumber(stats.streamCount || 0);
      document.getElementById('weeklyStreamDuration').textContent = formatStudioDuration(stats.totalStreamSeconds || 0);
      document.getElementById('weeklyWatchHours').textContent = (Number(stats.watchSeconds || 0) / 3600).toLocaleString('tr-TR', { maximumFractionDigits: 1 });
      document.getElementById('weeklyMessageCount').textContent = LogiApp.formatNumber(stats.messageCount || 0);
      document.getElementById('weeklyUniqueChatters').textContent = LogiApp.formatNumber(stats.uniqueChatters || 0);
      document.getElementById('weeklyGiftCoins').textContent = `${LogiApp.formatNumber(stats.giftCoins || 0)} coin`;
      document.getElementById('weeklyPeakViewers').textContent = LogiApp.formatNumber(stats.peakViewers || 0);
      document.getElementById('weeklyAvgViewers').textContent = LogiApp.formatNumber(stats.avgViewers || 0);
      document.getElementById('weeklyPeakChatters').textContent = LogiApp.formatNumber(stats.peakChatters || 0);
    } catch (_) {}
  }

  async function pollStats() {
    if (!activeStream) return;
    try {
      const data = await LogiApp.api(`/streams.php?id=${activeStream.id}`);
      const viewers = Number(data.stream.viewerCount || 0);
      document.getElementById('previewViewerCount').textContent = `◉ ${LogiApp.formatNumber(viewers)} izleyici`;
      document.getElementById('studioViewerStat').textContent = LogiApp.formatNumber(viewers);
      let bytes = 0; let connected = 0;
      for (const pc of peers.values()) {
        if (pc.connectionState === 'connected') connected++;
        try { const stats = await pc.getStats(); stats.forEach(r => { if (r.type === 'outbound-rtp' && r.kind === 'video' && !r.isRemote) bytes += Number(r.bytesSent || 0); }); } catch (_) {}
      }
      const now = performance.now(); const seconds = Math.max(.25, (now-lastStatsAt)/1000); const kbps = Math.max(0, Math.round(((bytes-lastStatsBytes)*8/1000)/seconds));
      if (bytes >= lastStatsBytes) document.getElementById('studioBitrateStat').textContent = `${kbps} kbps`;
      document.getElementById('studioConnectionStat').textContent = peers.size ? `${connected}/${peers.size} bağlı` : 'Hazır';
      lastStatsBytes = bytes; lastStatsAt = now;
    } catch (_) {}
  }

  async function startStream() {
    const startBtn = document.getElementById('startBtn');
    if (startBtn?.disabled) return;
    try {
      const titleInput = form?.querySelector('[name="title"]');
      const categoryInput = form?.querySelector('[name="category"]');
      if (!titleInput?.value.trim()) { setMessage('Yayını başlatmak için bir yayın başlığı yaz.'); titleInput?.focus(); return; }
      if (!categoryInput?.value.trim()) { setMessage('Yayını başlatmak için kategori seç veya yaz.'); categoryInput?.focus(); return; }
      if (startBtn) { startBtn.disabled = true; startBtn.textContent = '● Yayın hazırlanıyor...'; document.body.classList.add('studio-status-starting'); }
      try { await prepareCamera(); }
      catch (cameraError) {
        createFallbackStream(cameraError.message || 'Kamera izni alınamadı');
        LogiApp.toast('Kamera açılamadı; yayın kamera kapalı yedek görüntüyle başlatılacak. İstersen daha sonra cihaz izni verebilirsin.','error',6500);
      }
      const payload = Object.fromEntries(new FormData(form).entries());
      payload.delaySeconds = Number(payload.delaySeconds || user.defaultStreamDelay || 5);
      payload.adultOnly = document.getElementById('adultOnlyToggle')?.checked ? 1 : 0;
      payload.matureLanguage = document.getElementById('matureLanguageToggle')?.checked ? 1 : 0;
      if (payload.matureLanguage && !payload.adultOnly) {
        setMessage('Fazla küfür / sert dil seçiliyse +18 etiketi zorunludur. Lütfen "+18" kutusunu da işaretle.', false);
        if (startBtn) { startBtn.disabled = false; startBtn.textContent = '● Yayını Başlat'; }
        return;
      }
      const data = await LogiApp.api('/stream/start.php', { method: 'POST', body: JSON.stringify(payload) });
      activeStream = data.stream;
      if ((user.accountMode || 'viewer') !== 'creator') user = {...user, accountMode:'creator', creatorEnabled:true};
      // Yayın başladığı an yerel replay kaydını otomatik başlat. Kayıt desteği yoksa
      // canlı yayın yine devam eder; yalnızca yayın sonunda tekrar yükleme seçeneği sunulmaz.
      try { startVodRecording(activeStream.id); }
      catch (recordError) {
        LogiApp.toast(recordError.message || 'Bu tarayıcı yayın tekrar kaydını desteklemiyor.', 'error', 6000);
        const state = document.getElementById('recordVodState');
        if (state) state.textContent = 'Kayıt kullanılamıyor • yayın devam ediyor';
      }
      setStatus('CANLI', true);
      setMessage(`Yayın başladı. İzleyici bağlantısı anında kurulur; görüntü/ses ${data.delaySeconds ?? payload.delaySeconds} saniye tamponlu oynatılır. Chat ve hediyeler anlık olarak kontrol merkezine düşer.`, true);
      document.getElementById('studioDelayStat').textContent = `${data.delaySeconds ?? payload.delaySeconds} sn`;
      const shareUrl = `${window.location.origin}${window.location.pathname.replace('/studio.php', '/watch.php')}?id=${activeStream.id}`;
      document.getElementById('shareLinkInput').value = shareUrl;
      lastChatId = 0; lastGiftId = 0; giftHistoryLoaded = false;seenGiftIds.clear();chatReader.clear();
      clearTimers();
      signalTimer = setInterval(pollSignals, 900);
      heartbeatTimer = setInterval(() => LogiApp.api('/stream/heartbeat.php', { method: 'POST', body: '{}' }).catch(() => {}), 4000);
      monitorTimer = setInterval(pollStats, 4000);
      chatTimer = setInterval(pollChat, 1100);
      giftTimer = setInterval(pollGifts, 1500);
      await Promise.all([pollSignals(), pollStats(), pollChat(), pollGifts()]);
      if (startBtn) startBtn.textContent = '● Yayın Aktif';
    } catch (error) {
      setMessage(error.message || 'Yayın başlatılamadı.');
      if (startBtn) { startBtn.disabled = false; startBtn.textContent = '● Yayını Başlat'; }
    } finally {
      document.body.classList.remove('studio-status-starting');
    }
  }

  function clearTimers() {
    [signalTimer, heartbeatTimer, monitorTimer, giftTimer, chatTimer].forEach(id => id && clearInterval(id));
  }

  async function stopStream() {
    try {
      const streamSnapshot = activeStream ? {...activeStream} : null;
      await LogiApp.api('/stream/stop.php', { method: 'POST', body: '{}' });
      try { if (streamSnapshot) await stopVodToPending(streamSnapshot); }
      catch (vodError) { LogiApp.toast(vodError.message || 'Yayın kaydı sonlandırılamadı.', 'error', 6000); pendingVod = null; }
      activeStream = null;
      clearTimers();
      peers.forEach(pc => pc.close()); peers.clear();
      document.getElementById('peerCountStat').textContent = '0';
      setStatus('ÇEVRİMDIŞI', false);
      const startBtn = document.getElementById('startBtn'); if (startBtn) { startBtn.disabled = false; startBtn.textContent = '● Yayını Başlat'; }
      await loadWeeklyStats();
      if (pendingVod && openVodDecision()) {
        setMessage('Yayın sona erdi. Kayıt hazır; siteye tekrar olarak yüklemek isteyip istemediğini seç.', true);
      } else {
        setMessage('Yayın sona erdi. Bu oturum için tekrar kaydı oluşturulamadı.', true);
      }
    } catch (error) { setMessage(error.message || 'Yayın durdurulamadı.'); }
  }

  document.getElementById('studioCommandForm')?.addEventListener('submit', async e => {
    e.preventDefault();
    if (!activeStream) { document.getElementById('studioCommandMessage').textContent = 'Komut kullanmak için yayın açık olmalı.'; return; }
    const input = document.getElementById('studioCommandInput');
    const text = input.value.trim();
    const submit=e.currentTarget.querySelector('button[type=submit]');
    if (!text||submit.disabled) return;submit.disabled=true;
    try {
      const result = await LogiApp.api('/chat/send.php', { method: 'POST', body: JSON.stringify({ streamId: activeStream.id, message: text }) });
      document.getElementById('studioCommandMessage').textContent = result.message || 'Komut uygulandı.';
      if(input.value.trim()===text)input.value = '';
      if (text.startsWith('/clear')) { lastChatId = 0; chatReader.clear(); await pollChat(); }
      LogiApp.toast(result.message || 'Moderatör komutu uygulandı.', 'success');
    } catch (error) {
      document.getElementById('studioCommandMessage').textContent = error.message;
    } finally {submit.disabled=false;}
  });

  const delaySelect = document.getElementById('studioDelaySelect');
  const recordVodState = document.getElementById('recordVodState');

  if (delaySelect) delaySelect.value = String(user.defaultStreamDelay ?? 5);
  document.getElementById('studioDelayStat').textContent = `${delaySelect?.value || 5} sn`;
  document.getElementById('studioTopDelayBadge').textContent = `⏱ İzleyicide ${delaySelect?.value || 5} sn gecikme`;
  delaySelect?.addEventListener('change', async () => {
    const nextDelay = Number(delaySelect.value || 0);
    document.getElementById('studioDelayStat').textContent = `${nextDelay} sn`;
    document.getElementById('studioTopDelayBadge').textContent = `⏱ İzleyicide ${nextDelay} sn gecikme`;
    if (!activeStream) return;
    try {
      const result = await LogiApp.api('/stream/update.php', {method:'POST', body:JSON.stringify({delaySeconds:nextDelay})});
      activeStream = {...activeStream, delaySeconds:Number(result.delaySeconds ?? nextDelay)};
      await Promise.all([...peers.keys()].map(viewerId => LogiApp.api('/signal/send.php', {
        method:'POST',
        body:JSON.stringify({streamId:activeStream.id, recipientUserId:viewerId, type:'delay', payload:{delaySeconds:nextDelay}})
      }).catch(() => null)));
      LogiApp.toast(nextDelay ? `Yayın gecikmesi anında ${nextDelay} saniyeye alındı.` : 'Yayın gecikmesi anında kapatıldı.', 'success');
    } catch (error) {
      LogiApp.toast(error.message || 'Yayın gecikmesi güncellenemedi.', 'error');
    }
  });
  document.getElementById('vodUploadBtn')?.addEventListener('click', async () => {
    if (!pendingVod) return;
    const btn = document.getElementById('vodUploadBtn');
    const visibility = document.getElementById('vodDecisionVisibility')?.value || user.replayVisibility || 'private';
    try {
      btn.disabled = true; btn.textContent = 'Yükleniyor…';
      pendingVod.meta.visibility = visibility;
      const result = await uploadVodInChunks(pendingVod.blob, pendingVod.meta);
      pendingVod = null;
      LogiApp.closeModal?.('vodDecisionModal');
      LogiApp.toast('Yayın tekrarın Tekrarlar bölümüne eklendi.', 'success', 5000);
      setTimeout(() => { window.location.href = result?.recordingId ? `./replay.php?id=${result.recordingId}` : './replay.php'; }, 700);
    } catch (error) {
      LogiApp.toast(error.message || 'Tekrar yüklenemedi.', 'error', 7000);
      btn.disabled = false; btn.textContent = 'Tekrar Olarak Yükle';
    }
  });
  document.getElementById('vodDiscardBtn')?.addEventListener('click', () => {
    pendingVod = null;
    LogiApp.closeModal?.('vodDecisionModal');
    LogiApp.toast('Yayın kaydı yüklenmeden silindi.', 'success');
    setTimeout(() => { window.location.href = './index.php'; }, 450);
  });

  let chatScale = Number(localStorage.getItem('logi_studio_chat_scale') || 1);
  const applyChatScale = () => { chatScale = Math.max(.85, Math.min(1.45, chatScale)); document.documentElement.style.setProperty('--studio-chat-scale', chatScale); localStorage.setItem('logi_studio_chat_scale', String(chatScale)); };
  applyChatScale();
  document.getElementById('chatSmallerBtn')?.addEventListener('click', () => { chatScale -= .1; applyChatScale(); });
  document.getElementById('chatLargerBtn')?.addEventListener('click', () => { chatScale += .1; applyChatScale(); });

  window.addEventListener('buff:realtime', event => {
    const msg = event.detail || {};
    if (msg.event !== 'gift.sent' || !activeStream) return;
    const payload = msg.payload || {};
    if (Number(payload.streamId || msg.streamId || 0) !== Number(activeStream.id)) return;
    const id = Number(payload.eventId || payload.id || 0);
    if(!Number.isSafeInteger(id)||id<1||seenGiftIds.has(id))return;
    const giftEvent = {
      id,
      giftId: Number(payload.giftId || 0),
      giftName: payload.giftName || 'Hediye',
      giftIcon: payload.giftIcon || '🎁',
      quantity: Number(payload.quantity || 1),
      totalPrice: Number(payload.totalPrice || 0),
      senderName: payload.senderName || 'İzleyici',
      senderAvatar: payload.senderAvatar || null,
      audioUrl: payload.audioUrl || null,
      audioName: payload.audioName || null,
      createdAt: payload.createdAt || new Date().toISOString()
    };
    playGift(giftEvent);
    if (giftFeed) giftFeed.dataset.loaded = '';
    pollGifts().catch(() => {});
    loadWeeklyStats().catch(() => {});
  });

  await loadWeeklyStats();
  await populateDevices();
  cameraSelect?.addEventListener('change', () => restartPreviewForDeviceChange().catch(error => setMessage(error.message)));
  microphoneSelect?.addEventListener('change', () => restartPreviewForDeviceChange().catch(error => setMessage(error.message)));
  document.getElementById('chatFocusBtn')?.addEventListener('click', e => { document.body.classList.toggle('studio-chat-focus'); if(document.body.classList.contains('studio-chat-focus'))window.scrollTo(0,0); e.currentTarget.textContent = document.body.classList.contains('studio-chat-focus') ? 'Odaktan Çık' : 'Chat Odak'; });
  document.getElementById('chatImportantStudioBtn')?.addEventListener('click', e => { document.body.classList.toggle('studio-important-only'); e.currentTarget.classList.toggle('active', document.body.classList.contains('studio-important-only')); });
  document.getElementById('chatVipStudioBtn')?.addEventListener('click', e => { document.body.classList.toggle('studio-vip-only'); e.currentTarget.classList.toggle('active', document.body.classList.contains('studio-vip-only')); });

  document.getElementById('prepareBtn').addEventListener('click', () => prepareCamera().catch(error => setMessage(error.message)));
  document.getElementById('startBtn').addEventListener('click', startStream);
  document.getElementById('stopBtn').addEventListener('click', stopStream);
  document.getElementById('copyShareBtn').addEventListener('click', () => {
    const value = document.getElementById('shareLinkInput').value;
    if (value) LogiApp.copyText(value);
  });

  window.addEventListener('beforeunload', () => {
    clearTimers();
    peers.forEach(pc => pc.close());
    if (activeStream) navigator.sendBeacon('../backend/api/stream/stop.php');
  });
});
