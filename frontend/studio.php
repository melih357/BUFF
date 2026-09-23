<!doctype html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BUFF • Yayın Stüdyosu</title>
  <link rel="stylesheet" href="./assets/css/style.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v12.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v14.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/motion-v15.css?v=18.0.0"><link rel="stylesheet" href="./assets/css/v16-social.css?v=18.0.0">
  <script src="./assets/js/motion-v15.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v17.css?v=18.0.0"><script src="./assets/js/v17.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v18.css?v=18.0.0">
<script src="./assets/js/chat-v18.js?v=18.0.0" defer></script>
</head>
<body class="app-body studio-body" data-page="studio">
  <header class="app-header kickish-header">
    <a class="logo-lockup" href="./index.php"><img src="./assets/img/buff-logo.png" alt="BUFF"><span>BUFF</span></a>
    <nav class="header-nav"><a href="./index.php">Ana Akış</a><a href="./discover.php">Keşfet</a><a class="active" href="./studio.php">Creator Studio</a><a href="./profile.php">Profil</a><a href="./settings.php">Ayarlar</a></nav>
    <div class="header-search compact-search"><span>●</span><strong>Creator Studio</strong></div>
    <div id="authActions" class="header-actions"></div>
  </header>

  <main class="studio-shell">
    <section class="studio-preview-card">
      <div class="studio-card-head"><div><span class="section-kicker">YAYIN ÖNİZLEME</span><h1>BUFF Creator Studio</h1></div><div class="studio-health"><span id="studioStatus" class="status-pill offline">ÇEVRİMDIŞI</span><span id="studioTopDelayBadge" class="delay-badge">⏱ İzleyici gecikmesi</span></div></div>
      <div class="studio-video-wrap">
        <video id="localVideo" autoplay playsinline muted></video>
        <div class="preview-overlay"><span id="previewLiveLabel">ÖNİZLEME</span><span id="previewViewerCount">◉ 0 izleyici</span></div>
        <div id="studioGiftLayer" class="gift-animation-layer"></div>
        <div id="cameraEmpty" class="camera-empty"><div>◉</div><strong>Kamera henüz hazırlanmadı</strong><small>Yayından önce görüntü ve sesini kontrol et.</small></div>
      </div>
      <div class="device-picker-row">
        <label><span>Kamera</span><select id="cameraSelect"><option value="">Varsayılan kamera</option></select></label>
        <label><span>Mikrofon</span><select id="microphoneSelect"><option value="">Varsayılan mikrofon</option></select></label>
      </div>
      <div class="studio-controls">
        <button id="prepareBtn" class="btn btn-secondary" type="button">◉ Kamerayı Hazırla</button>
        <button id="micBtn" class="round-control active" type="button" title="Mikrofon">🎙</button>
        <button id="cameraBtn" class="round-control active" type="button" title="Kamera">📷</button>
        <button id="screenBtn" class="round-control" type="button" title="Ekran paylaş">▣</button>
        <div class="control-spacer"></div>
        <button id="startBtn" class="btn btn-primary" type="button">● Yayını Başlat</button>
        <button id="stopBtn" class="btn btn-danger" type="button">■ Yayını Bitir</button>
      </div>
      <div id="studioMessage" class="form-message"></div>
    </section>

    <aside class="studio-settings-card">
      <div class="studio-card-head"><div><span class="section-kicker">YAYIN BİLGİLERİ</span><h2>Yayınını hazırla</h2></div></div>
      <form id="streamForm" class="form-stack pro-form">
        <label><span>Başlık</span><input name="title" maxlength="160" required placeholder="İzleyicinin dikkatini çeken bir başlık yaz"></label>
        <label><span>Kategori</span><input name="category" maxlength="80" required placeholder="Sohbet, oyun, müzik, IRL..."></label>
        <label><span>Açıklama</span><textarea name="description" maxlength="300" rows="4" placeholder="Yayında ne olacağını kısaca anlat"></textarea></label>
        <label><span>Etiketler</span><input name="tags" placeholder="Türkçe, Rekabetçi, Sohbet"></label>
        <div class="form-row"><label><span>Dil</span><select name="language"><option value="TR">Türkçe</option><option value="EN">English</option><option value="DE">Deutsch</option><option value="AR">العربية</option><option value="ES">Español</option></select></label><label><span>Yayın Gecikmesi</span><select name="delaySeconds" id="studioDelaySelect"><option value="0">0 sn • Anlık</option><option value="3">3 sn • Düşük</option><option value="5">5 sn • Dengeli</option><option value="10">10 sn • Güvenli</option><option value="15">15 sn • Moderasyon</option><option value="30">30 sn • Yüksek</option></select></label></div>
        <div class="content-safety-box">
          <span class="section-kicker">İÇERİK GÜVENLİĞİ</span>
          <label class="toggle-row"><div><strong>Yetişkin / +18 yayın</strong><small>Cinsel, yoğun korku, ağır şiddet veya yalnızca yetişkinlere uygun içerik varsa aç.</small></div><input type="checkbox" name="adultOnly" id="adultOnlyToggle"><span class="switch-ui"></span></label>
          <label class="toggle-row"><div><strong>Fazla küfür / sert dil</strong><small>Yoğun küfürlü veya sert dil içeren yayınlarda bunu işaretle. Bu açıkken +18 seçimi zorunludur.</small></div><input type="checkbox" name="matureLanguage" id="matureLanguageToggle"><span class="switch-ui"></span></label>
          <div class="vod-auto-record-card"><div><span class="section-kicker">OTOMATİK YAYIN KAYDI</span><strong>Yayın başladığı anda kayıt başlar</strong><small>Yayını bitirdiğinde BUFF sana “Tekrar olarak yüklensin mi?” diye sorar. Yükleme dersen Tekrarlar'a eklenir; yükleme dersen kayıt tarayıcıdan silinir.</small></div><span class="vod-auto-dot">REC</span></div>
          <div id="recordVodState" class="mini-status vod-record-state enabled">Yayın başlayınca otomatik kayıt başlayacak</div>
          <p class="security-note">Kural: Fazla küfürlü yayın açıyorsan <strong>+18</strong> işaretlemeden canlı yayına çıkamazsın. Gerek yoksa +18 seçme.</p>
        </div>
      </form>
      <section class="payout-choice-card earnings-info-card">
        <span class="section-kicker">YAYINCI KAZANCI</span>
        <div class="creator-earning-explainer"><strong>Hediyeler çekilebilir kazanç bakiyene eklenir.</strong><p>Coin satın alma ile yayıncı kazancı ayrıdır. IBAN / ödeme yönetimi artık Ayarlar → Ödemeler bölümündedir.</p><a class="btn btn-ghost btn-small" href="./settings.php#payments">Kazanç & Ödemeler</a></div>
      </section>
      <div class="share-card"><span>Yayın bağlantısı</span><div><input id="shareLinkInput" readonly placeholder="Yayın başladıktan sonra oluşur"><button id="copyShareBtn" type="button">Kopyala</button></div></div>
    </aside>

    <section class="studio-monitor-grid">
      <div class="monitor-card">
        <div class="monitor-head"><div><span class="section-kicker">CANLI CHAT</span><h3>Yayın sohbeti</h3></div><div class="monitor-head-actions"><span id="studioChatCount">0 mesaj</span><button id="chatSmallerBtn" class="btn btn-ghost btn-small" type="button">A−</button><button id="chatLargerBtn" class="btn btn-ghost btn-small" type="button">A+</button><button id="chatImportantStudioBtn" class="btn btn-ghost btn-small" type="button">Önemli</button><button id="chatVipStudioBtn" class="btn btn-ghost btn-small" type="button">MOD/VIP</button><button id="chatFocusBtn" class="btn btn-ghost btn-small" type="button">Chat Odak</button></div></div>
        <div id="studioChatList" class="chat-list studio-chat-list"><div class="rail-empty">Yayın başladığında chat burada görünür.</div></div>
        <form id="studioCommandForm" class="moderator-command-form"><input id="studioCommandInput" placeholder="Mesaj yaz veya /help ile komutları gör" aria-label="Yayıncı mesajı veya moderatör komutu"><button type="submit" data-i18n="send">Gönder</button></form>
        <div id="studioCommandMessage" class="rail-message"></div>
      </div>
      <div class="monitor-card">
        <div class="monitor-head"><div><span class="section-kicker">DESTEK AKIŞI</span><h3>Canlı hediyeler</h3></div><span id="studioGiftTotal">0 coin</span></div>
        <div id="studioGiftFeed" class="recent-gifts"><div class="rail-empty">Henüz hediye yok.</div></div>
      </div>
      <div class="monitor-card analytics-card">
        <div class="monitor-head"><div><span class="section-kicker">YAYIN SAĞLIĞI</span><h3>Canlı durum</h3></div><span class="health-dot"></span></div>
        <div class="analytics-grid"><div><span>İzleyici</span><strong id="studioViewerStat">0</strong></div><div><span>Bağlı kullanıcı</span><strong id="peerCountStat">0</strong></div><div><span>Hediye</span><strong id="studioGiftStat">0</strong></div><div><span>Gecikme</span><strong id="studioDelayStat">5 sn</strong></div><div><span>Bitrate</span><strong id="studioBitrateStat">0 kbps</strong></div><div><span>Bağlantı</span><strong id="studioConnectionStat">Hazır</strong></div></div>
      </div>

    <section class="creator-weekly-card" id="creatorWeeklyCard">
      <div class="creator-weekly-top">
        <div class="creator-weekly-brand"><span class="section-kicker">HAFTALIK VERİLER</span><h2>Yayın özeti</h2></div>
        <div class="creator-weekly-profile"><span id="weeklyCreatorAvatar" class="creator-avatar">B</span><div><strong id="weeklyCreatorName">BUFF yayıncısı</strong><small id="weeklyCreatorUsername">@buff</small></div></div>
        <span id="weeklyPeriodLabel" class="weekly-period-pill">Bu hafta</span>
      </div>
      <div class="creator-weekly-grid">
        <article><span>YAYIN SAYISI</span><strong id="weeklyStreamCount">0</strong></article>
        <article><span>TOPLAM YAYIN SÜRESİ</span><strong id="weeklyStreamDuration">0 dk</strong></article>
        <article><span>İZLENME SAATİ</span><strong id="weeklyWatchHours">0</strong></article>
        <article><span>TOPLAM MESAJ</span><strong id="weeklyMessageCount">0</strong></article>
        <article><span>FARKLI CHATTER</span><strong id="weeklyUniqueChatters">0</strong></article>
        <article><span>HAFTALIK COIN</span><strong id="weeklyGiftCoins">0</strong></article>
        <article><span>ZİRVE İZLEYİCİ</span><strong id="weeklyPeakViewers">0</strong></article>
        <article><span>ORT. İZLEYİCİ</span><strong id="weeklyAvgViewers">0</strong></article>
        <article><span>ZİRVE ANLIK CHATTER</span><strong id="weeklyPeakChatters">0</strong></article>
      </div>
      <p class="creator-weekly-note">İzleyici ve chatter zirveleri canlı yayın sırasında alınan kısa aralıklı örneklerden hesaplanır. Hediye coinleri ve mesajlar anlık olarak güncellenir.</p>
    </section>
    </section>
  </main>

  <div class="app-modal vod-decision-modal" id="vodDecisionModal" aria-hidden="true" data-static-modal="1">
  <div class="modal-card vod-decision-card">
    <div class="vod-decision-icon">▶</div>
    <span class="section-kicker">YAYIN KAYDI HAZIR</span>
    <h2>Bu yayını Tekrarlar'a yüklemek ister misin?</h2>
    <p>Yayın boyunca alınan kayıt cihazında hazır. Yüklersen kanalındaki Tekrarlar bölümünde izlenebilir; istemezsen kayıt gönderilmeden silinir.</p>
    <div id="vodDecisionDuration" class="mini-status">Kayıt hazır</div>
    <label><span>Görünürlük</span><select id="vodDecisionVisibility"><option value="public">Herkese açık</option><option value="unlisted">Liste dışı</option><option value="private">Gizli</option></select></label>
    <div class="vod-decision-actions"><button id="vodDiscardBtn" class="btn btn-ghost" type="button">Yükleme • Sil</button><button id="vodUploadBtn" class="btn btn-primary" type="button">Tekrar Olarak Yükle</button></div>
  </div>
</div>
<script src="./assets/js/i18n.js?v=18.0.0"></script>
  <script src="./assets/js/common.js?v=18.0.0"></script>
  <script src="./assets/js/v12.js?v=18.0.0"></script>
  <script src="./assets/js/studio.js?v=18.0.0"></script>
  <script src="./assets/js/v14.js?v=18.0.0"></script>
</body>
</html>
