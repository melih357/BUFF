document.addEventListener('DOMContentLoaded', async () => {
  if(document.body.dataset.buffWatchMounted)return;
  document.body.dataset.buffWatchMounted='1';
  const streamId = Number(LogiApp.getQuery('id') || 0);
  const remoteVideo = document.getElementById('remoteVideo');
  const playerStatus = document.getElementById('playerStatus');
  const viewerCountBadge = document.getElementById('viewerCountBadge');
  const followBtn = document.getElementById('followBtn');
  const followerCountBadge = document.getElementById('followerCountBadge');
  const giftCatalogEl = document.getElementById('giftCatalog');
  const giftEventsEl = document.getElementById('giftEvents');
  const myWallet = document.getElementById('myWallet');
  const chatList = document.getElementById('chatList');
  const delayOverlay = document.getElementById('delayOverlay');
  const delayCount = document.getElementById('delayCount');
  const animationLayer = document.getElementById('giftAnimationLayer');
  const moderationHint = document.getElementById('moderationHint');
  const vipStickerTray = document.getElementById('vipStickerTray');

  let me = await LogiApp.getCurrentUser();
  if (!LogiApp.requireLoggedIn(me)) return;
  if (!streamId) { playerStatus.textContent = 'Geçersiz yayın bağlantısı.'; return; }
  if (me.isVip) vipStickerTray.classList.remove('hidden');

  const chatReader=window.BUFFChat.mount(chatList,{user:me,input:document.getElementById('chatInput')});
  let chatPollBusy=false,giftPollBusy=false,chatSending=false;
  let stream = null;
  let selectedGift = null;
  let peer = null;
  let lastChatId = 0;
  let lastGiftId = 0;
  let hasLoadedGifts = false;
  const seenGiftIds = new Set();
  let signalTimer = null, heartbeatTimer = null, chatTimer = null, giftTimer = null, statusTimer = null;
  let endingRedirect = false;

  const stickerMap = {
    party_parrot: '🦜 PARTY!', gg_fire: '🔥 GG!', logi_love: '🖤 BUFF LOVE', hype_train: '🚂 HYPE!', king_crown: '👑 KING', space_cat: '🐱‍🚀 SPACE CAT'
  };

  function activateTab(name) {
    document.querySelectorAll('#watchTabs [data-tab]').forEach(btn => btn.classList.toggle('active', btn.dataset.tab === name));
    document.querySelectorAll('[data-panel]').forEach(panel => panel.classList.toggle('active', panel.dataset.panel === name));
  }
  document.querySelectorAll('#watchTabs [data-tab]').forEach(btn => btn.addEventListener('click', () => activateTab(btn.dataset.tab)));

  function badgeHtml(msg) {
    const out = [];
    if (msg.isOwner) out.push('<em class="chat-role broadcaster-badge">YAYINCI</em>');
    else if (msg.isModerator) out.push('<em class="chat-role mod-badge">MOD</em>');
    if (msg.isOg) out.push('<em class="chat-role og-badge">OG</em>');
    if (msg.isVip) out.push(`<em class="chat-role vip-badge tier-${LogiApp.escapeHtml(msg.vipTier || 'plus')}">${LogiApp.escapeHtml((msg.vipTier || 'VIP').toUpperCase())}</em>`);
    return out.join('');
  }

  function addChatMessage(msg) {
    chatList.querySelector('.chat-empty, .rail-empty')?.remove();
    const item = document.createElement('div');
    item.className = `chat-message ${msg.isOwner ? 'broadcaster' : ''} ${msg.isModerator ? 'moderator-message' : ''}`;
    const loyaltyLevel = Math.max(1, Number(msg.loyaltyLevel || 1));
    const loyaltyTier = Math.min(5, Math.max(1, Math.ceil(loyaltyLevel / 5)));
    const avatar = `<span class="v14-loyalty-frame v14-loyalty-frame-${loyaltyTier}" title="Sadakat seviyesi ${loyaltyLevel}">${LogiApp.avatarMarkup({ displayName: msg.displayName, avatarUrl: msg.avatarUrl }, 'chat-avatar')}</span>`;
    const body = msg.messageType === 'sticker'
      ? `<div class="chat-sticker">${LogiApp.escapeHtml(stickerMap[msg.stickerCode] || '✨ VIP')}</div>`
      : `<p style="--chat-color:${LogiApp.escapeHtml(LogiApp.readableChatColor(msg.chatColor))};color:var(--chat-color)">${LogiApp.escapeHtml(msg.message)}</p>`;
    item.dataset.userId = String(msg.userId || ''); item.dataset.messageId = String(msg.id || ''); item.dataset.role = msg.isOwner ? 'owner' : msg.isModerator ? 'mod' : msg.isVip ? 'vip' : 'user';
    item.innerHTML = `${avatar}<div><div class="chat-meta"><strong data-user-id="${Number(msg.userId||0)}" data-username="${LogiApp.escapeHtml(msg.username||'')}">${LogiApp.escapeHtml(msg.displayName)}</strong>${badgeHtml(msg)}<small>${LogiApp.escapeHtml(LogiApp.timeAgo(msg.createdAt))}</small></div>${body}</div>`;
    chatReader.append(item,msg);
  }

  async function pollChat(initial = false) {
    if(chatPollBusy)return;chatPollBusy=true;
    try {
      const data = await LogiApp.api(`/chat/list.php?stream_id=${streamId}${lastChatId ? `&after_id=${lastChatId}` : ''}`);
      if (data.reset) { lastChatId = 0; chatReader.clear(); return; }
      if (initial) chatReader.clear();
      (data.messages || []).forEach(msg => { addChatMessage(msg); lastChatId = Math.max(lastChatId, Number(msg.id)); });
      if (!lastChatId && !chatList.children.length) chatList.innerHTML = '<div class="chat-empty">Henüz mesaj yok. İlk mesajı sen gönder.</div>';
    } catch (_) {} finally {chatPollBusy=false;}
  }

  async function sendChat(text, stickerCode = '') {
    const clean = String(text || '').trim();
    if ((!clean && !stickerCode)||chatSending) return;
    chatSending=true;const submit=document.querySelector('#chatForm button');submit.disabled=true;
    try {
      const response = await LogiApp.api('/chat/send.php', { method: 'POST', body: JSON.stringify({ streamId, message: clean, stickerCode }) });
      if(document.getElementById('chatInput').value.trim()===clean)document.getElementById('chatInput').value = '';
      document.getElementById('chatMessage').textContent = '';
      if (response.command) {
        LogiApp.toast(response.message, 'success', 5000);
        if (clean.startsWith('/clear')) { lastChatId = 0; await pollChat(true); }
      } else await pollChat();
    } catch (error) {
      document.getElementById('chatMessage').textContent = error.message;
      LogiApp.toast(error.message, 'error');
    } finally {chatSending=false;submit.disabled=false;}
  }

  document.getElementById('chatForm').addEventListener('submit', e => { e.preventDefault(); sendChat(document.getElementById('chatInput').value); });
  document.querySelectorAll('.quick-emojis button').forEach(btn => btn.addEventListener('click', () => sendChat(btn.textContent)));
  document.querySelectorAll('[data-sticker]').forEach(btn => btn.addEventListener('click', () => sendChat('', btn.dataset.sticker)));

  async function enforceAgeGate() {
    if (!stream?.adultOnly) return;
    const key = `logi_age_gate_${streamId}`;
    if (sessionStorage.getItem(key) === '1') return;
    const modal = document.getElementById('ageGateModal');
    const warnings = []; if (stream.adultOnly) warnings.push('+18'); if (stream.matureLanguage) warnings.push('Yoğun küfür / sert dil');
    document.getElementById('ageGateWarnings').innerHTML = warnings.map(x => `<span>${LogiApp.escapeHtml(x)}</span>`).join('');
    modal.classList.add('open'); document.body.classList.add('modal-open');
    await new Promise(resolve => document.getElementById('ageGateAcceptBtn').addEventListener('click', () => { sessionStorage.setItem(key,'1'); modal.classList.remove('open'); document.body.classList.remove('modal-open'); resolve(); }, {once:true}));
  }

  async function loadModerationPermissions() {
    try {
      const data = await LogiApp.api(`/moderation/permissions.php?stream_id=${streamId}`);
      if (data.role === 'owner' || data.role === 'moderator') {
        moderationHint.classList.remove('hidden');
        moderationHint.innerHTML = `<strong>🛡 ${data.role === 'owner' ? 'Yayıncı' : 'Moderatör'} modu</strong><span>${data.commands.map(LogiApp.escapeHtml).join(' · ')}</span>`;
      }
    } catch (_) {}
  }

  function animationClass(event) {
    if (event.totalPrice >= 5000) return 'legendary';
    if (event.totalPrice >= 2000) return 'epic';
    if (event.totalPrice >= 800) return 'rare';
    return 'standard';
  }
  const giftAnimationQueue = [];
  let giftAnimationPlaying = false;
  function playGiftAnimation(event) {
    if (window.BUFFMotion?.playGift && animationLayer) {
      window.BUFFMotion.playGift(animationLayer, event);
      return;
    }
    giftAnimationQueue.push(event);
    runGiftAnimationQueue();
  }
  function runGiftAnimationQueue() {
    if (giftAnimationPlaying || !giftAnimationQueue.length) return;
    giftAnimationPlaying = true;
    const event = giftAnimationQueue.shift();
    const wrap = document.createElement('div');
    const level = animationClass(event);
    wrap.className = `gift-burst gift-${level}`;
    const count = level === 'legendary' ? 52 : level === 'epic' ? 38 : level === 'rare' ? 26 : 16;
    const particles = Array.from({ length: count }, (_, i) => `<i style="--i:${i};--x:${(i % 11) - 5};--y:${Math.floor(i / 11) - 2}">${event.giftIcon || '✦'}</i>`).join('');
    const isHeart = /kalp|heart|love|❤️|💚|💖/i.test(`${event.giftName||''} ${event.giftIcon||''}`);
    const floatingHearts = isHeart ? Array.from({ length: Math.min(18, 8 + Number(event.quantity || 1)) }, (_, i) => `<span class="floating-heart" style="--i:${i}">${event.giftIcon || '❤️'}</span>`).join('') : '';
    const combo = Number(event.quantity || 1) > 1 ? `<b class="gift-combo">×${Number(event.quantity)}</b>` : '';
    wrap.innerHTML = `${particles}${floatingHearts}<div class="gift-scene-flare"></div><div class="gift-burst-card"><span class="gift-burst-icon">${event.giftIcon || '🎁'}</span>${combo}<div><small>${LogiApp.escapeHtml(event.senderName || 'İzleyici')}</small><strong>${LogiApp.escapeHtml(event.giftName || 'Hediye')} ${Number(event.quantity||1)>1?`×${Number(event.quantity)}`:''}</strong><em>${LogiApp.formatNumber(event.totalPrice)} coin destek</em></div></div>`;
    animationLayer.appendChild(wrap);
    requestAnimationFrame(() => wrap.classList.add('play'));
    if (event.audioUrl) {
      const audio = new Audio(event.audioUrl);
      audio.volume = 0.82;
      audio.play().catch(() => {});
    }
    const duration = level === 'legendary' ? 5200 : level === 'epic' ? 4400 : level === 'rare' ? 3400 : 2600;
    setTimeout(() => {
      wrap.classList.add('leaving');
      setTimeout(() => {
        wrap.remove();
        giftAnimationPlaying = false;
        runGiftAnimationQueue();
      }, 320);
    }, duration);
  }

  function acceptGiftEvent(event, animate = true) {
    const id = Number(event.eventId || event.id || 0);
    if(!Number.isSafeInteger(id)||id<1)return false;
    if (id && seenGiftIds.has(id)) return false;
    if (id) {
      seenGiftIds.add(id);
      // Polling owns its cursor; out-of-order realtime events must not skip rows.
    }
    if (animate) playGiftAnimation(event);
    return true;
  }

  async function loadStream() {
    const data = await LogiApp.api(`/streams.php?id=${streamId}`);
    stream = data.stream;
    document.getElementById('watchTitle').textContent = stream.title;
    document.getElementById('watchStreamer').textContent = stream.streamerName;
    document.getElementById('watchUsername').textContent = `@${stream.username}`;
    document.getElementById('watchCategory').textContent = stream.category;
    document.getElementById('watchDescription').textContent = stream.description || 'Yayıncı bu yayın için açıklama eklemedi.';
    const avatarRoot = document.getElementById('streamAvatar');
    if (stream.avatarUrl) avatarRoot.innerHTML = `<img src="${LogiApp.escapeHtml(stream.avatarUrl)}" alt="${LogiApp.escapeHtml(stream.streamerName)}">`; else avatarRoot.textContent = LogiApp.initials(stream.streamerName);
    document.getElementById('watchTags').innerHTML = (stream.tags || []).map(tag => `<span>${LogiApp.escapeHtml(tag)}</span>`).join('');
    document.getElementById('streamGiftTotal').textContent = `${LogiApp.formatNumber(stream.giftTotal)} coin`;
    const contentBadge = document.getElementById('contentRatingBadge');
    const safetyInfo = document.getElementById('streamSafetyInfo');
    const warnings = [];
    if (stream.adultOnly) warnings.push('+18 yayın');
    if (stream.matureLanguage) warnings.push('yoğun küfür / sert dil');
    contentBadge.classList.toggle('hidden', !stream.adultOnly);
    safetyInfo.classList.toggle('hidden', warnings.length === 0);
    safetyInfo.innerHTML = warnings.length ? warnings.map(item => `<span>${LogiApp.escapeHtml(item)}</span>`).join('') : '';
    const delay = Number(stream.delaySeconds || 0);
    document.getElementById('delayBadge').textContent = `⏱ ${delay} sn yayın gecikmesi`;
    document.getElementById('delayCount').textContent = String(delay);
    document.getElementById('delayText').textContent = delay ? `Bağlantı hemen kurulur; yayın içeriği ${delay} saniye gecikmeli oynatılır.` : 'Yayın anlık bağlantı modunda açılıyor.';
    viewerCountBadge.textContent = `◉ ${LogiApp.formatNumber(stream.viewerCount)}`;
    if (!stream.isLive) throw new Error('Bu yayın artık canlı değil.');
  }

  async function loadFollowStatus() {
    if (stream.userId === me.id) { followBtn.textContent = 'Kendi Kanalın'; followBtn.disabled = true; return; }
    try {
      const data = await LogiApp.api(`/follow/status.php?user_id=${stream.userId}`);
      followBtn.classList.toggle('following', data.following);
      followBtn.textContent = data.following ? '✓ Takip Ediliyor' : '+ Takip Et';
      followerCountBadge.textContent = `✦ ${LogiApp.formatNumber(data.followerCount)}`;
    } catch (_) {}
  }
  followBtn.addEventListener('click', async () => {
    if (!stream || stream.userId === me.id) return;
    try {
      const data = await LogiApp.api('/follow/toggle.php', { method: 'POST', body: JSON.stringify({ userId: stream.userId }) });
      followBtn.classList.toggle('following', data.following); followBtn.textContent = data.following ? '✓ Takip Ediliyor' : '+ Takip Et'; followerCountBadge.textContent = `✦ ${LogiApp.formatNumber(data.followerCount)}`;
      LogiApp.toast(data.following ? 'Yayıncı takip edildi.' : 'Takipten çıkarıldı.', 'success');
    } catch (error) { LogiApp.toast(error.message, 'error'); }
  });
  document.getElementById('shareBtn').addEventListener('click', () => LogiApp.copyText(window.location.href));

  document.getElementById('reportForm').addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.currentTarget;
    const msg = document.getElementById('reportMessage');
    try {
      const formData = Object.fromEntries(new FormData(form).entries());
      const targetUserId = Number(formData.targetUserId || stream.userId); const chatMessageId = Number(formData.chatMessageId || 0);
      const result = await LogiApp.api('/reports/create.php', { method: 'POST', body: JSON.stringify({ ...formData, streamId, targetUserId, chatMessageId, scope: formData.scope || 'creator' }) });
      msg.className = 'form-message success'; msg.textContent = result.message; form.reset();
      setTimeout(() => LogiApp.closeModal('reportModal'), 1000);
    } catch (error) { msg.className = 'form-message error'; msg.textContent = error.message; }
  });

  async function loadGiftCatalog() {
    const data = await LogiApp.api('/gifts/catalog.php');
    giftCatalogEl.innerHTML = data.gifts.map(gift => `<button class="gift-shop-card" data-id="${gift.id}" data-price="${gift.price}" type="button"><span>${gift.icon}</span><strong>${LogiApp.escapeHtml(gift.name)}</strong><small>${LogiApp.formatNumber(gift.price)} coin</small></button>`).join('');
    giftCatalogEl.querySelectorAll('[data-id]').forEach(btn => btn.addEventListener('click', () => { giftCatalogEl.querySelectorAll('.gift-shop-card').forEach(x => x.classList.remove('selected')); btn.classList.add('selected'); selectedGift = { id: Number(btn.dataset.id), price: Number(btn.dataset.price), name: btn.querySelector('strong').textContent, icon: btn.querySelector('span').textContent }; document.getElementById('giftSelectionSummary').textContent=`1 × ${selectedGift.name} · ${LogiApp.formatNumber(selectedGift.price)} coin`; }));
    giftCatalogEl.querySelector('[data-id]')?.click();
  }
  function aggregateGiftEvents(events = []) { const map = new Map(); events.forEach(event => { const key = `${event.giftId}|${event.giftName}|${event.giftIcon}`; if (!map.has(key)) map.set(key, { ...event, quantity: 0, totalPrice: 0, senders: new Set(), latest: event.createdAt }); const item = map.get(key); item.quantity += Number(event.quantity || 0); item.totalPrice += Number(event.totalPrice || 0); item.senders.add(event.senderName); if (new Date(event.createdAt) > new Date(item.latest)) item.latest = event.createdAt; }); return [...map.values()].sort((a,b) => new Date(b.latest) - new Date(a.latest)); }
  function renderGiftEvents(events) { const rows = aggregateGiftEvents(events).slice(0, 10); giftEventsEl.innerHTML = rows.length ? rows.map(event => `<div class="gift-feed-item gift-summary-item"><span>${event.giftIcon}</span><div><strong>${LogiApp.escapeHtml(event.giftName)} ×${event.quantity}</strong><small>${event.senders.size} gönderici • ${LogiApp.escapeHtml(LogiApp.timeAgo(event.latest))}</small></div><em>+${LogiApp.formatNumber(event.totalPrice)}</em></div>`).join('') : '<div class="rail-empty">Henüz hediye yok.</div>'; }
  async function pollGifts(initial = false) {
    if(giftPollBusy)return;giftPollBusy=true;
    try {
      if (initial || !hasLoadedGifts) {
        const data = await LogiApp.api(`/gifts/events.php?stream_id=${streamId}`);
        const events = data.events || [];
        renderGiftEvents(events);
        events.forEach(e => {acceptGiftEvent(e, false);lastGiftId=Math.max(lastGiftId,Number(e.eventId||e.id)||0);});
        hasLoadedGifts = true;
        return;
      }
      const data = await LogiApp.api(`/gifts/events.php?stream_id=${streamId}&after_id=${lastGiftId}`);
      const newEvents = data.events || [];
      if (newEvents.length) {
        newEvents.forEach(event => {acceptGiftEvent(event,true);lastGiftId=Math.max(lastGiftId,Number(event.eventId||event.id)||0);});
        const full = await LogiApp.api(`/gifts/events.php?stream_id=${streamId}`);
        renderGiftEvents(full.events || []);
      }
    } catch (_) {} finally {giftPollBusy=false;}
  }
  const giftAudioUploads = new WeakMap();
  document.getElementById('giftQuantity').value = '1';
  document.getElementById('giftQuantity').readOnly = true;
  ['quantityMinus','quantityPlus'].forEach(id=>document.getElementById(id).hidden=true);
  document.getElementById('sendGiftBtn').addEventListener('click', async e => {
    const button=e.currentTarget;if(!selectedGift||button.disabled||e.detail>1)return;
    const chosen={...selectedGift};button.disabled=true;button.setAttribute('aria-busy','true');
    try {
      let audioUrl=null,audioName=null;const audioInput=document.getElementById('giftAudioInput'),file=audioInput?.files?.[0];
      if(file){let upload=giftAudioUploads.get(file);if(!upload){const fd=new FormData();fd.append('audio',file);upload=await LogiApp.api('/gifts/audio-upload.php',{method:'POST',body:fd});giftAudioUploads.set(file,upload);}audioUrl=upload.audioUrl;audioName=upload.audioName;}
      const response=await BUFFV17.mutation('/gifts/send.php',{streamId,giftId:chosen.id,quantity:1,audioUrl,audioName},'live-gift');
      if(!response.duplicate)acceptGiftEvent(response.event,true);
      document.getElementById('giftMessage').textContent=response.message;
      LogiApp.toast(response.message,'success');if(audioInput)audioInput.value='';document.getElementById('giftAudioPreview')?.classList.add('hidden');
      await pollGifts(true);
    }catch(error){document.getElementById('giftMessage').textContent=error.message;LogiApp.toast(error.message,'error');}finally{button.removeAttribute('aria-busy');setTimeout(()=>{button.disabled=false;},800);}
  });
  document.getElementById('sendGiftBtn').addEventListener('keydown',e=>{if(e.repeat&&(e.key==='Enter'||e.key===' '))e.preventDefault();});


  const giftAudioInput = document.getElementById('giftAudioInput');
  giftAudioInput?.addEventListener('change', () => {
    const file = giftAudioInput.files?.[0];
    const preview = document.getElementById('giftAudioPreview');
    const player = document.getElementById('giftAudioPlayer');
    if (!file) return preview?.classList.add('hidden');
    if (file.size > 8 * 1024 * 1024) { giftAudioInput.value=''; LogiApp.toast('Ses dosyası en fazla 8 MB olabilir.','error'); return; }
    player.src = URL.createObjectURL(file); preview.classList.remove('hidden');
  });
  document.getElementById('removeGiftAudio')?.addEventListener('click', () => { giftAudioInput.value=''; const p=document.getElementById('giftAudioPlayer'); if(p) p.removeAttribute('src'); document.getElementById('giftAudioPreview')?.classList.add('hidden'); });

  function applyViewerDelay(nextDelay, announce = false) {
    const delay = Math.max(0, Number(nextDelay || 0));
    if (stream) stream.delaySeconds = delay;
    document.getElementById('delayBadge').textContent = delay ? `⏱ ${delay} sn yayın gecikmesi` : '⏱ Gecikmesiz';
    document.getElementById('delayCount').textContent = String(delay);
    document.getElementById('delayText').textContent = delay ? `Bağlantı anlık; yayın içeriği ${delay} saniye gecikmeli oynatılıyor.` : 'Yayın anlık bağlantı modunda oynatılıyor.';
    try {
      peer?.getReceivers?.().forEach(receiver => {
        if (receiver.track?.kind !== 'video' && receiver.track?.kind !== 'audio') return;
        if ('playoutDelayHint' in receiver) receiver.playoutDelayHint = delay;
        if ('jitterBufferTarget' in receiver) receiver.jitterBufferTarget = delay * 1000;
      });
    } catch (_) {}
    if (remoteVideo.srcObject) {
      playerStatus.textContent = delay ? `CANLI • ${delay} sn gecikmeli` : 'CANLI • anlık';
      playerStatus.classList.add('connected');
    }
    if (announce) LogiApp.toast(delay ? `Yayıncı gecikmeyi ${delay} saniye yaptı.` : 'Yayıncı gecikmeyi kapattı.', 'success', 2600);
  }

  function createPeer() {
    peer = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
    peer.ontrack = event => {
      remoteVideo.srcObject = event.streams[0];
      applyViewerDelay(Number(stream?.delaySeconds || 0), false);
      remoteVideo.play().catch(() => {});
      delayOverlay.classList.add('hidden');
    };
    peer.onicecandidate = async event => { if (event.candidate && stream) await LogiApp.api('/signal/send.php', { method: 'POST', body: JSON.stringify({ streamId, recipientUserId: stream.userId, type: 'candidate', payload: event.candidate.toJSON() }) }).catch(() => {}); };
  }
  async function startViewerConnection() {
    const delay = Number(stream?.delaySeconds || 0);
    delayCount.textContent = String(delay);
    createPeer();
    const offer = await peer.createOffer({ offerToReceiveVideo: true, offerToReceiveAudio: true });
    await peer.setLocalDescription(offer);
    await LogiApp.api('/signal/send.php', { method: 'POST', body: JSON.stringify({ streamId, recipientUserId: stream.userId, type: 'offer', payload: offer }) });
    playerStatus.textContent = delay ? `Yayıncıya bağlanılıyor • ${delay} sn yayın gecikmesi` : 'Yayıncıya bağlanılıyor...';
  }
  async function pollSignals() { try { const data = await LogiApp.api(`/signal/poll.php?stream_id=${streamId}`); for (const msg of data.messages || []) { if (msg.type === 'answer' && peer && !peer.currentRemoteDescription) await peer.setRemoteDescription(new RTCSessionDescription(msg.payload)); else if (msg.type === 'candidate' && peer) { try { await peer.addIceCandidate(new RTCIceCandidate(msg.payload)); } catch (_) {} } else if (msg.type === 'delay') { applyViewerDelay(Number(msg.payload?.delaySeconds || 0), true); } } } catch (_) {} }
  async function heartbeat() { try { const data = await LogiApp.api('/stream/viewer-heartbeat.php', { method: 'POST', body: JSON.stringify({ streamId }) }); viewerCountBadge.textContent = `◉ ${LogiApp.formatNumber(data.viewerCount)}`; } catch (_) {} }
  let endedReplayTimer = null;

  async function checkEndedReplay() {
    try {
      const data = await LogiApp.api(`/recordings/list.php?stream_id=${streamId}&limit=1`);
      const replay = (data.recordings || [])[0];
      const btn = document.getElementById('openEndedReplayBtn');
      const hint = document.getElementById('endedReplayHint');
      if (replay?.id) {
        if (btn) { btn.href = `./replay.php?id=${replay.id}`; btn.classList.remove('hidden'); }
        if (hint) hint.textContent = 'Yayın tekrarı hazır. İstersen hemen izleyebilirsin.';
        if (endedReplayTimer) { clearInterval(endedReplayTimer); endedReplayTimer = null; }
      } else if (hint) {
        hint.textContent = 'Bu yayın için kayıt açıksa tekrar birkaç saniye içinde burada görünecek.';
      }
    } catch (_) {}
  }

  function handleStreamEnded() {
    if (endingRedirect) return;
    endingRedirect = true;
    [signalTimer, heartbeatTimer, chatTimer, giftTimer, statusTimer].forEach(id => id && clearInterval(id));
    if (peer) { try { peer.close(); } catch (_) {} }
    playerStatus.textContent = 'Yayın sona erdi.';
    playerStatus.classList.remove('connected');
    const modal = document.getElementById('streamEndedModal');
    modal?.classList.add('open');
    document.body.classList.add('modal-open');
    LogiApp.toast('Yayıncı canlı yayını bitirdi.', 'success', 3500);
    checkEndedReplay();
    endedReplayTimer = setInterval(checkEndedReplay, 2200);
  }

  document.getElementById('stayOnEndedStreamBtn')?.addEventListener('click', () => {
    document.getElementById('streamEndedModal')?.classList.remove('open');
    document.body.classList.remove('modal-open');
    playerStatus.textContent = 'Yayın sona erdi • bu sayfada kalıyorsun';
  });

  async function pollStreamStatus() {
    if (endingRedirect) return;
    try {
      const data = await LogiApp.api(`/streams.php?id=${streamId}`);
      if (data.stream?.isLive) {
        const nextDelay = Number(data.stream.delaySeconds || 0);
        const previousDelay = Number(stream?.delaySeconds || 0);
        if (nextDelay !== previousDelay) applyViewerDelay(nextDelay, true);
        stream = {...stream, ...data.stream};
      }
      if (!data.stream?.isLive) handleStreamEnded();
    } catch (_) {}
  }

  window.addEventListener('buff:realtime', event => {
    const msg = event.detail || {};
    if (msg.event === 'stream.ended' && Number(msg.streamId || msg.payload?.streamId || 0) === streamId) {
      handleStreamEnded();
      return;
    }
    if (msg.event !== 'gift.sent') return;
    const payload = msg.payload || {};
    if (Number(payload.streamId || msg.streamId || 0) !== streamId) return;
    const giftEvent = {
      id: Number(payload.eventId || payload.id || 0),
      eventId: Number(payload.eventId || payload.id || 0),
      giftId: Number(payload.giftId || 0),
      giftName: payload.giftName || 'Hediye',
      giftIcon: payload.giftIcon || '🎁',
      quantity: Number(payload.quantity || 1),
      totalPrice: Number(payload.totalPrice || 0),
      senderName: payload.senderName || 'İzleyici',
      audioUrl: payload.audioUrl || null,
      audioName: payload.audioName || null,
      createdAt: payload.createdAt || new Date().toISOString()
    };
    if (acceptGiftEvent(giftEvent, true)) {
      pollGifts(true).catch(() => {});
      const total = document.getElementById('streamGiftTotal');
      if (total && stream) {
        stream.giftTotal = Number(stream.giftTotal || 0) + Number(giftEvent.totalPrice || 0);
        total.textContent = `${LogiApp.formatNumber(stream.giftTotal)} coin`;
      }
    }
  });

  try {
    await loadStream(); await enforceAgeGate(); myWallet.textContent = LogiApp.formatNumber(me.walletBalance); await Promise.all([loadFollowStatus(), loadGiftCatalog(), pollGifts(true), pollChat(true), loadModerationPermissions(), heartbeat()]);
    startViewerConnection().catch(error => { playerStatus.textContent = error.message; });
    signalTimer = setInterval(pollSignals, 900); heartbeatTimer = setInterval(heartbeat, 5000); chatTimer = setInterval(pollChat, 1200); giftTimer = setInterval(() => pollGifts(false), 900); statusTimer = setInterval(pollStreamStatus, 1500);
  } catch (error) { playerStatus.textContent = error.message; delayOverlay.classList.add('hidden'); }
  window.addEventListener('beforeunload', () => { [signalTimer, heartbeatTimer, chatTimer, giftTimer, statusTimer, endedReplayTimer].forEach(id => id && clearInterval(id)); if (peer) peer.close(); });
});

document.addEventListener('click', e => {
  const btn = e.target.closest?.('[data-paytr-coins]');
  if (btn) window.location.href = `./payment.php?coins=${encodeURIComponent(btn.dataset.paytrCoins)}`;
});
