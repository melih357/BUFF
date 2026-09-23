<!doctype html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BUFF • Profil</title>
  <link rel="stylesheet" href="./assets/css/style.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v12.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v14.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/motion-v15.css?v=18.0.0"><link rel="stylesheet" href="./assets/css/v16-social.css?v=18.0.0">
  <script src="./assets/js/motion-v15.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v17.css?v=18.0.0"><script src="./assets/js/v17.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v18.css?v=18.0.0">
</head>
<body class="app-body profile-body" data-page="profile">
  <header class="app-header kickish-header">
    <a class="logo-lockup" href="./index.php"><img src="./assets/img/buff-logo.png" alt="BUFF"><span>BUFF</span></a>
    <nav class="header-nav"><a href="./index.php">Ana Akış</a><a href="./discover.php">Keşfet</a><a class="active" href="./profile.php">Profil</a><a href="./studio.php">Creator Studio</a><a href="./settings.php">Ayarlar</a></nav>
    <div class="header-search compact-search"><span>👤</span><strong>Profil Merkezi</strong></div>
    <div id="authActions" class="header-actions"></div>
  </header>

  <main class="profile-shell profile-shell-v8">
    <section class="profile-hero-card profile-hero-v8">
      <div class="profile-avatar-stack"><div id="profileAvatar" class="profile-avatar-xl">L</div><button class="avatar-edit-btn" type="button" data-open-modal="avatarModal">📷</button></div>
      <div class="profile-identity"><span class="section-kicker">PROFİL</span><h1 id="profileDisplayTitle">Profilim</h1><div class="profile-name-line"><p id="profileUsername">@kullanici</p><span id="profilePublicId" class="profile-public-id">BF00000000</span><div id="profileBadges"></div></div><p id="profileBioPreview">Biyografi eklenmedi.</p></div>
      <div class="profile-actions"><a class="btn btn-ghost" href="./settings.php">Ayarlar</a><a class="btn btn-primary" href="./studio.php">Stüdyoya Git</a></div>
    </section>

    <section class="profile-metrics profile-metrics-v8">
      <article><span>Takipçi</span><strong id="followerCount">0</strong><small>kanalını takip eden</small></article>
      <article><span>Coin Bakiyesi</span><strong id="walletBalance">0</strong><small>harcanabilir coin</small></article>
      <article><span>Toplam Hediye</span><strong id="giftIncome">0</strong><small>alınan coin</small></article>
      <article><span>Banka Bekleyen</span><strong id="bankPending">0</strong><small>çekim kuyruğu</small></article>
      <article><span>İade Talebi</span><strong id="refundCount">0</strong><small>bekleyen karar</small></article>
      <article><span>Yayın Durumu</span><strong id="streamStatus">Pasif</strong><small id="streamViewerSummary">Şu anda yayında değilsin</small></article>
    </section>

    <section class="profile-dashboard simplified-profile-grid">
      <div class="profile-main-column">
        <section class="dashboard-card">
          <div class="card-heading"><div><span class="section-kicker">KISA BİLGİLER</span><h2>Profil düzenle</h2></div></div>
          <form id="profileForm" class="form-stack pro-form">
            <label><span>Görünen ad</span><input name="displayName" id="displayName"></label>
            <label><span>Biyografi</span><textarea name="bio" id="bio" rows="4" maxlength="255" placeholder="Kendini ve yayınlarını anlat"></textarea></label>
            <button class="btn btn-primary" type="submit">Profili Kaydet</button>
          </form>
          <div id="profileMessage" class="form-message"></div>
        </section>

        <section class="dashboard-card" id="gifts">
          <div class="card-heading"><div><span class="section-kicker">GELEN HEDİYELER</span><h2>Son destekler</h2></div><div class="card-heading-actions"><span id="giftListCount" class="soft-pill">0 kayıt</span><button id="allReceivedGiftsBtn" class="btn btn-ghost btn-small" type="button">Tümünü Gör</button></div></div>
          <div id="receivedGiftList" class="support-grid support-scroll"></div>
        </section>

        <section class="dashboard-card" id="sent-gifts">
          <div class="card-heading"><div><span class="section-kicker">GÖNDERDİĞİM HEDİYELER</span><h2>Destek geçmişi</h2></div></div>
          <div id="sentGiftList" class="activity-list"></div>
        </section>

        <section class="dashboard-card" id="refunds">
          <div class="card-heading"><div><span class="section-kicker">İADE TALEPLERİ</span><h2>Gelen talepler</h2></div><span id="pendingRefundBadge" class="soft-pill">0 bekliyor</span></div>
          <div id="incomingRefundList" class="activity-list"></div>
        </section>
      </div>

      <aside class="profile-side-column">
        <section class="dashboard-card slim-focus-card"><div class="card-heading"><div><span class="section-kicker">HEDEF</span><h2>Topluluk hedefin</h2></div></div><div id="profileCommunityGoal" class="channel-goal-box"></div></section>
        <section class="dashboard-card slim-focus-card"><div class="card-heading"><div><span class="section-kicker">LİDERLER</span><h2>En iyi destekçilerin</h2></div></div><div id="profileTopSupporters" class="support-leaderboard"></div></section>
        <section class="dashboard-card slim-focus-card">
          <div class="card-heading"><div><span class="section-kicker">KANAL</span><h2>Yayın özetin</h2></div></div>
          <div id="myStreamCard" class="empty-state">Henüz yayın açmadın.</div>
        </section>
        <section class="dashboard-card slim-focus-card">
          <div class="card-heading"><div><span class="section-kicker">İŞLEMLER</span><h2>Cüzdan hareketleri</h2></div></div>
          <div id="transactionList" class="activity-list compact-activity-list"></div>
        </section>
        <section class="dashboard-card slim-focus-card">
          <div class="card-heading"><div><span class="section-kicker">KISAYOLLAR</span><h2>Hızlı erişim</h2></div></div>
          <div class="quick-link-stack"><a class="quick-link-item" href="./settings.php#payments">Coin / ödeme ayarları <span>→</span></a><a class="quick-link-item" href="./settings.php#membership">VIP üyelikler <span>→</span></a><a class="quick-link-item" href="./settings.php#support">Geri bildirim & destek <span>→</span></a></div>
        </section>
      </aside>
    </section>
  </main>

  <div class="app-modal" id="receivedGiftsModal"><div class="modal-card wide-modal support-modal-card"><button class="modal-close" type="button" data-close-modal="receivedGiftsModal">×</button><span class="section-kicker">TOPLULUK</span><h2>Tüm destekler</h2><div id="receivedGiftModalList" class="support-grid support-scroll support-scroll-modal"></div></div></div>

  <div class="app-modal" id="avatarModal"><div class="modal-card"><button class="modal-close" type="button" data-close-modal="avatarModal">×</button><span class="section-kicker">PROFİL</span><h2>Profil Fotoğrafı</h2><form id="avatarForm" class="form-stack pro-form" enctype="multipart/form-data"><input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required><small class="muted-note">JPG, PNG veya WEBP • maksimum 3 MB</small><button class="btn btn-primary" type="submit">Fotoğrafı Yükle</button></form><div id="avatarMessage" class="form-message"></div></div></div>

  <div class="app-modal" id="refundRequestModal"><div class="modal-card"><button class="modal-close" type="button" data-close-modal="refundRequestModal">×</button><span class="section-kicker">İADE</span><h2>Hediye iade talebi</h2><form id="refundRequestForm" class="form-stack pro-form"><input type="hidden" id="refundGiftEventId"><label><span>Neden iade istiyorsun?</span><textarea id="refundReason" minlength="8" maxlength="500" required placeholder="Örn. yanlışlıkla gönderildi"></textarea></label><button class="btn btn-primary" type="submit">Yayıncıya İlet</button></form><div id="refundRequestMessage" class="form-message"></div></div></div>

  <script src="./assets/js/i18n.js?v=18.0.0"></script>
  <script src="./assets/js/common.js?v=18.0.0"></script>
  <script src="./assets/js/v12.js?v=18.0.0"></script>
  <script src="./assets/js/profile.js?v=18.0.0"></script>
  <script src="./assets/js/v14.js?v=18.0.0"></script>
</body>
</html>
