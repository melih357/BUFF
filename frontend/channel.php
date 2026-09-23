<!doctype html>
<html lang="tr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>BUFF • Kanal</title>
  <link rel="stylesheet" href="./assets/css/style.css?v=18.0.0"><link rel="stylesheet" href="./assets/css/v12.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v14.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/motion-v15.css?v=18.0.0">
  <script src="./assets/js/motion-v15.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v17.css?v=18.0.0"><script src="./assets/js/v17.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v18.css?v=18.0.0">
</head>
<body class="app-body kick-page" data-page="channel">
  <header class="app-header kick-header-v9">
    <button class="hamburger-btn" id="sidebarCollapseBtn" type="button">☰</button>
    <a class="logo-lockup" href="./index.php"><img src="./assets/img/buff-logo.png" alt="BUFF"><span>BUFF</span></a>
    <nav class="header-nav"></nav>
    <div class="header-search v9-search"><span>⌕</span><input id="channelSearch" type="search" data-i18n-placeholder="search_placeholder" placeholder="Yayın, kanal, kategori, tekrar veya klip ara"><kbd>Ctrl K</kbd></div>
    <div id="authActions" class="header-actions"></div>
  </header>
  <aside class="app-sidebar kick-sidebar-v9"><div class="sidebar-main-links"></div><div class="sidebar-block"><div class="sidebar-title"><span data-i18n="categories">KATEGORİLER</span></div><div class="sidebar-category-list"><a class="sidebar-category-link" href="./discover.php?q=Sohbet"><span>💬</span><span>Sohbet</span></a><a class="sidebar-category-link" href="./discover.php?q=Oyun"><span>🎮</span><span>Oyun</span></a><a class="sidebar-category-link" href="./discover.php?q=Müzik"><span>♫</span><span>Müzik</span></a></div></div></aside>

  <main class="channel-shell">
    <section class="channel-banner" id="channelBanner"></section>
    <section class="channel-header-card">
      <div id="channelAvatar" class="channel-avatar-xl">L</div>
      <div class="channel-head-copy"><div class="channel-live-line"><span id="channelLiveBadge" class="live-label hidden" data-i18n="live">CANLI</span></div><h1 id="channelName">Kanal yükleniyor...</h1><p id="channelHandle">@buff</p><p id="channelBio">—</p><div class="channel-meta"><span><strong id="channelFollowerCount">0</strong> <span data-i18n="followers">takipçi</span></span><span id="channelLiveMeta"></span></div></div>
      <div class="channel-head-actions"><button id="channelFollowBtn" class="btn btn-primary hidden" type="button" data-i18n="follow">Takip Et</button><a id="channelLiveBtn" class="btn btn-primary hidden" href="#" data-i18n="watch">Yayını İzle</a><a id="editChannelBtn" class="btn btn-ghost hidden" href="./settings.php#channel">Kanalı Düzenle</a></div>
    </section>

    <section class="channel-community-grid">
      <article class="channel-community-card"><div class="channel-card-head"><div><span class="section-kicker">TAKVİM</span><h2>Yayın takvimi</h2></div><span class="soft-pill">TR saati</span></div><div id="channelSchedule" class="channel-schedule-list"></div></article>
      <article class="channel-community-card"><div class="channel-card-head"><div><span class="section-kicker">HEDEF</span><h2>Topluluk hedefi</h2></div></div><div id="channelGoal" class="channel-goal-box"></div></article>
      <article class="channel-community-card"><div class="channel-card-head"><div><span class="section-kicker">LİDERLER</span><h2>En iyi destekçiler</h2></div></div><div id="channelLeaderboard" class="support-leaderboard"></div></article>
      <article class="channel-community-card"><div class="channel-card-head"><div><span class="section-kicker">SOSYAL</span><h2>Sosyal ağlar</h2></div></div><div id="channelSocialsTop" class="channel-social-grid"></div></article>
    </section>

    <nav class="channel-tabs" id="channelTabs">
      <button class="active" data-channel-tab="live">Canlı</button><button data-channel-tab="videos" data-i18n="videos">Videolar</button><button data-channel-tab="clips" data-i18n="clips">Klipler</button><button data-channel-tab="community" data-i18n="community">Topluluk</button><button data-channel-tab="about" data-i18n="about">Hakkında</button>
    </nav>
    <section class="channel-panel active" data-channel-panel="live"><div id="channelLivePanel"></div><section class="channel-support-board surface-card"><div class="channel-card-head"><div><span class="section-kicker">TOPLULUK</span><h2>Son destekler</h2><p>Aynı destekçinin art arda gönderdiği aynı hediyeler tek kartta toplanır.</p></div><button id="channelAllSupportsBtn" class="btn btn-ghost btn-small" type="button">Tümünü Gör</button></div><div id="channelRecentSupports" class="support-grid support-scroll"></div></section></section>
    <section class="channel-panel" data-channel-panel="videos"><div id="channelRecordings" class="media-library-grid"></div></section>
    <section class="channel-panel" data-channel-panel="clips"><div id="channelClips" class="media-library-grid"></div></section>
    <section class="channel-panel" data-channel-panel="community"><div id="channelPosts" class="feed-list"></div></section>
    <section class="channel-panel" data-channel-panel="about"><div class="surface-card" style="padding:18px"><h2 data-i18n="about">Hakkında</h2><p id="channelAboutBio">—</p><h3 data-i18n="social_links">Sosyal bağlantılar</h3><div id="channelSocials" class="mini-tags"></div></div></section>
  </main>
  <div class="app-modal" id="channelSupportsModal"><div class="modal-card wide-modal support-modal-card"><button class="modal-close" type="button" data-close-modal="channelSupportsModal">×</button><span class="section-kicker">TOPLULUK</span><h2>Tüm destekler</h2><div id="channelAllSupports" class="support-grid support-scroll support-scroll-modal"></div></div></div>
  <script src="./assets/js/i18n.js?v=18.0.0"></script><script src="./assets/js/common.js?v=18.0.0"></script><script src="./assets/js/v12.js?v=18.0.0"></script><script src="./assets/js/channel.js?v=18.0.0"></script>
  <script src="./assets/js/v14.js?v=18.0.0"></script>
</body></html>
