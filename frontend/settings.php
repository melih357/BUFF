<!doctype html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BUFF • Ayarlar</title>
  <link rel="stylesheet" href="./assets/css/style.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v12.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v14.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/motion-v15.css?v=18.0.0">
  <script src="./assets/js/motion-v15.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v17.css?v=18.0.0"><script src="./assets/js/v17.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v18.css?v=18.0.0">
</head>
<body class="app-body settings-body" data-page="settings">
  <header class="app-header kickish-header">
    <a class="logo-lockup" href="./index.php"><img src="./assets/img/buff-logo.png" alt="BUFF"><span>BUFF</span></a>
    <nav class="header-nav"><a href="./index.php">Ana Akış</a><a href="./discover.php">Keşfet</a><a href="./profile.php">Profil</a><a href="./studio.php">Creator Studio</a><a class="active" href="./settings.php">Ayarlar</a></nav>
    <div class="header-search"><span>⌕</span><input type="search" placeholder="Ayarlar içinde ara" disabled><kbd>⚙</kbd></div>
    <div id="authActions" class="header-actions"></div>
  </header>

  <main class="settings-shell settings-shell-v8">
    <aside class="settings-nav surface-card">
      <div class="settings-nav-head"><span class="section-kicker">AYARLAR</span><h1>Kontrol Merkezi</h1><p>Hesabını, görünümünü, yayın güvenliğini ve ödemelerini tek yerden yönet.</p></div>
      <nav>
        <button class="settings-tab active" data-settings-tab="account"><span>👤</span><div><strong>Hesap</strong><small>Profil, kullanıcı adı, hesap modu</small></div></button>
        <button class="settings-tab" data-settings-tab="security"><span>🔐</span><div><strong>Güvenlik</strong><small>E-posta, şifre, oturumlar</small></div></button>
        <button class="settings-tab" data-settings-tab="privacy"><span>◉</span><div><strong>Gizlilik</strong><small>Profil ve etkileşim tercihleri</small></div></button>
        <button class="settings-tab" data-settings-tab="notifications"><span>🔔</span><div><strong>Bildirimler</strong><small>Canlı, takip, yorum, hediye</small></div></button>
        <button class="settings-tab" data-settings-tab="appearance"><span>◐</span><div><strong>Görünüm</strong><small>Tema, vurgu rengi, hareket</small></div></button>
        <button class="settings-tab" data-settings-tab="language"><span>🌐</span><div><strong>Dil</strong><small>TR, EN, DE, ES, AR</small></div></button>
        <button class="settings-tab" data-settings-tab="stream"><span>📡</span><div><strong>Yayın</strong><small>Gecikme, +18, varsayılan güvenlik</small></div></button>
        <button class="settings-tab" data-settings-tab="moderation"><span>🛡</span><div><strong>Moderasyon</strong><small>Chat ve güvenlik tercihleri</small></div></button>
        <button class="settings-tab" data-settings-tab="channel"><span>▤</span><div><strong>Kanal</strong><small>Bio ve sosyal bağlantılar</small></div></button>
        <button class="settings-tab" data-settings-tab="payments"><span>₺</span><div><strong>Ödemeler</strong><small>Coin, kart/IBAN, çekim</small></div></button>
        <button class="settings-tab" data-settings-tab="membership"><span>✦</span><div><strong>Üyelik</strong><small>Plus, Pro, Premium</small></div></button>
        <button class="settings-tab" data-settings-tab="support"><span>🛟</span><div><strong>Destek</strong><small>Şikayet, talep, geri bildirim</small></div></button>
      </nav>
    </aside>

    <section class="settings-content">
      <section class="settings-panel surface-card active" data-settings-panel="account">
        <div class="settings-panel-head"><div><span class="section-kicker">HESAP</span><h2>Profil ve mod yönetimi</h2><p>Kullanıcı hesabı ile yayıncı hesabı arasında tek yerden geçiş yap.</p></div><span class="setting-status" id="accountStatus">Hazır</span></div>
        <form id="accountSettingsForm" class="settings-form">
          <label><span>Görünen ad</span><input id="settingsDisplayName" name="displayName" maxlength="60"></label>
          <label><span>Kullanıcı adı</span><div class="input-prefix"><b>@</b><input id="settingsUsername" name="username" maxlength="24"></div><small>30 günde bir değiştirilebilir.</small></label>
          <label class="full"><span>Biyografi</span><textarea id="settingsBio" name="bio" maxlength="255" rows="5" placeholder="Kendini ve içeriğini anlat..."></textarea><small><span id="bioCounter">0</span>/255 karakter</small></label>
          <div class="full settings-actions"><button class="btn btn-primary" type="submit">Kaydet</button></div>
        </form>
        <div id="accountMessage" class="form-message"></div>
        <div class="setting-group account-mode-box">
          <div class="setting-group-title"><div><h3>Hesap modu</h3><p>Aynı hesapla izleyici veya yayıncı görünümüne geçebilirsin.</p></div><span id="accountModeBadge" class="mini-status">İzleyici</span></div>
          <div class="choice-grid two account-mode-choices">
            <button class="setting-choice" type="button" data-account-mode="viewer"><strong>Normal kullanıcı</strong><small>Yayın açamaz, akış ve topluluk özelliklerini kullanır.</small></button>
            <button class="setting-choice" type="button" data-account-mode="creator"><strong>Yayıncı</strong><small>Creator Studio, canlı yayın, chat ve gelir yönetimini açar.</small></button>
          </div>
          <div id="accountModeMessage" class="form-message"></div>
        </div>
      </section>


      <section class="settings-panel surface-card" data-settings-panel="security">
        <div class="settings-panel-head"><div><span class="section-kicker">GÜVENLİK</span><h2>Hesap güvenliği</h2><p>E-posta ve şifreni değiştir, aktif oturumlarını görüntüle ve gerekirse hesabını kapat.</p></div></div>
        <div class="security-settings-grid">
          <form id="emailChangeForm" class="setting-subcard form-stack"><h3>E-posta değiştir</h3><label><span>Yeni e-posta</span><input name="email" type="email" required></label><label><span>Mevcut şifre</span><input name="password" type="password" required></label><button class="btn btn-secondary" type="submit">E-postayı Değiştir</button><div id="emailChangeMessage" class="form-message"></div></form>
          <form id="passwordChangeForm" class="setting-subcard form-stack"><h3>Şifre değiştir</h3><label><span>Mevcut şifre</span><input name="currentPassword" type="password" required></label><label><span>Yeni şifre</span><input name="newPassword" type="password" minlength="8" required></label><button class="btn btn-secondary" type="submit">Şifreyi Değiştir</button><div id="passwordChangeMessage" class="form-message"></div></form>
        </div>
        <div class="setting-subcard"><div class="setting-group-title"><div><h3>Aktif oturumlar</h3><p>Hesabının açık olduğu tarayıcı ve cihazları kontrol et.</p></div><button id="refreshSessionsBtn" class="btn btn-ghost btn-small" type="button">Yenile</button></div><div id="sessionList" class="settings-list"></div></div>
        <div class="setting-subcard danger-zone"><h3>Hesabı kapat</h3><p>Hesabın girişe kapatılır. Bu işlem için mevcut şifren gerekir.</p><div class="inline-danger-form"><input id="closeAccountPassword" type="password" placeholder="Mevcut şifre"><button id="closeAccountBtn" class="btn btn-danger" type="button">Hesabı Kapat</button></div><div id="closeAccountMessage" class="form-message"></div></div>
      </section>

      <section class="settings-panel surface-card" data-settings-panel="privacy"><div class="settings-panel-head"><div><span class="section-kicker">GİZLİLİK</span><h2>Gizlilik tercihleri</h2><p>Topluluk ve kanal görünürlüğüne ilişkin istemci tercihleri.</p></div></div><label class="toggle-row"><div><strong>Çevrimiçi durumumu göster</strong><small>Kanal ve topluluk alanında aktif durum işareti kullanılsın.</small></div><input class="local-pref" data-pref="presence" type="checkbox" checked><span class="switch-ui"></span></label><label class="toggle-row"><div><strong>Mesajlarda mention kabul et</strong><small>@kullanıcı etiketlemelerine izin ver.</small></div><input class="local-pref" data-pref="mentions" type="checkbox" checked><span class="switch-ui"></span></label><label class="toggle-row"><div><strong>Topluluk kaydetme geçmişi</strong><small>Kaydettiğin gönderileri hesabında tut.</small></div><input class="local-pref" data-pref="savedPosts" type="checkbox" checked><span class="switch-ui"></span></label></section>

      <section class="settings-panel surface-card" data-settings-panel="notifications"><div class="settings-panel-head"><div><span class="section-kicker">BİLDİRİMLER</span><h2>Bildirim tercihleri</h2><p>Takip, yorum, hediye ve canlı yayın olaylarını yönet.</p></div></div><div class="setting-group compact-setting-group"><label class="toggle-row"><div><strong>Takip ettiğim kanal canlı</strong><small>Canlı yayın başladığında bildir.</small></div><input class="local-pref" data-pref="notifyLive" type="checkbox" checked><span class="switch-ui"></span></label><label class="toggle-row"><div><strong>Yorum ve yanıt</strong><small>Gönderilerine gelen yorum ve cevapları bildir.</small></div><input class="local-pref" data-pref="notifyComments" type="checkbox" checked><span class="switch-ui"></span></label><label class="toggle-row"><div><strong>Beğeni ve takip</strong><small>Yeni beğeni ve takipleri bildir.</small></div><input class="local-pref" data-pref="notifySocial" type="checkbox" checked><span class="switch-ui"></span></label><label class="toggle-row"><div><strong>Hediye ve ödeme</strong><small>Hediye, ödeme ve çekim durumlarını bildir.</small></div><input class="local-pref" data-pref="notifyMoney" type="checkbox" checked><span class="switch-ui"></span></label></div></section>

      <section class="settings-panel surface-card" data-settings-panel="language"><div class="settings-panel-head"><div><span class="section-kicker">DİL</span><h2>Arayüz dili</h2><p>Dil değişikliği kullanıcı ve admin arayüzünün tamamında aynı çeviri motorunu kullanır.</p></div></div><div class="language-choice-grid" id="settingsLanguageGrid"><button data-settings-language="tr">🇹🇷 Türkçe</button><button data-settings-language="en">🇬🇧 English</button><button data-settings-language="de">🇩🇪 Deutsch</button><button data-settings-language="es">🇪🇸 Español</button><button data-settings-language="ar">🇸🇦 العربية</button></div><div id="languageSettingsMessage" class="form-message"></div></section>

      <section class="settings-panel surface-card" data-settings-panel="moderation"><div class="settings-panel-head"><div><span class="section-kicker">MODERASYON</span><h2>Chat kontrolü</h2><p>Yayıncı görünümündeki varsayılan chat davranışlarını belirle.</p></div></div><label class="toggle-row"><div><strong>Slow-mode önerisi</strong><small>Yoğun chat sırasında yavaş mod kontrolünü öne çıkar.</small></div><input class="local-pref" data-pref="slowSuggestion" type="checkbox" checked><span class="switch-ui"></span></label><label class="toggle-row"><div><strong>Önemli mesajlar filtresi</strong><small>Studio açıldığında yayıncı/MOD mesajlarını öne çıkar.</small></div><input class="local-pref" data-pref="importantDefault" type="checkbox"><span class="switch-ui"></span></label><div class="settings-note"><strong>Slash komutları</strong><p>/mute, /unmute, /ban, /unban, /clear, /slow, /mod ve /unmod Creator Studio içinde kullanılabilir.</p></div></section>

      <section class="settings-panel surface-card" data-settings-panel="channel" id="channel">
        <div class="settings-panel-head"><div><span class="section-kicker">KANAL</span><h2>Herkese açık kanal sayfası</h2><p>Kanal biyografini, sosyal ağlarını ve haftalık yayın takvimini tek yerden yönet.</p></div><a class="btn btn-ghost" href="./channel.php">Kanalı Gör</a></div>
        <form id="channelSettingsForm" class="settings-form">
          <label class="full"><span>Kanal biyografisi</span><textarea name="bio" rows="5" maxlength="500"></textarea></label>
          <label><span>YouTube</span><input name="youtube" placeholder="https://youtube.com/@kanal"></label><label><span>Instagram</span><input name="instagram" placeholder="https://instagram.com/kullanici"></label>
          <label><span>X</span><input name="x" placeholder="https://x.com/kullanici"></label><label><span>TikTok</span><input name="tiktok" placeholder="https://tiktok.com/@kullanici"></label>
          <label><span>Discord</span><input name="discord" placeholder="https://discord.gg/... "></label>
          <div class="full channel-schedule-editor"><div class="setting-group-title"><div><h3>Haftalık yayın takvimi</h3><p>İzleyicilerin hangi gün ve saatte çevrimiçi olacağını görsün.</p></div><span class="soft-pill">Europe/Istanbul</span></div>
            <div id="channelScheduleEditor" class="schedule-editor-grid"></div>
          </div>
          <div class="full settings-actions"><button class="btn btn-primary" type="submit">Kanalı ve Takvimi Kaydet</button></div>
        </form><div id="channelSettingsMessage" class="form-message"></div>
      </section>

      <section class="settings-panel surface-card" data-settings-panel="appearance">
        <div class="settings-panel-head"><div><span class="section-kicker">GÖRÜNÜM</span><h2>Daha doğal açık/koyu tema</h2><p>Her modda yazı okunurluğu artırıldı; neon yerine daha profesyonel kontrast kullanılır.</p></div></div>
        <div class="setting-group">
          <h3>Tema modu</h3>
          <div class="choice-grid three" id="themeModeChoices">
            <button class="setting-choice" data-theme-choice="light"><span class="theme-preview light-preview"></span><strong>Açık</strong><small>Beyaz ve nötr yüzeyler</small></button>
            <button class="setting-choice" data-theme-choice="dark"><span class="theme-preview dark-preview"></span><strong>Koyu</strong><small>Derin antrasit ve okunaklı kartlar</small></button>
            <button class="setting-choice" data-theme-choice="system"><span class="theme-preview system-preview"></span><strong>Sistem</strong><small>Cihazını takip et</small></button>
          </div>
        </div>
        <div class="setting-group">
          <div class="setting-group-title"><div><h3>Vurgu rengi</h3><p>VIP seviyene göre ek renk paletleri açılır.</p></div><span id="membershipThemeBadge" class="mini-status">Standart</span></div>
          <div id="accentChoices" class="accent-choice-grid"></div>
        </div>
        <label class="toggle-row"><div><strong>Hareketleri azalt</strong><small>Sayfa geçişleri ve efektler minimuma iner.</small></div><input id="reduceMotionToggle" type="checkbox"><span class="switch-ui"></span></label>
        <div class="settings-actions"><button id="saveAppearanceBtn" class="btn btn-primary" type="button">Görünümü Kaydet</button></div>
        <div id="appearanceMessage" class="form-message"></div>
      </section>

      <section class="settings-panel surface-card" data-settings-panel="stream">
        <div class="settings-panel-head"><div><span class="section-kicker">YAYIN</span><h2>Varsayılan yayın güvenliği</h2><p>Gecikme ve içerik uyarıları burada belirlenir. Fazla küfürlü / yetişkin yayınlar için +18 işaretleme standardı zorunlu hale gelir.</p></div></div>
        <div class="delay-picker" id="delayPicker">
          <button data-delay="0"><strong>0 sn</strong><small>Anlık</small></button><button data-delay="3"><strong>3 sn</strong><small>Düşük</small></button><button data-delay="5"><strong>5 sn</strong><small>Dengeli</small></button><button data-delay="10"><strong>10 sn</strong><small>Güvenli</small></button><button data-delay="15"><strong>15 sn</strong><small>Moderasyon</small></button><button data-delay="30"><strong>30 sn</strong><small>Yüksek</small></button>
        </div>
        <div class="setting-group compact-setting-group">
          <label class="toggle-row"><div><strong>Varsayılan +18 etiketi</strong><small>Yetişkin içerikli yayınları baştan işaretlemek için önerilir.</small></div><input id="defaultAdultToggle" type="checkbox" disabled><span class="switch-ui"></span></label>
          <label class="toggle-row"><div><strong>Fazla küfür / sert dil uyarısı</strong><small>Yayın açarken bu seçenek açık ise +18 işaretleme zorunlu tutulur.</small></div><input id="defaultMatureToggle" type="checkbox" disabled><span class="switch-ui"></span></label>
        </div>
        <div class="settings-note"><strong>Not:</strong><p>Bu sürümde zorunlu kontrol Creator Studio açılış formunda yapılır; kullanıcı küfürlü/yetişkin içerik yayınlayacaksa +18 seçimi olmadan yayını başlatamaz.</p></div>
        <div class="settings-actions"><a class="btn btn-secondary" href="./studio.php">Stüdyoyu Aç</a><button id="saveStreamSettingsBtn" class="btn btn-primary" type="button">Yayın Ayarını Kaydet</button></div>
        <div id="streamSettingsMessage" class="form-message"></div>
      </section>

      <section class="settings-panel surface-card" data-settings-panel="payments" id="payments">
        <div class="settings-panel-head"><div><span class="section-kicker">ÖDEMELER</span><h2>Coin, ödeme yöntemleri ve çekim</h2><p>Coin satın alma artık doğrudan bakiye yüklemez; önce talep oluşturur, sonra ödeme sağlayıcısı / banka doğrulaması beklenir.</p></div></div>
        <div class="money-summary-grid">
          <article><span>Harcanabilir Coin</span><strong id="spendableCoins">0</strong><small>Hediye, üyelik ve iç kullanım</small></article>
          <article class="earnings-card"><span>Yayıncı Kazancı</span><strong id="creatorEarnings">0</strong><small id="creatorEarningsTry">≈ ₺0,00</small></article>
          <article><span>Minimum Çekim</span><strong>1.000</strong><small>yayıncı coini</small></article>
        </div>
        <div class="payment-settings-grid">
          <section class="setting-subcard">
            <h3>Coin satın al / yükle</h3>
            <p class="muted-note">Aşağıdaki paketlerden biri için ödeme talebi oluştur. Coinler, onay sonrası hesabına yüklenir.</p>
            <div class="coin-package-grid" id="coinPackageGrid">
              <button type="button" data-buy-coins="500"><strong>500 coin</strong><small>49,90 TL • Kart / PayTR</small></button>
              <button type="button" data-buy-coins="1500"><strong>1.500 coin</strong><small>129,90 TL • Kart / PayTR</small></button>
              <button type="button" data-buy-coins="5000"><strong>5.000 coin</strong><small>399,90 TL • Kart / PayTR</small></button>
              <button type="button" data-buy-coins="12000"><strong>12.000 coin</strong><small>849,90 TL • Kart / PayTR</small></button>
            </div>
            <button class="btn btn-ghost full-btn" type="button" data-open-modal="bankPurchaseModal">Banka transferi ile talep oluştur</button>
            <div id="bankPaymentList" class="mini-payment-list"></div>
          </section>
          <section class="setting-subcard saved-card-panel">
            <div class="setting-group-title"><div><h3>Kayıtlı kartlar</h3><p>Coin ve üyelik satın alımlarında kullanacağın ödeme yöntemlerini yönet.</p></div><span class="secure-chip">🔒 PCI uyumlu yaklaşım</span></div>
            <div id="savedPaymentMethods" class="saved-payment-methods"><div class="empty-state compact-empty"><strong>Henüz kart kaydedilmedi.</strong></div></div>
            <form id="cardSettingsForm" class="form-stack compact-form card-entry-form" autocomplete="off">
              <label><span>Kart üzerindeki ad</span><input name="cardHolder" required maxlength="120" placeholder="Ad Soyad" autocomplete="cc-name"></label>
              <label><span>Kart numarası</span><input id="settingsCardNumber" name="cardNumber" required inputmode="numeric" maxlength="23" placeholder="0000 0000 0000 0000" autocomplete="cc-number"></label>
              <div class="form-row"><label><span>Son kullanma</span><input id="settingsCardExpiry" name="expiry" required maxlength="7" placeholder="AA/YYYY" autocomplete="cc-exp"></label><label><span>CVV</span><input id="settingsCardCvv" name="cvv" required inputmode="numeric" maxlength="4" placeholder="•••" autocomplete="cc-csc"></label></div>
              <label><span>Telefon</span><input id="settingsCardPhone" name="phone" required placeholder="05xx xxx xx xx" autocomplete="tel"></label>
              <label><span>Ev / fatura adresi</span><textarea id="settingsCardAddress" name="address" required rows="3" placeholder="Mahalle, sokak, ilçe, şehir"></textarea></label>
              <button class="btn btn-secondary" type="submit">Kartı Kaydet</button>
              <small class="security-inline">Tam kart numarası ve CVV veritabanına yazılmaz; yalnızca kart markası, son 4 hane, son kullanma tarihi ve iletişim bilgileri saklanır.</small>
            </form>
            <div id="cardMessage" class="form-message"></div>
          </section>
          <section class="setting-subcard payout-bank-panel">
            <h3>Yayıncı çekim hesabı</h3>
            <p class="muted-note">IBAN yalnızca yayıncı kazançlarını banka hesabına çekmek için kullanılır; coin veya VIP satın alımında kullanılmaz.</p>
            <div id="savedBankCard" class="saved-bank-card">Henüz çekim IBAN'ı kaydedilmedi.</div>
            <form id="bankSettingsForm" class="form-stack compact-form">
              <label><span>Hesap sahibi</span><input name="accountHolder" required placeholder="Ad Soyad"></label>
              <label><span>IBAN</span><input name="iban" required placeholder="TR00 0000 0000 0000 0000 0000 00"></label>
              <button class="btn btn-ghost" type="submit">Çekim IBAN'ını Kaydet</button>
            </form>
            <div id="bankMessage" class="form-message"></div>
          </section>
          <section class="setting-subcard">
            <h3>Para çekme talebi</h3>
            <label><span>Çekilecek yayıncı coini</span><input id="withdrawCoins" type="number" min="1000" step="100" value="1000"></label>
            <div class="withdraw-preview"><span>Tahmini banka tutarı</span><strong id="withdrawPreview">₺0,00</strong></div>
            <button id="withdrawBtn" class="btn btn-primary" type="button">IBAN'a Çekim Talebi Oluştur</button>
            <div id="withdrawMessage" class="form-message"></div>
          </section>
        </div>
        <div class="settings-two-column">
          <section class="setting-subcard"><h3>Çekim geçmişi</h3><div id="payoutList" class="settings-list"></div></section>
          <section class="setting-subcard"><h3>Gelen hediyeler</h3><div id="settingsGiftList" class="settings-list"></div></section>
        </div>
        <div class="settings-two-column">
          <section class="setting-subcard"><h3>Gönderdiğim hediyeler</h3><div id="settingsSentGiftList" class="settings-list"></div></section>
          <section class="setting-subcard"><h3>Gelen talepler / destekler</h3><div id="supportListLite" class="settings-list"></div></section>
        </div>
      </section>

      <section class="settings-panel surface-card" data-settings-panel="membership">
        <div class="settings-panel-head"><div><span class="section-kicker">ÜYELİK</span><h2>VIP katmanları</h2><p>Plus, Pro ve Premium tek pakete sıkışmayan net farklarla sunulur.</p></div><span id="currentMembership" class="mini-status">Standart</span></div>
        <div id="membershipPlans" class="membership-plan-grid"></div>
        <div id="membershipMessage" class="form-message"></div>
      </section>

      <section class="settings-panel surface-card" data-settings-panel="support">
        <div class="settings-panel-head"><div><span class="section-kicker">DESTEK</span><h2>Şikayet, arz, talep ve geri bildirim</h2><p>Siteyle alakalı her sorunu ya da geliştirme isteğini burada iletebilirsin.</p></div></div>
        <form id="supportForm" class="settings-form">
          <label><span>Tür</span><select name="type"><option value="complaint">Site şikayeti</option><option value="request">Talep</option><option value="suggestion">Öneri</option><option value="technical">Teknik sorun</option><option value="payment">Ödeme / kazanç</option></select></label>
          <label><span>Konu</span><input name="subject" maxlength="140" required placeholder="Kısa konu başlığı"></label>
          <label class="full"><span>Açıklama</span><textarea name="message" maxlength="2000" rows="7" required placeholder="Yaşadığın durumu veya isteğini ayrıntılı anlat..."></textarea></label>
          <div class="full settings-actions"><button class="btn btn-primary" type="submit">Gönder</button></div>
        </form>
        <div id="supportMessage" class="form-message"></div>
        <div class="setting-group"><h3>Gönderdiğin talepler</h3><div id="supportList" class="settings-list"></div></div>
      </section>
    </section>
  </main>


  <div class="app-modal" id="creatorOnboardingModal"><div class="modal-card wide-modal"><button class="modal-close" type="button" data-close-modal="creatorOnboardingModal">×</button><span class="section-kicker">CREATOR ONBOARDING</span><h2>Yayıncı modunu hazırla</h2><div class="onboarding-steps"><span class="active">1 Kanal</span><span>2 Kategori</span><span>3 Güvenlik</span><span>4 Ödeme</span><span>5 Test</span></div><form id="creatorOnboardingForm" class="form-stack"><label><span>Kanal açıklaması</span><textarea name="bio" rows="3" maxlength="500" required placeholder="Kanalını kısaca anlat"></textarea></label><label><span>Ana kategori</span><select name="category"><option>Sohbet</option><option>Oyun</option><option>Müzik</option><option>E-spor</option><option>Sanat</option><option>Mobil</option></select></label><label class="toggle-row"><div><strong>+18 ve yoğun küfür kurallarını anladım</strong><small>Yoğun küfür işaretliyse +18 zorunludur.</small></div><input name="safety" type="checkbox" required><span class="switch-ui"></span></label><label class="toggle-row"><div><strong>Ödeme hesabını sonra Ayarlar → Ödemeler'den tamamlayacağım</strong><small>IBAN eklemeden de yayın açabilirsin; çekim için IBAN gerekir.</small></div><input name="payment" type="checkbox" required><span class="switch-ui"></span></label><div class="device-test-box"><strong>Kamera / mikrofon testi</strong><p>Onboarding tamamlandıktan sonra Creator Studio’daki “Kamerayı Hazırla” ile izinleri ve cihazlarını test edebilirsin.</p></div><button class="btn btn-primary" type="submit">Yayıncı Modunu Aç</button></form><div id="creatorOnboardingMessage" class="form-message"></div></div></div>

  <div class="app-modal" id="bankPurchaseModal"><div class="modal-card wide-modal"><button class="modal-close" type="button" data-close-modal="bankPurchaseModal">×</button><span class="section-kicker">COIN</span><h2>Banka transferi ile coin talebi</h2><p class="muted-note">Talep, banka veya ödeme sağlayıcısı tarafından doğrulandıktan sonra coin bakiyene yansır.</p><form id="bankPurchaseForm" class="form-stack pro-form"><label><span>Coin paketi</span><select name="coinAmount"><option value="500">500 coin • 49,90 TL</option><option value="1500">1.500 coin • 129,90 TL</option><option value="5000">5.000 coin • 399,90 TL</option><option value="12000">12.000 coin • 849,90 TL</option></select></label><label><span>Hesap sahibi</span><input name="accountHolder" required placeholder="Ad Soyad"></label><label><span>IBAN</span><input name="iban" required placeholder="TR00 0000 0000 0000 0000 0000 00"></label><button class="btn btn-primary" type="submit">Transfer Talebi Oluştur</button></form><div id="bankPurchaseMessage" class="form-message"></div><div class="security-note">Bu yerel sürüm gerçek otomatik tahsilat yapmaz; ödeme sağlayıcısı entegrasyonu ile birleştirilmelidir.</div></div></div>

  <script src="./assets/js/i18n.js?v=18.0.0"></script>
  <script src="./assets/js/common.js?v=18.0.0"></script>
  <script src="./assets/js/v12.js?v=18.0.0"></script>
  <script src="./assets/js/settings.js?v=18.0.0"></script>
  <script src="./assets/js/settings-v12.js?v=18.0.0"></script>
  <script src="./assets/js/v14.js?v=18.0.0"></script>
</body>
</html>
