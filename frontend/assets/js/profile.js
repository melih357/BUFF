document.addEventListener('DOMContentLoaded', async () => {
  const me = await LogiApp.getCurrentUser();
  if (!LogiApp.requireLoggedIn(me)) return;

  const form = document.getElementById('profileForm');
  const msg = document.getElementById('profileMessage');

  function message(text, ok = false, target = msg) {
    target.className = `form-message ${ok ? 'success' : 'error'}`;
    target.textContent = text;
  }

  function statusLabel(status) {
    const map = { pending: 'Bekliyor', approved: 'Onaylandı', rejected: 'Reddedildi', refunded: 'İade edildi', wallet_credited: 'Cüzdana geçti', bank_pending: 'Bankaya aktarım bekliyor' };
    return map[status] || status;
  }

  function renderAvatar(user) {
    const root = document.getElementById('profileAvatar');
    if (user.avatarUrl) root.innerHTML = `<img src="${LogiApp.escapeHtml(user.avatarUrl)}" alt="${LogiApp.escapeHtml(user.displayName)}">`;
    else root.textContent = LogiApp.initials(user.displayName || user.username);
  }

  function renderBadges(user) {
    const badges = `${user.isOg ? '<span class="user-badge badge-og">OG</span>' : ''}${user.isVip ? `<span class="user-badge badge-vip badge-${LogiApp.escapeHtml(user.vipTier || 'plus')}">${LogiApp.escapeHtml(String(user.vipTier || 'plus').toUpperCase())}</span>` : ''}`;
    document.getElementById('profileBadges').innerHTML = badges;
  }

  function renderTransactions(rows) {
    const root = document.getElementById('transactionList');
    root.innerHTML = rows.length ? rows.map(tx => `<div class="activity-item compact"><div class="activity-copy"><strong>${LogiApp.escapeHtml(tx.description)}</strong><span>${tx.amount > 0 ? '+' : ''}${LogiApp.formatNumber(tx.amount)} coin</span><small>${LogiApp.escapeHtml(LogiApp.timeAgo(tx.createdAt))}</small></div></div>`).join('') : '<div class="empty-state compact-empty"><strong>Henüz işlem yok.</strong></div>';
  }

  function groupReceivedGifts(gifts = []) {
    const out=[];
    gifts.forEach(g=>{const key=`${g.senderUsername}|${g.giftName}`;const last=out[out.length-1];if(last&&last._key===key){last.quantity+=Number(g.quantity||0);last.totalPrice+=Number(g.totalPrice||0);last.eventCount+=1;return;}out.push({...g,_key:key,eventCount:1});});
    return out;
  }

  function receivedGiftCard(g) {
    const av=g.senderAvatarUrl?`<span class="support-avatar"><img src="${LogiApp.escapeHtml(g.senderAvatarUrl)}" alt=""></span>`:`<span class="support-avatar">${LogiApp.initials(g.senderName||'?')}</span>`;
    return `<article class="support-card">${av}<div class="support-card-copy"><strong>${LogiApp.escapeHtml(g.senderName)}</strong><span>${LogiApp.escapeHtml(g.giftName)} <b>×${g.quantity}</b></span><small>@${LogiApp.escapeHtml(g.senderUsername)} • ${LogiApp.escapeHtml(LogiApp.timeAgo(g.createdAt))}</small></div><div class="support-card-value"><i>${g.giftIcon}</i><strong>+${LogiApp.formatNumber(g.totalPrice)}</strong><small>coin</small>${g.audioUrl?`<button type="button" data-audio="${LogiApp.escapeHtml(g.audioUrl)}">♫</button>`:''}</div></article>`;
  }

  function renderReceivedGifts(gifts, totalCount) {
    const grouped=groupReceivedGifts(gifts);
    const root=document.getElementById('receivedGiftList');
    document.getElementById('giftListCount').textContent=`${LogiApp.formatNumber(totalCount)} kayıt`;
    root.innerHTML=grouped.length?grouped.slice(0,9).map(receivedGiftCard).join(''):'<div class="empty-state compact-empty full-span"><strong>Henüz hediye almadın.</strong></div>';
    const modal=document.getElementById('receivedGiftModalList');if(modal)modal.innerHTML=grouped.length?grouped.map(receivedGiftCard).join(''):'<div class="empty-state compact-empty full-span"><strong>Henüz hediye almadın.</strong></div>';
    const allBtn=document.getElementById('allReceivedGiftsBtn');if(allBtn)allBtn.hidden=grouped.length<=9;
  }

  function renderCommunity(data) {
    const goal=document.getElementById('profileCommunityGoal'),g=data.communityGoal;
    if(goal)goal.innerHTML=g?`<div class="goal-head"><strong>${LogiApp.escapeHtml(g.title)}</strong><span>%${g.progress}</span></div><div class="community-progress"><i style="width:${Math.max(0,Math.min(100,Number(g.progress)||0))}%"></i></div><div class="goal-values"><span>${LogiApp.formatNumber(g.currentAmount)} coin</span><span>${LogiApp.formatNumber(g.targetAmount)} coin hedef</span></div>`:'<div class="community-empty">Aktif topluluk hedefin yok.</div>';
    const lb=document.getElementById('profileTopSupporters');if(lb)lb.innerHTML=(data.topSupporters||[]).length?(data.topSupporters||[]).slice(0,5).map((x,i)=>`<div class="leader-row"><span class="leader-rank">${i+1}</span>${x.avatarUrl?`<span class="leader-avatar"><img src="${LogiApp.escapeHtml(x.avatarUrl)}" alt=""></span>`:`<span class="leader-avatar">${LogiApp.initials(x.displayName||'?')}</span>`}<div><strong>${LogiApp.escapeHtml(x.displayName)}</strong><small>@${LogiApp.escapeHtml(x.username)}</small></div><b>${LogiApp.formatNumber(x.totalCoins)} coin</b></div>`).join(''):'<div class="community-empty">Henüz destekçi yok.</div>';
  }

  function renderSentGifts(gifts) {
    const root = document.getElementById('sentGiftList');
    root.innerHTML = gifts.length ? gifts.map(g => `<div class="activity-item"><div class="activity-icon">${g.giftIcon}</div><div class="activity-copy"><strong>${LogiApp.escapeHtml(g.recipientName)} • ${LogiApp.escapeHtml(g.giftName)}</strong><span>x${g.quantity} • ${LogiApp.formatNumber(g.totalPrice)} coin</span><small>${LogiApp.escapeHtml(LogiApp.timeAgo(g.createdAt))}</small></div><div class="activity-actions">${!g.refundRequestId ? `<button class="btn btn-ghost btn-small" type="button" data-request-refund="${g.id}">İade Talebi</button>` : `<span class="soft-pill">${statusLabel(g.refundStatus || 'pending')}</span>`}</div></div>`).join('') : '<div class="empty-state compact-empty"><strong>Henüz hediye göndermedin.</strong></div>';
  }

  function renderStreamCard(stream) {
    const root = document.getElementById('myStreamCard');
    if (!stream) {
      root.className = 'empty-state compact-empty';
      root.innerHTML = '<strong>Henüz kanal yayını oluşturulmadı</strong><span>Stüdyodan ilk yayınını başlatabilirsin.</span>';
      return;
    }
    root.className = 'channel-status-card';
    root.innerHTML = `<div class="channel-status-top"><span class="${stream.isLive ? 'live-chip' : 'subtle-pill'}">${stream.isLive ? 'CANLI' : 'ÇEVRİMDIŞI'}</span><span>${LogiApp.formatNumber(stream.viewerCount)} izleyici</span></div><strong>${LogiApp.escapeHtml(stream.title)}</strong><small>${LogiApp.escapeHtml(stream.category)}</small><div class="channel-status-actions">${stream.isLive ? `<a class="btn btn-ghost" href="./watch.php?id=${stream.id}">Yayını Gör</a>` : ''}<a class="btn btn-primary" href="./studio.php">Stüdyoya Git</a></div>`;
  }

  function renderState(data) {
    const info = data.user;
    document.getElementById('displayName').value = info.displayName || '';
    document.getElementById('bio').value = info.bio || '';
    document.getElementById('walletBalance').textContent = LogiApp.formatNumber(info.walletBalance);
    document.getElementById('giftIncome').textContent = LogiApp.formatNumber(info.totalGiftsReceived);
    document.getElementById('bankPending').textContent = LogiApp.formatNumber(data.bankPendingEarnings || 0);
    document.getElementById('refundCount').textContent = LogiApp.formatNumber(data.pendingRefundCount || 0);
    document.getElementById('followerCount').textContent = LogiApp.formatNumber(data.followerCount || 0);
    document.getElementById('profileDisplayTitle').textContent = info.displayName || info.username;
    document.getElementById('profileUsername').textContent = `@${info.username}`;
    const pid=document.getElementById('profilePublicId'); if(pid) pid.textContent=info.publicId||'';
    document.getElementById('profileBioPreview').textContent = info.bio || 'Biyografi eklenmedi.';
    document.getElementById('streamStatus').textContent = data.stream?.isLive ? 'Yayında' : 'Pasif';
    document.getElementById('streamViewerSummary').textContent = data.stream?.isLive ? `${LogiApp.formatNumber(data.stream.viewerCount)} kişi izliyor` : 'Şu anda yayında değilsin';
    renderAvatar(info);
    renderBadges(info);
    renderTransactions(data.transactions || []);
    renderReceivedGifts(data.receivedGifts || [], Number(data.receivedGiftCount || 0));
    renderSentGifts(data.sentGifts || []);
    renderCommunity(data);
    renderStreamCard(data.stream || null);
  }

  async function loadRefunds() {
    try {
      const data = await LogiApp.api('/refunds/list.php');
      const root = document.getElementById('incomingRefundList');
      const pending = (data.incoming || []).filter(r => r.status === 'pending');
      document.getElementById('pendingRefundBadge').textContent = `${pending.length} bekliyor`;
      root.innerHTML = (data.incoming || []).length ? data.incoming.map(r => `
        <div class="activity-item refund-item"><div class="activity-icon">${r.gift_icon}</div><div class="activity-copy"><strong>${LogiApp.escapeHtml(r.sender_name)} • ${LogiApp.escapeHtml(r.gift_name)}</strong><span>${LogiApp.formatNumber(r.total_price)} coin • “${LogiApp.escapeHtml(r.reason)}”</span><small>${LogiApp.escapeHtml(LogiApp.timeAgo(r.created_at))} • ${statusLabel(r.status)}</small></div><div class="activity-actions">${r.status === 'pending' ? `<button class="btn btn-primary btn-small" data-refund-accept="${r.id}">Kabul</button><button class="btn btn-ghost btn-small" data-refund-reject="${r.id}">Reddet</button>` : `<span class="soft-pill">${statusLabel(r.status)}</span>`}</div></div>`).join('') : '<div class="empty-state compact-empty"><strong>İade talebi yok.</strong></div>';
      root.querySelectorAll('[data-refund-accept],[data-refund-reject]').forEach(btn => btn.addEventListener('click', async () => {
        const decision = btn.hasAttribute('data-refund-accept') ? 'accept' : 'reject';
        const requestId = Number(btn.dataset.refundAccept || btn.dataset.refundReject);
        const note = window.prompt(decision === 'accept' ? 'İade notu (isteğe bağlı):' : 'Red nedeni (isteğe bağlı):', '') || '';
        try {
          const res = await LogiApp.api('/refunds/respond.php', { method: 'POST', body: JSON.stringify({ requestId, decision, note }) });
          LogiApp.toast(res.message, 'success');
          await loadAll();
        } catch (error) { LogiApp.toast(error.message, 'error'); }
      }));
    } catch (_) {}
  }

  async function loadProfile() {
    try { renderState(await LogiApp.api('/profile/get.php')); }
    catch (error) { message(error.message); }
  }

  async function loadAll() {
    await Promise.all([loadProfile(), loadRefunds()]);
  }

  form.addEventListener('submit', async e => {
    e.preventDefault();
    try {
      await LogiApp.api('/profile/update.php', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(form).entries())) });
      message('Profil bilgilerin güncellendi.', true);
      await loadProfile();
      await LogiApp.renderAuthActions();
    } catch (error) { message(error.message); }
  });

  document.getElementById('avatarForm').addEventListener('submit', async e => {
    e.preventDefault();
    const target = document.getElementById('avatarMessage');
    try {
      const fd = new FormData(e.currentTarget);
      await LogiApp.api('/profile/avatar.php', { method: 'POST', body: fd });
      message('Profil fotoğrafı güncellendi.', true, target);
      await loadProfile();
      await LogiApp.renderAuthActions();
      setTimeout(() => LogiApp.closeModal('avatarModal'), 700);
    } catch (error) { message(error.message, false, target); }
  });

  document.getElementById('refundRequestForm').addEventListener('submit', async e => {
    e.preventDefault();
    const target = document.getElementById('refundRequestMessage');
    try {
      const res = await LogiApp.api('/refunds/request.php', { method: 'POST', body: JSON.stringify({ giftEventId: Number(document.getElementById('refundGiftEventId').value), reason: document.getElementById('refundReason').value }) });
      message(res.message, true, target);
      await loadAll();
      setTimeout(() => LogiApp.closeModal('refundRequestModal'), 700);
    } catch (error) { message(error.message, false, target); }
  });

  document.getElementById('allReceivedGiftsBtn')?.addEventListener('click',()=>LogiApp.openModal('receivedGiftsModal'));

  document.addEventListener('click', e => {
    const audioBtn = e.target.closest('[data-audio]');
    if (audioBtn) new Audio(audioBtn.dataset.audio).play().catch(() => {});
    const refundBtn = e.target.closest('[data-request-refund]');
    if (refundBtn) {
      document.getElementById('refundGiftEventId').value = refundBtn.dataset.requestRefund;
      document.getElementById('refundReason').value = '';
      document.getElementById('refundRequestMessage').className = 'form-message';
      document.getElementById('refundRequestMessage').textContent = '';
      LogiApp.openModal('refundRequestModal');
    }
  });

  await loadAll();
});
