<!doctype html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BUFF • Gözat</title>
  <link rel="stylesheet" href="./assets/css/style.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v12.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v14.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/motion-v15.css?v=18.0.0">
  <script src="./assets/js/motion-v15.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v17.css?v=18.0.0"><script src="./assets/js/v17.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v18.css?v=18.0.0">
</head>
<body class="app-body kick-page" data-page="home">
  <header class="app-header kick-header-v9">
    <button class="hamburger-btn" id="sidebarCollapseBtn" type="button" aria-label="Menü">☰</button>
    <a class="logo-lockup logo-lockup-v9" href="./index.php"><img src="./assets/img/buff-logo.png" alt="BUFF"><span>BUFF</span></a>
    <nav class="header-nav"><a href="./index.php">Ana Sayfa</a><a class="active" href="./discover.php">Gözat</a><a href="#followingSection">Takip Edilen Kanallar</a></nav>
    <div class="header-search v9-search"><span>⌕</span><input id="searchInput" type="search" placeholder="Yayın, kanal veya kategori ara"><kbd>Ctrl K</kbd></div>
    <div id="authActions" class="header-actions"></div>
  </header>

  <aside class="app-sidebar kick-sidebar-v9">
    <div class="sidebar-main-links">
      <a class="sidebar-nav" href="./index.php"><span>⌂</span><div><strong>Ana Sayfa</strong></div></a>
      <a class="sidebar-nav active" href="./discover.php"><span>▣</span><div><strong>Gözat</strong></div></a>
      <a class="sidebar-nav" href="#followingSection"><span>♡</span><div><strong>Takip Edilen Kanallar</strong></div></a>
    </div>
    <div class="sidebar-block">
      <div class="sidebar-title"><span>TAKİP ETTİKLERİN</span><span id="followingLiveCount" class="count-pill">0</span></div>
      <div id="followingChannels" class="channel-list"><div class="sidebar-empty">Takip ettiğin canlı kanallar burada görünür.</div></div>
    </div>
    <div class="sidebar-block">
      <div class="sidebar-title"><span>ÖNERİLEN KANALLAR</span><span id="liveCount" class="count-pill">0</span></div>
      <div id="sidebarLiveChannels" class="channel-list"></div>
    </div>
    <div class="sidebar-block sidebar-category-v9">
      <div class="sidebar-title"><span>KATEGORİLER</span><button class="sidebar-section-toggle" type="button" data-toggle-target="discoverSidebarCategories" aria-expanded="true">−</button></div>
      <div class="sidebar-category-list" id="discoverSidebarCategories">
        <a class="sidebar-category-link" href="#categories" data-side-category="Sohbet"><span>💬</span><span>Sohbet</span></a>
        <a class="sidebar-category-link" href="#categories" data-side-category="Oyun"><span>🎮</span><span>Oyun</span></a>
        <a class="sidebar-category-link" href="#categories" data-side-category="Müzik"><span>♫</span><span>Müzik</span></a>
        <a class="sidebar-category-link" href="#categories" data-side-category="E-spor"><span>🏆</span><span>E-spor</span></a>
      </div>
    </div>
  </aside>

  <main class="app-main kick-main-v9">
    <section class="discover-hero v9-discover-hero">
      <div id="heroStage" class="hero-stage"><div class="hero-skeleton"></div></div>
      <div id="heroRail" class="hero-rail"></div>
    </section>

    <section class="discovery-toolbar v9-discovery-toolbar">
      <div class="filter-group" id="discoveryFilters"><button class="filter-chip active" data-filter="all">Sana Özel</button><button class="filter-chip" data-filter="trend">Trend</button><button class="filter-chip" data-filter="new">Yeni</button><button class="filter-chip" data-filter="support">En Çok Desteklenen</button><button class="filter-chip" data-filter="following">Takip Ettiklerim</button></div>
      <div class="discover-sort"><span>Canlı yayınları keşfet</span><strong id="resultCount">0 sonuç</strong></div>
    </section>

    <section class="content-section" id="liveStreams"><div class="section-heading"><div><span class="section-kicker">CANLI</span><h2>Önerilen canlı kanallar</h2><p>İzleyici, destek ve ilgi alanlarına göre sıralanır.</p></div></div><div id="streamGrid" class="pro-stream-grid"></div></section>
    <section class="content-section" id="followingSection"><div class="section-heading"><div><span class="section-kicker">TAKİP</span><h2>Takip ettiğin yayıncılar</h2><p>Takip ettiğin kanallardan aktif olanlar.</p></div></div><div id="followingGrid" class="horizontal-cards"></div></section>
    <section class="content-section"><div class="section-heading"><div><span class="section-kicker">YENİ</span><h2>Yeni açılan yayınlar</h2><p>Yeni yayına çıkan kanalları erkenden keşfet.</p></div></div><div id="risingGrid" class="horizontal-cards"></div></section>
    <section class="content-section"><div class="section-heading"><div><span class="section-kicker">DESTEK</span><h2>Topluluğun desteklediği yayınlar</h2><p>Hediye etkileşimi yüksek yayınlar.</p></div></div><div id="supportedGrid" class="horizontal-cards"></div></section>
    <section class="content-section" id="categories"><div class="section-heading"><div><span class="section-kicker">KATEGORİLER</span><h2>Ne izlemek istiyorsun?</h2><p>İçerikleri ilgi alanına göre filtrele.</p></div></div><div id="categoryGrid" class="category-grid-pro"></div></section>
  </main>

  <script src="./assets/js/i18n.js?v=18.0.0"></script>
  <script src="./assets/js/common.js?v=18.0.0"></script>
  <script src="./assets/js/v12.js?v=18.0.0"></script>
  <script src="./assets/js/home.js?v=18.0.0"></script>
  <script src="./assets/js/v14.js?v=18.0.0"></script>
</body>
</html>
