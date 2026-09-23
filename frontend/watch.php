<!doctype html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BUFF • Canlı Yayın</title>
  <link rel="stylesheet" href="./assets/css/style.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v12.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v14.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/motion-v15.css?v=18.0.0">
  <script src="./assets/js/motion-v15.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v17.css?v=18.0.0"><script src="./assets/js/v17.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v18.css?v=18.0.0">
<script src="./assets/js/chat-v18.js?v=18.0.0" defer></script>
</head>
<body class="app-body watch-body" data-page="watch">
  <header class="app-header kickish-header">
    <a class="logo-lockup" href="./index.php"><img src="./assets/img/buff-logo.png" alt="BUFF"><span>BUFF</span></a>
    <nav class="header-nav"><a href="./index.php">Ana Akış</a><a href="./discover.php">Keşfet</a><a class="active" href="#">Canlı</a><a href="./studio.php">Yayın Aç</a><a href="./settings.php">Ayarlar</a></nav>
    <div class="header-search compact-search"><span>⌕</span><input id="watchSearch" type="search" data-i18n-placeholder="search_placeholder" placeholder="Yayın, kanal, kategori, tekrar veya klip ara"><kbd>Ctrl K</kbd></div>
    <div id="authActions" class="header-actions"></div>
  </header>

  <main class="watch-shell">
    <section class="watch-center">
      <div class="player-shell" id="playerShell">
        <video id="remoteVideo" autoplay playsinline controls></video>
        <div class="player-topbar"><span class="live-label">CANLI</span><span id="delayBadge" class="delay-badge">⏱ yayın gecikmesi</span><span id="contentRatingBadge" class="metric-pill hidden">+18</span></div>
        <div id="delayOverlay" class="delay-overlay"><div class="delay-ring"><span id="delayCount">5</span></div><strong>Yayın bağlantısı hazırlanıyor</strong><small id="delayText">Yayıncı tarafından seçilen gecikme uygulanıyor.</small></div>
        <div id="giftAnimationLayer" class="gift-animation-layer"></div>
        <div id="playerStatus" class="player-status">Yayın bilgisi alınıyor...</div>
      </div>

      <section class="stream-details-card">
        <div class="stream-details-main">
          <span id="streamAvatar" class="creator-avatar large avatar-violet">L</span>
          <div class="stream-detail-copy">
            <h1 id="watchTitle">Yayın yükleniyor...</h1>
            <div class="creator-row"><strong id="watchStreamer">BUFF yayıncısı</strong><span id="watchUsername">@buff</span><span>•</span><span id="watchCategory">Kategori</span></div>
            <div id="watchTags" class="mini-tags"></div>
            <div id="streamSafetyInfo" class="stream-safety-info hidden"></div>
          </div>
        </div>
        <div class="stream-detail-actions">
          <span id="viewerCountBadge" class="metric-pill">◉ 0</span>
          <span id="followerCountBadge" class="metric-pill">✦ 0</span>
          <button id="followBtn" class="btn btn-primary" type="button">+ Takip Et</button>
          <button id="shareBtn" class="btn btn-ghost" type="button">↗ Paylaş</button>
          <button id="clipBtn" class="btn btn-ghost" type="button">✂ Klip</button>
          <button id="theatreBtn" class="btn btn-ghost" type="button">▣ Theatre</button>
          <button id="fullscreenBtn" class="btn btn-ghost" type="button">⛶ Tam Ekran</button>
          <button id="reportBtn" class="btn btn-ghost danger-outline" type="button" data-open-modal="reportModal">⚑ Yayıncıyı Şikayet Et</button>
        </div>
      </section>

      <section class="stream-about-card">
        <div><span class="section-kicker">YAYIN HAKKINDA</span><h2>Yayın açıklaması</h2><p id="watchDescription">Yayın açıklaması yükleniyor...</p></div>
        <div class="support-summary"><span>Bu yayına gönderilen destek</span><strong id="streamGiftTotal">0 coin</strong></div>
      </section>
    </section>

    <aside class="watch-rail">
      <div class="rail-tabs" id="watchTabs"><button class="active" data-tab="chat">Canlı Chat</button><button data-tab="gifts">Hediyeler</button></div>

      <section class="rail-panel active" data-panel="chat">
        <div class="chat-head"><div><strong>Yayın Chat'i</strong><small id="chatPresence">Topluluğa katıl</small></div><span>💬</span></div>
        <div id="moderationHint" class="moderation-hint hidden"></div><div class="chat-view-tools"><button id="chatImportantBtn" type="button">Önemli</button><button id="chatVipBtn" type="button">MOD/VIP</button><button id="chatFocusWatchBtn" type="button">Odak</button></div>
        <div id="chatList" class="chat-list"></div>
        <div class="quick-emojis"><button type="button">🔥</button><button type="button">👏</button><button type="button">❤️</button><button type="button">😂</button><button type="button">GG</button></div>
        <div id="vipStickerTray" class="vip-sticker-tray hidden"><span>VIP Stickerlar</span><div><button data-sticker="party_parrot" type="button">🦜</button><button data-sticker="gg_fire" type="button">🔥GG</button><button data-sticker="logi_love" type="button">💚</button><button data-sticker="hype_train" type="button">🚂</button><button data-sticker="king_crown" type="button">👑</button><button data-sticker="space_cat" type="button">🐱‍🚀</button></div></div>
        <form id="chatForm" class="chat-form"><input aria-label="Sohbet mesajı" id="chatInput" maxlength="300" placeholder="Mesaj gönder veya /help yaz..."><button aria-label="Gönder" type="submit">➤</button></form>
        <div id="chatMessage" class="rail-message"></div>
      </section>

      <section class="rail-panel" data-panel="gifts">
        <div class="gift-wallet"><div><span>Coin bakiyen</span><strong id="myWallet">0</strong></div><button class="link-button" type="button" data-open-modal="bankPurchaseModal">Coin Satın Al</button></div>
        <div id="giftCatalog" class="gift-shop-grid"></div><p id="giftSelectionSummary" class="gift-selection-summary" aria-live="polite"></p>
        <div class="gift-quantity"><span>Adet</span><button id="quantityMinus" type="button">−</button><input id="giftQuantity" type="number" min="1" max="20" value="1"><button id="quantityPlus" type="button">+</button></div>
        <div class="gift-audio-box">
          <div><strong>♫ Hediye sesi ekle</strong><small>İstersen hediyeyle birlikte kısa bir MP3/WAV/OGG/M4A gönder. En fazla 8 MB.</small></div>
          <label class="audio-upload-btn">Ses seç<input id="giftAudioInput" type="file" accept="audio/mpeg,audio/wav,audio/ogg,audio/webm,audio/mp4" hidden></label>
          <div id="giftAudioPreview" class="audio-preview hidden"><audio id="giftAudioPlayer" controls></audio><button id="removeGiftAudio" type="button">Kaldır</button></div>
        </div>
        <button id="sendGiftBtn" class="btn btn-primary gift-send-btn" type="button">🎁 Hediyeyi Gönder</button>
        <div id="giftMessage" class="rail-message"></div>
        <div class="recent-gifts-head"><strong data-i18n="gifts">Hediyeler</strong><small>Toplam</small></div>
        <div id="giftEvents" class="recent-gifts"></div>
      </section>
    </aside>
  </main>

  <div class="app-modal" id="reportModal"><div class="modal-card"><button class="modal-close" type="button" data-close-modal="reportModal">×</button><span class="section-kicker">GÜVENLİK</span><h2>Yayın / Kullanıcı Şikayeti</h2><form id="reportForm" class="form-stack pro-form"><input type="hidden" name="targetUserId" value=""><input type="hidden" name="chatMessageId" value=""><label><span>Neyi şikayet ediyorsun?</span><select name="scope"><option value="creator">Yayıncı / kanal</option><option value="user">Kullanıcı</option><option value="stream">Bu canlı yayın</option><option value="chat_message">Seçili chat mesajı</option></select></label><label><span>Şikayet nedeni</span><select name="category"><option value="harassment">Taciz / zorbalık</option><option value="spam">Spam</option><option value="fraud">Dolandırıcılık</option><option value="hate">Nefret söylemi</option><option value="nudity">Uygunsuz içerik</option><option value="violence">Şiddet</option><option value="copyright">Telif</option><option value="other">Diğer</option></select></label><label><span>Açıklama</span><textarea name="description" rows="5" minlength="5" maxlength="500" required placeholder="Ne olduğunu kısaca açıkla"></textarea></label><button class="btn btn-danger" type="submit">Şikayeti Gönder</button></form><div id="reportMessage" class="form-message"></div></div></div>

  <div class="app-modal" id="bankPurchaseModal"><div class="modal-card wide-modal"><button class="modal-close" type="button" data-close-modal="bankPurchaseModal">×</button><span class="section-kicker">COIN</span><h2>Coin satın al</h2><p class="muted-note">Kart ödemesi PayTR güvenli iFrame üzerinden açılır. Kart bilgilerin BUFF sunucusuna gelmez.</p><div class="coin-package-grid modal-coin-grid"><button type="button" data-paytr-coins="500"><strong>500 coin</strong><small>49,90 TL</small></button><button type="button" data-paytr-coins="1500"><strong>1.500 coin</strong><small>129,90 TL</small></button><button type="button" data-paytr-coins="5000"><strong>5.000 coin</strong><small>399,90 TL</small></button><button type="button" data-paytr-coins="12000"><strong>12.000 coin</strong><small>849,90 TL</small></button></div><a class="btn btn-ghost full-btn" href="./settings.php#payments">Banka transferi ve ödeme ayarları</a><div class="security-note">Gerçek ödeme için `.env` içinde PayTR mağaza bilgileri tanımlanmalıdır.</div></div></div>

  <div class="app-modal age-gate-modal" id="ageGateModal"><div class="modal-card age-gate-card"><img src="./assets/img/buff-logo.png" alt="BUFF"><span class="section-kicker">İÇERİK UYARISI</span><h2>Bu yayın +18 olarak işaretlenmiş</h2><p>Yayıncı yetişkin içerik veya yoğun küfür / sert dil uyarısı kullanıyor. Devam etmek için onay vermelisin.</p><div id="ageGateWarnings" class="mini-tags"></div><div class="modal-actions"><a class="btn btn-ghost" href="./index.php">Geri Dön</a><button id="ageGateAcceptBtn" class="btn btn-primary" type="button">18+ olduğumu onayla ve devam et</button></div></div></div>

  <div class="app-modal" id="clipModal"><div class="modal-card"><button class="modal-close" type="button" data-close-modal="clipModal">×</button><span class="section-kicker">KLİP OLUŞTUR</span><h2>Canlı yayından klip</h2><p class="muted-note">Kayıt, butona bastıktan sonraki 30 veya 60 saniyeyi yakalar ve gerçek video dosyası olarak kaydeder.</p><label><span>Süre</span><select id="clipDuration"><option value="30">30 saniye</option><option value="60">60 saniye</option></select></label><label><span>Başlık</span><input id="clipTitle" maxlength="160" placeholder="Klip başlığı"></label><button id="clipRecordBtn" class="btn btn-primary full-btn" type="button">Kaydı Başlat</button><div id="clipProgress" class="form-message"></div></div></div>

  <div id="chatUserCard" class="chat-user-card hidden"></div>
  <div class="app-modal stream-ended-modal" id="streamEndedModal"><div class="modal-card stream-ended-card">
    <span class="stream-ended-icon">■</span><span class="section-kicker">YAYIN SONA ERDİ</span><h2>Canlı yayın bitti</h2>
    <p>Yayıncı yayını sonlandırdı. Ana sayfaya dönebilir veya bu sayfada kalabilirsin. Kayıt alındıysa yayın tekrarı hazır olduğunda burada da göstereceğiz.</p>
    <div class="modal-actions stream-ended-actions"><button id="stayOnEndedStreamBtn" class="btn btn-ghost" type="button">Burada Kal</button><a class="btn btn-primary" href="./index.php">Ana Sayfaya Dön</a><a id="openEndedReplayBtn" class="btn btn-secondary hidden" href="#">Yayın Tekrarını Aç</a></div>
    <small id="endedReplayHint" class="stream-ended-hint">Tekrar kaydı kontrol ediliyor…</small>
  </div></div>


  <script src="./assets/js/i18n.js?v=18.0.0"></script>
  <script src="./assets/js/common.js?v=18.0.0"></script>
  <script src="./assets/js/v12.js?v=18.0.0"></script>
  <script src="./assets/js/watch.js?v=18.0.0"></script>
  <script src="./assets/js/watch-v12.js?v=18.0.0"></script>
  <script src="./assets/js/v14.js?v=18.0.0"></script>
</body>
</html>
