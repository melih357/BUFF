<!doctype html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BUFF • Ana Akış</title>
  <link rel="stylesheet" href="./assets/css/style.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v12.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v14.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/motion-v15.css?v=18.0.0">
  <script src="./assets/js/motion-v15.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v17.css?v=18.0.0"><script src="./assets/js/v17.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v18.css?v=18.0.0">
</head>
<body class="app-body feed-body kick-page" data-page="feed">
  <header class="app-header kick-header-v9">
    <button class="hamburger-btn" id="sidebarCollapseBtn" type="button" aria-label="Menü">☰</button>
    <a class="logo-lockup logo-lockup-v9" href="./index.php"><img src="./assets/img/buff-logo.png" alt="BUFF"><span>BUFF</span></a>
    <nav class="header-nav"><a class="active" href="./index.php">Ana Sayfa</a><a href="./discover.php">Gözat</a><a href="./discover.php#followingSection">Takip Edilen Kanallar</a></nav>
    <div class="header-search v9-search"><span>⌕</span><input id="globalSearch" type="search" placeholder="Ara"><kbd>Ctrl K</kbd></div>
    <div id="authActions" class="header-actions"></div>
  </header>

  <aside class="app-sidebar kick-sidebar-v9 feed-sidebar">
    <div class="sidebar-main-links">
      <a class="sidebar-nav active" href="./index.php"><span>⌂</span><div><strong>Ana Sayfa</strong></div></a>
      <a class="sidebar-nav" href="./discover.php"><span>▣</span><div><strong>Gözat</strong></div></a>
      <a class="sidebar-nav" href="./discover.php#followingSection"><span>♡</span><div><strong>Takip Edilen Kanallar</strong></div></a>
      <a class="sidebar-nav" href="./settings.php#membership"><span>✦</span><div><strong>Üyelikler</strong></div></a>
    </div>
    <div class="sidebar-block">
      <div class="sidebar-title"><span>TAKİP ETTİĞİN KANALLAR</span><span id="feedFollowingCount" class="count-pill">0</span></div>
      <div id="feedSidebarFollowing" class="channel-list"><div class="sidebar-empty">Takip ettiğin canlı kanallar burada görünür.</div></div>
    </div>
    <div class="sidebar-block">
      <div class="sidebar-title"><span>ÖNERİLEN KANALLAR</span><span id="feedLiveCount" class="count-pill">0</span></div>
      <div id="feedSidebarLive" class="channel-list"></div>
    </div>
    <div class="sidebar-block sidebar-category-v9">
      <div class="sidebar-title"><span>KATEGORİLER</span><button class="sidebar-section-toggle" type="button" data-toggle-target="feedSidebarCategories" aria-expanded="true">−</button></div>
      <div class="sidebar-category-list" id="feedSidebarCategories">
        <a class="sidebar-category-link" href="./discover.php?q=Sohbet"><span>💬</span><span>Sohbet</span></a>
        <a class="sidebar-category-link" href="./discover.php?q=Oyun"><span>🎮</span><span>Oyun</span></a>
        <a class="sidebar-category-link" href="./discover.php?q=Müzik"><span>♫</span><span>Müzik</span></a>
        <a class="sidebar-category-link" href="./discover.php?q=E-spor"><span>🏆</span><span>E-spor</span></a>
      </div>
    </div>
  </aside>

  <main class="app-main feed-main kick-main-v9">
    <section id="feedFeatured" class="kick-home-feature"><div class="kick-feature-empty">Canlı yayınlar yükleniyor...</div></section>
    <section class="v9-welcome-strip">
      <div><span class="section-kicker">BUFF LIVE</span><h1>Topluluk akışı</h1><p>Canlı yayınları, takip ettiklerini ve topluluk gönderilerini tek yerde gör.</p></div>
      <div class="welcome-actions"><a class="btn btn-secondary" href="./discover.php">Gözat</a><a class="btn btn-primary" href="./studio.php">Canlı Yayın Aç</a></div>
    </section>

    <section class="feed-live-strip surface-card v9-live-strip">
      <div class="section-heading compact"><div><span class="section-kicker">CANLI</span><h2>Şimdi izleyebileceklerin</h2></div><a href="./discover.php">Tümünü gör →</a></div>
      <div id="feedLiveStrip" class="live-strip"></div>
    </section>

    <section class="home-collection-grid">
      <section class="surface-card v9-live-strip"><div class="section-heading compact"><div><span class="section-kicker">TAKİP</span><h2 data-i18n="following">Takip Ettiklerin</h2></div></div><div id="homeFollowingStrip" class="live-strip compact-home-strip"></div></section>
      <section class="surface-card v9-live-strip"><div class="section-heading compact"><div><span class="section-kicker">SANA ÖZEL</span><h2 data-i18n="for_you">Senin İçin</h2></div></div><div id="homeForYouStrip" class="live-strip compact-home-strip"></div></section>
      <section class="surface-card v9-live-strip"><div class="section-heading compact"><div><span class="section-kicker">TREND</span><h2 data-i18n="trend">Trend</h2></div></div><div id="homeTrendStrip" class="live-strip compact-home-strip"></div></section>
      <section class="surface-card v9-live-strip"><div class="section-heading compact"><div><span class="section-kicker">YENİ</span><h2>Yeni Yayıncılar</h2></div></div><div id="homeNewStrip" class="live-strip compact-home-strip"></div></section>
      <section class="surface-card v9-live-strip"><div class="section-heading compact"><div><span class="section-kicker">DESTEK</span><h2 data-i18n="most_supported">En Çok Desteklenen</h2></div></div><div id="homeSupportedStrip" class="live-strip compact-home-strip"></div></section>
    </section>

    
    <section class="feed-replay-strip surface-card v9-replay-strip">
      <div class="section-heading compact"><div><span class="section-kicker">TEKRARLAR</span><h2>Kaçırdığın yayın tekrarları</h2></div><a href="./replay.php">Tüm tekrarlar →</a></div>
      <div id="feedReplayStrip" class="replay-strip-grid"><div class="feed-empty">Yayın tekrarları yükleniyor...</div></div>
    </section>

    <section class="feed-replay-strip surface-card v9-replay-strip">
      <div class="section-heading compact"><div><span class="section-kicker">KLİPLER</span><h2 data-i18n="clips">Klipler</h2></div><a href="./clips.php">Tümünü gör →</a></div>
      <div id="feedClipStrip" class="replay-strip-grid"><div class="feed-empty">Klipler yükleniyor...</div></div>
    </section>

    <section class="feed-grid-layout v9-feed-layout">
      <div class="feed-column">
        <section id="postComposer" class="post-composer surface-card hidden v9-composer">
          <div id="composerAvatar"></div>
          <form id="postForm">
            <textarea name="body" maxlength="1000" rows="3" placeholder="Toplulukla bir şey paylaş..."></textarea>
            <div class="composer-actions"><label class="file-pill"><span data-i18n="upload_image">▧ Görsel</span><input id="postImage" type="file" name="image" accept="image/jpeg,image/png,image/webp" hidden></label><label class="file-pill"><span data-i18n="upload_video">▸ Kısa Video</span><input id="postVideo" type="file" name="video" accept="video/mp4,video/webm" hidden></label><button id="togglePollBtn" class="file-pill" type="button" data-i18n="poll">Anket</button><span id="postImageName" class="muted-note"></span><button class="btn btn-primary" type="submit" data-i18n="publish">Paylaş</button></div>
            <div id="pollComposer" class="poll-composer hidden"><input id="pollQuestion" type="text" maxlength="140" placeholder="Anket sorusu"><input class="poll-option-input" type="text" maxlength="90" placeholder="Seçenek 1"><input class="poll-option-input" type="text" maxlength="90" placeholder="Seçenek 2"><button id="addPollOption" type="button" class="btn btn-ghost btn-small">+ Seçenek</button></div>
          </form>
        </section>
        <section id="feedList" class="feed-list"><div class="feed-loading surface-card">Akış hazırlanıyor...</div></section>
      </div>

      <aside class="feed-right-column v9-right-rail">
        <section class="surface-card quick-start-card"><span class="section-kicker">HIZLI ERİŞİM</span><h3>Hesabını yönet</h3><a href="./profile.php">Profilim <span>→</span></a><a href="./settings.php#payments">Coin & Ödemeler <span>→</span></a><a href="./settings.php#membership">Plus / Pro / Premium <span>→</span></a><a href="./settings.php#support">Geri Bildirim <span>→</span></a></section>
        <section class="surface-card pro-tip-card"><span class="section-kicker">YAYINCI</span><h3>Daha okunaklı chat</h3><p>Creator Studio'da Chat Odak moduyla mesajları büyütüp diğer panelleri sadeleştirebilirsin.</p><a href="./studio.php">Creator Studio →</a></section>
      </aside>
    </section>
  </main>

  <script src="./assets/js/i18n.js?v=18.0.0"></script>
  <script src="./assets/js/common.js?v=18.0.0"></script>
  <script src="./assets/js/v12.js?v=18.0.0"></script>
  <script src="./assets/js/feed.js?v=18.0.0"></script>
  <script src="./assets/js/v14.js?v=18.0.0"></script>
</body>
</html>
