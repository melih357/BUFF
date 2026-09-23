document.addEventListener('DOMContentLoaded', async () => {
  const me = await LogiApp.getCurrentUser();
  if (!LogiApp.requireLoggedIn(me)) return;

  const tabs = [...document.querySelectorAll('[data-settings-tab]')];
  const panels = [...document.querySelectorAll('[data-settings-panel]')];
  let settingsData = null;
  let selectedThemeMode = 'dark';
  let selectedAccent = 'buff';
  let selectedDelay = 5;

  const accents = {
    buff: ['BUFF', '#e83427'], indigo: ['İndigo', '#4f5bd5'], graphite: ['Grafit', '#525866'], ocean: ['Okyanus', '#147d92'], forest: ['Orman', '#2f7d56'], rose: ['Gül', '#b54e68'], amber: ['Kehribar', '#b36a1f'], violet: ['Viyole', '#7652b8']
  };

  function moneyTRY(value) {
    return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(Number(value || 0));
  }

  function cardDigits(value) { return String(value || '').replace(/\D/g, '').slice(0, 19); }
  function luhnValid(value) {
    const digits = cardDigits(value);
    if (digits.length < 13) return false;
    let sum = 0, flip = false;
    for (let i = digits.length - 1; i >= 0; i--) {
      let n = Number(digits[i]);
      if (flip) { n *= 2; if (n > 9) n -= 9; }
      sum += n; flip = !flip;
    }
    return sum % 10 === 0;
  }
  function cardBrand(value) {
    const n = cardDigits(value);
    if (/^4/.test(n)) return 'visa';
    if (/^(5[1-5]|2[2-7])/.test(n)) return 'mastercard';
    if (/^3[47]/.test(n)) return 'amex';
    if (/^9792/.test(n)) return 'troy';
    return 'card';
  }
  function parseExpiry(value) {
    const m = String(value || '').trim().match(/^(0?[1-9]|1[0-2])\s*[\/-]\s*(\d{2}|\d{4})$/);
    if (!m) return null;
    const month = Number(m[1]); let year = Number(m[2]); if (year < 100) year += 2000;
    const now = new Date();
    if (year < now.getFullYear() || (year === now.getFullYear() && month < now.getMonth() + 1)) return null;
    return { month, year };
  }
  function renderPaymentMethods(methods = []) {
    const root = document.getElementById('savedPaymentMethods');
    if (!root) return;
    root.innerHTML = methods.length ? methods.map(card => `
      <article class="saved-card-row">
        <div class="saved-card-brand"><span>${card.brand === 'visa' ? 'VISA' : card.brand === 'mastercard' ? 'MC' : card.brand === 'troy' ? 'TROY' : 'CARD'}</span></div>
        <div><strong>${LogiApp.escapeHtml(card.cardHolder)}</strong><span>${LogiApp.escapeHtml(card.masked || `•••• •••• •••• ${card.last4}`)}</span><small>${String(card.expMonth).padStart(2,'0')}/${card.expYear}${card.isDefault ? ' • Varsayılan' : ''}</small></div>
        <button class="btn btn-ghost btn-small" type="button" data-delete-payment-method="${card.id}">Kaldır</button>
      </article>`).join('') : '<div class="empty-state compact-empty"><strong>Henüz kart kaydedilmedi.</strong></div>';
    root.querySelectorAll('[data-delete-payment-method]').forEach(btn => btn.addEventListener('click', async () => {
      try {
        const res = await LogiApp.api('/payment-methods/delete.php', {method:'POST',body:JSON.stringify({id:Number(btn.dataset.deletePaymentMethod)})});
        LogiApp.toast(res.message,'success');
        await loadCore();
      } catch (error) { LogiApp.toast(error.message,'error'); }
    }));
  }

  function msg(id, text, ok = false) {
    const el = document.getElementById(id);
    if (!el) return;
    el.className = `form-message ${ok ? 'success' : 'error'}`;
    el.textContent = text;
  }

  function openTab(name, updateHash = true) {
    tabs.forEach(btn => btn.classList.toggle('active', btn.dataset.settingsTab === name));
    panels.forEach(panel => panel.classList.toggle('active', panel.dataset.settingsPanel === name));
    if (updateHash) history.replaceState(null, '', `#${name}`);
  }

  tabs.forEach(btn => btn.addEventListener('click', () => openTab(btn.dataset.settingsTab)));
  const initial = (location.hash || '').replace('#', '');
  if (initial && tabs.some(btn => btn.dataset.settingsTab === initial)) openTab(initial, false);

  function setThemeMode(mode) {
    selectedThemeMode = mode;
    const resolved = mode === 'system' ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : mode;
    document.documentElement.dataset.themeMode = mode;
    document.documentElement.dataset.theme = resolved;
    document.querySelectorAll('[data-theme-choice]').forEach(btn => btn.classList.toggle('active', btn.dataset.themeChoice === mode));
  }
  document.querySelectorAll('[data-theme-choice]').forEach(btn => btn.addEventListener('click', () => setThemeMode(btn.dataset.themeChoice)));

  function renderAccents(allowed = ['buff','indigo','graphite']) {
    const root = document.getElementById('accentChoices');
    root.innerHTML = Object.entries(accents).map(([id, info]) => `
      <button type="button" class="accent-choice ${selectedAccent===id?'active':''} ${allowed.includes(id)?'':'locked'}" data-accent-choice="${id}" ${allowed.includes(id)?'':'disabled'}>
        <span style="--swatch:${info[1]}"></span>
        <strong>${info[0]}</strong>
        <small>${allowed.includes(id) ? 'Kullanılabilir' : 'Üyelik gerekli'}</small>
      </button>`).join('');
    root.querySelectorAll('[data-accent-choice]:not([disabled])').forEach(btn => btn.addEventListener('click', () => {
      selectedAccent = btn.dataset.accentChoice;
      document.documentElement.dataset.accent = selectedAccent;
      localStorage.setItem('logi_accent', selectedAccent);
      renderAccents(allowed);
    }));
  }

  function renderDelay() {
    document.querySelectorAll('[data-delay]').forEach(btn => btn.classList.toggle('active', Number(btn.dataset.delay) === selectedDelay));
  }
  document.querySelectorAll('[data-delay]').forEach(btn => btn.addEventListener('click', () => { selectedDelay = Number(btn.dataset.delay); renderDelay(); }));

  async function loadCore() {
    settingsData = await LogiApp.api('/settings/get.php');
    const u = settingsData.user;
    document.getElementById('settingsDisplayName').value = u.displayName || '';
    document.getElementById('settingsUsername').value = u.username || '';
    document.getElementById('settingsBio').value = u.bio || '';
    document.getElementById('bioCounter').textContent = String((u.bio || '').length);
    document.getElementById('accountModeBadge').textContent = u.accountMode === 'creator' ? 'Yayıncı' : 'İzleyici';
    document.querySelectorAll('[data-account-mode]').forEach(btn => btn.classList.toggle('active', btn.dataset.accountMode === u.accountMode));

    document.getElementById('spendableCoins').textContent = LogiApp.formatNumber(u.walletBalance);
    document.getElementById('creatorEarnings').textContent = LogiApp.formatNumber(u.creatorEarnings);
    document.getElementById('creatorEarningsTry').textContent = `≈ ${moneyTRY(Number(u.creatorEarnings || 0) * Number(settingsData.creatorRate || 0.01))}`;

    selectedThemeMode = settingsData.preferences.themeMode || 'dark';
    selectedAccent = settingsData.preferences.accentTheme || 'buff';
    selectedDelay = Number(settingsData.preferences.defaultStreamDelay || 5);
    setThemeMode(selectedThemeMode);
    document.documentElement.dataset.accent = selectedAccent;
    document.getElementById('reduceMotionToggle').checked = !!settingsData.preferences.reduceMotion;
    renderAccents(settingsData.membership.allowedAccentThemes || ['buff','indigo', 'graphite']);
    renderDelay();
    document.getElementById('membershipThemeBadge').textContent = settingsData.membership.isVip ? String(settingsData.membership.tier || 'plus').toUpperCase() : 'Standart';
    document.getElementById('currentMembership').textContent = settingsData.membership.isVip ? String(settingsData.membership.tier || 'plus').toUpperCase() : 'Standart';
    document.getElementById('savedBankCard').innerHTML = settingsData.bankAccount
      ? `<strong>${LogiApp.escapeHtml(settingsData.bankAccount.accountHolder)}</strong><span>${LogiApp.escapeHtml(settingsData.bankAccount.maskedIban)}</span>`
      : 'Henüz çekim IBAN\'ı kaydedilmedi.';
    renderPaymentMethods(settingsData.paymentMethods || []);
    const phone = document.getElementById('settingsCardPhone');
    const address = document.getElementById('settingsCardAddress');
    if (phone && !phone.value) phone.value = settingsData.billingContact?.phone || '';
    if (address && !address.value) address.value = settingsData.billingContact?.address || '';
    updateWithdrawPreview();
  }

  document.getElementById('settingsBio').addEventListener('input', e => document.getElementById('bioCounter').textContent = String(e.target.value.length));

  document.getElementById('accountSettingsForm').addEventListener('submit', async e => {
    e.preventDefault();
    try {
      const data = Object.fromEntries(new FormData(e.currentTarget).entries());
      const res = await LogiApp.api('/settings/profile.php', { method: 'POST', body: JSON.stringify(data) });
      msg('accountMessage', res.message, true);
      await loadCore();
      await LogiApp.renderAuthActions();
    } catch (error) { msg('accountMessage', error.message); }
  });

  document.querySelectorAll('[data-account-mode]').forEach(btn => btn.addEventListener('click', async () => {
    if (btn.dataset.accountMode === 'creator' && settingsData?.user && !settingsData.user.creatorEnabled) { LogiApp.openModal('creatorOnboardingModal'); return; }
    try {
      const res = await LogiApp.api('/account/mode.php', { method: 'POST', body: JSON.stringify({ mode: btn.dataset.accountMode }) });
      msg('accountModeMessage', res.message, true);
      await loadCore();
      await LogiApp.renderAuthActions();
    } catch (error) { msg('accountModeMessage', error.message); }
  }));

  async function savePreferences() {
    const reduceMotion = document.getElementById('reduceMotionToggle').checked;
    const payload = {
      themeMode: selectedThemeMode,
      accentTheme: selectedAccent,
      sidebarCollapsed: localStorage.getItem('logi_sidebar_collapsed') === '1',
      defaultStreamDelay: selectedDelay,
      reduceMotion
    };
    const res = await LogiApp.api('/settings/preferences.php', { method: 'POST', body: JSON.stringify(payload) });
    localStorage.setItem('logi_theme_mode', selectedThemeMode);
    localStorage.setItem('logi_accent', selectedAccent);
    localStorage.setItem('logi_reduce_motion', reduceMotion ? '1' : '0');
    document.documentElement.dataset.reduceMotion = reduceMotion ? '1' : '0';
    return res;
  }

  document.getElementById('saveAppearanceBtn').addEventListener('click', async () => {
    try { const res = await savePreferences(); msg('appearanceMessage', res.message, true); }
    catch (error) { msg('appearanceMessage', error.message); }
  });
  document.getElementById('saveStreamSettingsBtn').addEventListener('click', async () => {
    try { await savePreferences(); msg('streamSettingsMessage', `Varsayılan yayın gecikmesi ${selectedDelay} saniye olarak kaydedildi.`, true); }
    catch (error) { msg('streamSettingsMessage', error.message); }
  });

  document.getElementById('bankSettingsForm').addEventListener('submit', async e => {
    e.preventDefault();
    try {
      const data = Object.fromEntries(new FormData(e.currentTarget).entries());
      const res = await LogiApp.api('/bank/save.php', { method: 'POST', body: JSON.stringify(data) });
      msg('bankMessage', res.message, true);
      await loadCore();
    } catch (error) { msg('bankMessage', error.message); }
  });

  const cardNumberInput = document.getElementById('settingsCardNumber');
  const cardExpiryInput = document.getElementById('settingsCardExpiry');
  cardNumberInput?.addEventListener('input', e => {
    const digits = cardDigits(e.target.value);
    e.target.value = digits.replace(/(.{4})/g, '$1 ').trim();
  });
  cardExpiryInput?.addEventListener('input', e => {
    let digits = String(e.target.value || '').replace(/\D/g, '').slice(0, 6);
    if (digits.length > 2) digits = `${digits.slice(0,2)}/${digits.slice(2)}`;
    e.target.value = digits;
  });
  document.getElementById('cardSettingsForm')?.addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.currentTarget;
    const fd = new FormData(form);
    const number = cardDigits(fd.get('cardNumber'));
    const cvv = String(fd.get('cvv') || '').replace(/\D/g,'');
    const expiry = parseExpiry(fd.get('expiry'));
    if (!luhnValid(number)) { msg('cardMessage','Kart numarası geçerli görünmüyor.'); return; }
    if (!expiry) { msg('cardMessage','Son kullanma tarihini AA/YYYY biçiminde gir.'); return; }
    if (!/^\d{3,4}$/.test(cvv)) { msg('cardMessage','CVV 3 veya 4 haneli olmalı.'); return; }
    const payload = {
      cardHolder: String(fd.get('cardHolder') || '').trim(),
      brand: cardBrand(number),
      last4: number.slice(-4),
      expMonth: expiry.month,
      expYear: expiry.year,
      phone: String(fd.get('phone') || '').trim(),
      address: String(fd.get('address') || '').trim()
    };
    try {
      const res = await LogiApp.api('/payment-methods/save.php',{method:'POST',body:JSON.stringify(payload)});
      msg('cardMessage',res.message,true);
      form.querySelector('[name="cardNumber"]').value='';
      form.querySelector('[name="cvv"]').value='';
      await loadCore();
    } catch (error) { msg('cardMessage',error.message); }
  });

  const withdrawInput = document.getElementById('withdrawCoins');
  function updateWithdrawPreview() {
    const rate = Number(settingsData?.creatorRate || 0.01);
    document.getElementById('withdrawPreview').textContent = moneyTRY(Number(withdrawInput.value || 0) * rate);
  }
  withdrawInput.addEventListener('input', updateWithdrawPreview);
  document.getElementById('withdrawBtn').addEventListener('click', async () => {
    try {
      const res = await LogiApp.api('/payouts/create.php', { method: 'POST', body: JSON.stringify({ earningCoins: Number(withdrawInput.value || 0) }) });
      msg('withdrawMessage', `${res.message} ${res.referenceCode} • ${moneyTRY(res.moneyAmount)}`, true);
      await loadCore();
      await loadPayouts();
    } catch (error) { msg('withdrawMessage', error.message); }
  });

  async function loadPayouts() {
    const data = await LogiApp.api('/payouts/list.php');
    const root = document.getElementById('payoutList');
    root.innerHTML = data.payouts.length
      ? data.payouts.map(p => `<article class="settings-list-item"><div><strong>${LogiApp.formatNumber(p.earningCoins)} coin → ${moneyTRY(p.moneyAmount)}</strong><span>${LogiApp.escapeHtml(p.maskedIban)} • ${LogiApp.escapeHtml(p.referenceCode)}</span><small>${LogiApp.escapeHtml(p.createdAt)}</small></div><div><span class="status-pill status-${LogiApp.escapeHtml(p.status)}">${LogiApp.escapeHtml(p.status)}</span>${p.status === 'pending' ? `<button class="btn btn-ghost btn-small" data-cancel-payout="${p.id}">İptal</button>` : ''}</div></article>`).join('')
      : '<div class="empty-state compact-empty"><strong>Henüz para çekme talebin yok.</strong></div>';
    root.querySelectorAll('[data-cancel-payout]').forEach(btn => btn.addEventListener('click', async () => {
      try {
        const res = await LogiApp.api('/payouts/cancel.php', { method: 'POST', body: JSON.stringify({ id: Number(btn.dataset.cancelPayout) }) });
        LogiApp.toast(res.message, 'success');
        await loadCore();
        await loadPayouts();
      } catch (error) { LogiApp.toast(error.message, 'error'); }
    }));
  }

  async function loadMembership() {
    const data = await LogiApp.api('/membership/status.php');
    const current = data.membership;
    document.getElementById('currentMembership').textContent = current.isVip ? String(current.tier || 'plus').toUpperCase() : 'Standart';
    const root = document.getElementById('membershipPlans');
    root.innerHTML = current.plans.map(plan => `
      <article class="membership-plan ${current.tier===plan.id?'current':''}">
        <span class="plan-kicker">${plan.name}</span>
        <strong>${Number(plan.priceTry || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} TL / 30 gün</strong>
        <ul>${plan.benefits.map(b => `<li>${LogiApp.escapeHtml(b)}</li>`).join('')}</ul>
        <button class="btn ${current.tier===plan.id?'btn-secondary':'btn-primary'}" data-buy-tier="${plan.id}">${current.tier===plan.id?'Kart ile Uzat':'Kart ile Satın Al'}</button>
      </article>`).join('');
    root.querySelectorAll('[data-buy-tier]').forEach(btn => btn.addEventListener('click', () => {
      window.location.href = `./payment.php?membership=${encodeURIComponent(btn.dataset.buyTier)}&mode=membership`;
    }));
  }

  async function loadPayments() {
    const data = await LogiApp.api('/payments/list.php');
    const root = document.getElementById('bankPaymentList');
    root.innerHTML = data.requests.length
      ? data.requests.map(r => `<div class="payment-mini"><strong>${LogiApp.formatNumber(r.coinAmount)} coin</strong><span>${Number(r.moneyAmount).toLocaleString('tr-TR', { minimumFractionDigits: 2 })} ${r.currency}</span><small>${LogiApp.escapeHtml(r.referenceCode)} • ${LogiApp.escapeHtml(r.status)}</small></div>`).join('')
      : '<div class="empty-state compact-empty"><strong>Henüz coin satın alma talebin yok.</strong></div>';
  }

  document.querySelectorAll('[data-buy-coins]').forEach(btn => btn.addEventListener('click', () => {
    window.location.href = `./payment.php?coins=${encodeURIComponent(btn.dataset.buyCoins)}`;
  }));
  document.addEventListener('submit', e => { if (e.target?.id === 'bankPurchaseForm') setTimeout(loadPayments, 700); });

  function aggregateGiftRows(rows = [], receiverMode = true) {
    const map = new Map();
    rows.forEach(g => {
      const key = `${g.giftName}|${g.giftIcon}`;
      if (!map.has(key)) map.set(key, { ...g, quantity: 0, totalPrice: 0, names: new Set(), createdAt: g.createdAt });
      const item = map.get(key);
      item.quantity += Number(g.quantity || 0);
      item.totalPrice += Number(g.totalPrice || 0);
      item.createdAt = new Date(g.createdAt) > new Date(item.createdAt) ? g.createdAt : item.createdAt;
      item.names.add(receiverMode ? g.senderName : g.recipientName);
    });
    return [...map.values()].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
  }

  async function loadGiftHistory() {
    const data = await LogiApp.api('/profile/get.php');
    const received = document.getElementById('settingsGiftList');
    const sent = document.getElementById('settingsSentGiftList');
    const receivedRows = aggregateGiftRows((data.receivedGifts || []).slice(0, 20), true);
    const sentRows = aggregateGiftRows((data.sentGifts || []).slice(0, 20), false);
    received.innerHTML = receivedRows.length
      ? receivedRows.map(g => `<article class="settings-list-item"><div><strong>${g.giftIcon} ${LogiApp.escapeHtml(g.giftName)} ×${g.quantity}</strong><span>${g.names.size} gönderici • +${LogiApp.formatNumber(g.totalPrice)} coin</span><small>${LogiApp.escapeHtml(LogiApp.timeAgo(g.createdAt))}</small></div></article>`).join('')
      : '<div class="empty-state compact-empty"><strong>Henüz hediye gelmedi.</strong></div>';
    sent.innerHTML = sentRows.length
      ? sentRows.map(g => `<article class="settings-list-item"><div><strong>${g.giftIcon} ${LogiApp.escapeHtml(g.giftName)} ×${g.quantity}</strong><span>${g.names.size} yayıncı/alıcı • ${LogiApp.formatNumber(g.totalPrice)} coin</span><small>${LogiApp.escapeHtml(LogiApp.timeAgo(g.createdAt))}</small></div></article>`).join('')
      : '<div class="empty-state compact-empty"><strong>Henüz hediye göndermedin.</strong></div>';
  }

  async function loadSupport() {
    const [data, reportData] = await Promise.all([
      LogiApp.api('/support/list.php'),
      LogiApp.api('/reports/mine.php').catch(() => ({reports:[]}))
    ]);
    const tickets = (data.tickets || []).map(ticket => `<article class="settings-list-item" id="support-${ticket.id}" data-support-entry="support-${ticket.id}"><div><strong>#${ticket.id} • ${LogiApp.escapeHtml(ticket.subject)}</strong><span>${LogiApp.escapeHtml(ticket.type)} • ${LogiApp.escapeHtml(ticket.status)}</span><small>${LogiApp.escapeHtml(ticket.createdAt)}</small>${ticket.adminReply ? `<div class="support-admin-reply"><b>BUFF Yönetim yanıtı</b><p>${LogiApp.escapeHtml(ticket.adminReply)}</p></div>` : ''}</div></article>`).join('');
    const reports = (reportData.reports || []).map(report => `<article class="settings-list-item" id="report-${report.id}" data-support-entry="report-${report.id}"><div><strong>Şikayet #${report.id} • ${LogiApp.escapeHtml(report.category)}</strong><span>${LogiApp.escapeHtml(report.scope || 'şikayet')} • ${LogiApp.escapeHtml(report.status)}</span><small>${LogiApp.escapeHtml(report.createdAt)}</small>${report.adminReply ? `<div class="support-admin-reply"><b>BUFF Yönetim yanıtı</b><p>${LogiApp.escapeHtml(report.adminReply)}</p></div>` : ''}</div></article>`).join('');
    const html = tickets || reports ? `${tickets}${reports}` : '<div class="empty-state compact-empty"><strong>Henüz destek talebi veya şikayetin yok.</strong></div>';
    document.getElementById('supportList').innerHTML = html;
    document.getElementById('supportListLite').innerHTML = html;
    const focus = new URLSearchParams(location.search).get('focus');
    if (focus) {
      requestAnimationFrame(() => {
        const target = document.getElementById(focus);
        if (target) {
          target.classList.add('support-focus');
          target.scrollIntoView({behavior: document.documentElement.dataset.reduceMotion==='1'?'auto':'smooth', block:'center'});
          setTimeout(() => target.classList.remove('support-focus'), 4200);
        }
      });
    }
  }

  document.getElementById('supportForm').addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.currentTarget;
    try {
      const data = Object.fromEntries(new FormData(form).entries());
      const res = await LogiApp.api('/support/create.php', { method: 'POST', body: JSON.stringify(data) });
      msg('supportMessage', `${res.message} #${res.ticketId}`, true);
      form.reset();
      await loadSupport();
    } catch (error) { msg('supportMessage', error.message); }
  });

  async function syncSupportFromNavigation() {
    const name=(location.hash||'').replace('#','');
    if(name && tabs.some(btn=>btn.dataset.settingsTab===name)) openTab(name,false);
    if(name==='support') await loadSupport();
  }
  window.addEventListener('hashchange',()=>{ syncSupportFromNavigation().catch(()=>{}); });
  document.addEventListener('buff:notificationnavigate',()=>{ syncSupportFromNavigation().catch(()=>{}); });
  document.addEventListener('buff:notificationcreated',e=>{
    const type=String(e.detail?.type||'');
    if(type==='support_reply'||type==='report_reply') loadSupport().catch(()=>{});
  });

  await Promise.all([loadCore(), loadPayouts(), loadMembership(), loadPayments(), loadGiftHistory(), loadSupport()]);
  await syncSupportFromNavigation();
});
