document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(location.search);
  const result = params.get('result');
  let coins = Number(params.get('coins') || 0);
  const membership = (params.get('membership') || '').toLowerCase();
  const mode = (params.get('mode') || (membership ? 'membership' : 'coin')).toLowerCase();
  const packageMap = {};let coinPackages=[];
  try{const d=await LogiApp.api('/payments/packages.php');coinPackages=d.packages;coinPackages.forEach(p=>packageMap[p.coins]=LogiApp.formatNumber(p.coins)+' coin • '+(p.amountKurus/100).toLocaleString('tr-TR',{minimumFractionDigits:2})+' TL');}catch{}
  if(mode==='coin'&&!packageMap[coins])coins=coinPackages[0]?.coins||0;
  const membershipMap = { plus: 'Plus • 30 gün • 99,90 TL', pro: 'Pro • 30 gün • 199,90 TL', premium: 'Premium • 30 gün • 349,90 TL' };
  const resultBox = document.getElementById('paymentResult');
  const iframe = document.getElementById('paytrIframe');
  const msg = document.getElementById('paymentMessage');
  const form = document.getElementById('paymentPreForm');
  const packageEl = document.getElementById('selectedPackage');
  const titleEl = document.getElementById('paymentTitle');
  const introEl = document.getElementById('paymentIntro');
  const labelEl = document.getElementById('paymentLabel');
  const savedRoot = document.getElementById('paymentSavedCards');
  const selectedMethod = document.getElementById('selectedPaymentMethodId');
  const newCardBox = document.getElementById('paymentNewCard');
  let methods = [];

  const cardDigits = value => String(value || '').replace(/\D/g, '').slice(0, 19);
  const luhnValid = value => {
    const digits = cardDigits(value); if (digits.length < 13) return false;
    let sum = 0, flip = false;
    for (let i = digits.length - 1; i >= 0; i--) { let n = Number(digits[i]); if (flip) { n *= 2; if (n > 9) n -= 9; } sum += n; flip = !flip; }
    return sum % 10 === 0;
  };
  const brandOf = value => {
    const n = cardDigits(value);
    if (/^4/.test(n)) return 'visa';
    if (/^(5[1-5]|2[2-7])/.test(n)) return 'mastercard';
    if (/^3[47]/.test(n)) return 'amex';
    if (/^9792/.test(n)) return 'troy';
    return 'card';
  };
  const expiryOf = value => {
    const m = String(value || '').trim().match(/^(0?[1-9]|1[0-2])\s*[\/-]\s*(\d{2}|\d{4})$/);
    if (!m) return null;
    const month = Number(m[1]); let year = Number(m[2]); if (year < 100) year += 2000;
    const now = new Date(); if (year < now.getFullYear() || (year === now.getFullYear() && month < now.getMonth() + 1)) return null;
    return { month, year };
  };
  const setMessage = (text, ok = false) => { msg.className = `form-message ${ok ? 'success' : 'error'}`; msg.textContent = text || ''; };

  function selectSavedCard(id) {
    selectedMethod.value = id ? String(id) : '';
    savedRoot?.querySelectorAll('[data-payment-method]').forEach(el => el.classList.toggle('active', String(el.dataset.paymentMethod) === String(id)));
    const isSaved = !!id;
    newCardBox?.classList.toggle('hidden', isSaved);
    newCardBox?.querySelectorAll('input[required]').forEach(input => input.required = !isSaved);
    const card = methods.find(x => String(x.id) === String(id));
    if (card) {
      const phone = document.getElementById('paymentPhone'); const address = document.getElementById('paymentAddress');
      if (card.phone) phone.value = card.phone;
      if (card.address) address.value = card.address;
    }
  }

  function renderSavedCards() {
    if (!savedRoot) return;
    const items = methods.map(card => `<button type="button" class="payment-card-choice" data-payment-method="${card.id}"><span class="payment-card-brand">${card.brand === 'visa' ? 'VISA' : card.brand === 'mastercard' ? 'MC' : card.brand === 'troy' ? 'TROY' : 'CARD'}</span><strong>${LogiApp.escapeHtml(card.masked)}</strong><small>${String(card.expMonth).padStart(2,'0')}/${card.expYear}</small></button>`).join('');
    savedRoot.innerHTML = `${items}<button type="button" class="payment-card-choice new-card-choice" data-payment-method=""><span>＋</span><strong>Yeni kart</strong><small>Kart bilgisi gir</small></button>`;
    savedRoot.querySelectorAll('[data-payment-method]').forEach(btn => btn.addEventListener('click', () => selectSavedCard(btn.dataset.paymentMethod)));
    if (methods.length) selectSavedCard(methods.find(x => x.isDefault)?.id || methods[0].id); else selectSavedCard('');
  }

  try {
    const [cardData, me] = await Promise.all([LogiApp.api('/payment-methods/list.php'), LogiApp.getCurrentUser()]);
    methods = cardData.methods || [];
    renderSavedCards();
    if (me) {
      document.getElementById('paymentPhone').value ||= me.phone || '';
      document.getElementById('paymentAddress').value ||= me.billingAddress || '';
    }
  } catch (_) { renderSavedCards(); }

  const numberInput = document.getElementById('paymentCardNumber');
  const expiryInput = document.getElementById('paymentCardExpiry');
  numberInput?.addEventListener('input', e => { const d = cardDigits(e.target.value); e.target.value = d.replace(/(.{4})/g, '$1 ').trim(); });
  expiryInput?.addEventListener('input', e => { let d = String(e.target.value || '').replace(/\D/g,'').slice(0,6); if (d.length > 2) d = `${d.slice(0,2)}/${d.slice(2)}`; e.target.value = d; });

  if (mode === 'membership') {
    titleEl.textContent = 'VIP üyelik satın al';
    introEl.textContent = 'Plus, Pro veya Premium üyeliğini kartla satın al. Telefon ve adres bilgilerin hesabında kalır; tam kart numarası/CVV saklanmaz.';
    labelEl.textContent = 'Üyelik';
    packageEl.textContent = membershipMap[membership] || 'Geçersiz üyelik paketi';
  } else {
    packageEl.textContent = packageMap[coins] || 'Geçersiz paket';
  }

  if (result) {
    form.classList.add('hidden');
    resultBox.classList.remove('hidden');
    resultBox.className = `payment-result ${result === 'success' ? 'success' : 'error'}`;
    resultBox.innerHTML = result === 'success'
      ? `<strong>${mode === 'membership' ? 'Üyelik ödeme sayfası tamamlandı.' : 'Ödeme sağlayıcısı işlem sayfasını tamamladı.'}</strong><span>${mode === 'membership' ? 'VIP üyeliğin güvenli callback onayından sonra otomatik etkinleşir.' : 'Coin bakiyesinin kesinleşmesi güvenli callback bildirimi geldikten sonra yapılır.'}</span>`
      : `<strong>${mode === 'membership' ? 'Üyelik ödemesi tamamlanamadı.' : 'Ödeme tamamlanamadı.'}</strong><span>Ödeme yöntemi veya banka onayını kontrol edip tekrar deneyebilirsin.</span>`;
    return;
  }

  if(mode==='coin'&&coinPackages.length){const picker=document.createElement('div');picker.className='v17-coin-packages';picker.innerHTML=coinPackages.map(p=>'<button type="button" class="surface-card '+(p.coins===coins?'selected':'')+'" data-coins="'+p.coins+'"><strong>◈ '+LogiApp.formatNumber(p.coins)+'</strong><span>'+(p.amountKurus/100).toLocaleString('tr-TR',{minimumFractionDigits:2})+' TL</span></button>').join('');form.before(picker);picker.onclick=e=>{const b=e.target.closest('[data-coins]');if(!b)return;coins=Number(b.dataset.coins);packageEl.textContent=packageMap[coins];picker.querySelectorAll('button').forEach(x=>x.classList.toggle('selected',x===b));const url=new URL(location.href);url.searchParams.set('coins',coins);history.replaceState({},'',url);};}
  const invalid = mode === 'membership' ? !membershipMap[membership] : (!coins || !packageMap[coins]);
  if (invalid) {
    form.classList.add('hidden');
    setMessage(mode === 'membership' ? 'Geçerli bir üyelik paketi seçilmedi.' : 'Geçerli bir coin paketi seçilmedi.');
    return;
  }

  form.addEventListener('submit', async event => {
    event.preventDefault();
    const phone = document.getElementById('paymentPhone').value.trim();
    const address = document.getElementById('paymentAddress').value.trim();
    let cardMeta = null;
    if (!selectedMethod.value) {
      const number = cardDigits(numberInput.value);
      const expiry = expiryOf(expiryInput.value);
      const cvv = String(document.getElementById('paymentCardCvv').value || '').replace(/\D/g,'');
      const holder = document.getElementById('paymentCardHolder').value.trim();
      if (!luhnValid(number)) { setMessage('Kart numarası geçerli görünmüyor.'); return; }
      if (!expiry) { setMessage('Son kullanma tarihini AA/YYYY biçiminde gir.'); return; }
      if (!/^\d{3,4}$/.test(cvv)) { setMessage('CVV 3 veya 4 haneli olmalı.'); return; }
      if (holder.length < 3) { setMessage('Kart üzerindeki adı gir.'); return; }
      cardMeta = { cardHolder: holder, brand: brandOf(number), last4: number.slice(-4), expMonth: expiry.month, expYear: expiry.year, phone, address };
    }

    setMessage('Güvenli ödeme formu hazırlanıyor...', true);
    try {
      if (cardMeta && document.getElementById('saveCardForLater').checked) {
        await LogiApp.api('/payment-methods/save.php', {method:'POST', body:JSON.stringify(cardMeta)});
      }
      const payload = { phone, address };
      if (mode === 'membership') payload.membershipTier = membership; else payload.coinAmount = coins;
      const data = await LogiApp.api('/payments/paytr/create.php', { method: 'POST', body: JSON.stringify(payload) });
      iframe.src = data.iframeUrl;
      iframe.classList.remove('hidden');
      form.classList.add('hidden');
      setMessage('Güvenli ödeme ekranı hazır. Kart doğrulamasını ödeme sağlayıcısında tamamla.', true);
    } catch (error) { setMessage(error.message); }
  });
});
