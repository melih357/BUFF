<!doctype html>
<html lang="tr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>BUFF • Klipler</title>
  <link rel="stylesheet" href="./assets/css/style.css?v=18.0.0"><link rel="stylesheet" href="./assets/css/v12.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v14.css?v=18.0.0"><link rel="stylesheet" href="./assets/css/motion-v15.css?v=18.0.0"><link rel="stylesheet" href="./assets/css/v16-social.css?v=18.0.0">
  <script src="./assets/js/motion-v15.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v17.css?v=18.0.0"><script src="./assets/js/v17.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v18.css?v=18.0.0">
</head>
<body class="app-body kick-page" data-page="clips">
<header class="app-header kick-header-v9">
  <button class="hamburger-btn" id="sidebarCollapseBtn" type="button" aria-label="Menü">☰</button>
  <a class="logo-lockup" href="./index.php"><img src="./assets/img/buff-logo.png" alt="BUFF"><span>BUFF</span></a>
  <nav class="header-nav"></nav>
  <div class="header-search v9-search"><span>⌕</span><input id="clipSearch" type="search" data-i18n-placeholder="search_placeholder" placeholder="Klip, kullanıcı veya kategori ara"><kbd>Ctrl K</kbd></div>
  <div id="authActions" class="header-actions"></div>
</header>
<aside class="app-sidebar kick-sidebar-v9"><div class="sidebar-main-links"></div><div class="sidebar-block"><div class="sidebar-title"><span data-i18n="categories">KATEGORİLER</span></div><div class="sidebar-category-list"><a class="sidebar-category-link" href="./discover.php?q=Sohbet"><span>💬</span><span>Sohbet</span></a><a class="sidebar-category-link" href="./discover.php?q=Oyun"><span>🎮</span><span>Oyun</span></a><a class="sidebar-category-link" href="./discover.php?q=Müzik"><span>♫</span><span>Müzik</span></a></div></div></aside>
<main class="app-main v12-library-page clips-v16-page">
  <section class="page-hero compact clip-v16-hero"><div><span class="section-kicker">BUFF CLIPS</span><h1>Klipler & kısa videolar</h1><p>Canlı yayından alınan anları veya kendi videonu toplulukla paylaş. Beğen, yorum yap, arkadaşlarına gönder ve repost et.</p></div><button id="openClipUploadBtn" class="btn btn-primary" type="button">＋ Video Yükle</button></section>
  <section class="clip-upload-card"><div><strong>Kendi klibini paylaş</strong><p>Yayıncı olman gerekmez. MP4, WEBM veya MOV videonu yükle; klip Keşfet akışında da görünür.</p></div><button id="openClipUploadBtn2" class="btn btn-ghost" type="button">Dosya Seç</button></section>
  <section class="surface-card library-surface">
    <div class="clip-v16-toolbar"><div class="section-heading compact"><div><span class="section-kicker">KEŞFET</span><h2>Topluluktan klipler</h2></div><span id="clipCount" class="mini-status">0</span></div><div class="hero-chip-row"><button class="filter-chip active" data-clip-filter="all">Tümü</button><button class="filter-chip" data-clip-filter="live">Yayından</button><button class="filter-chip" data-clip-filter="upload">Yüklenen</button></div></div>
    <div id="clipsGrid" class="media-library-grid clip-v16-grid"></div>
  </section>
</main>

<div class="app-modal" id="clipUploadModal"><div class="modal-card v16-modal"><button class="modal-close" type="button" data-close-modal="clipUploadModal">×</button><span class="section-kicker">YENİ KLİP</span><h2>Kendi videonu yükle</h2><form id="clipUploadForm" class="form-stack" enctype="multipart/form-data"><label><span>Başlık</span><input name="title" maxlength="160" required placeholder="Klibine kısa bir başlık ver"></label><label><span>Açıklama</span><textarea name="description" maxlength="500" rows="3" placeholder="İstersen kısa bir açıklama ekle"></textarea></label><label class="clip-file-drop"><span>Video dosyası</span><input name="video" type="file" accept="video/mp4,video/webm,video/quicktime" required><small>MP4 / WEBM / MOV • en fazla 500 MB</small></label><input type="hidden" name="durationSeconds" id="clipUploadDuration" value="30"><video id="clipUploadPreview" class="clip-upload-preview hidden" controls muted playsinline></video><div id="clipUploadMessage" class="form-message"></div><button class="btn btn-primary" type="submit">Klibi Yayınla</button></form></div></div>

<div class="app-modal" id="clipShareModal"><div class="modal-card v16-modal compact"><button class="modal-close" type="button" data-close-modal="clipShareModal">×</button><span class="section-kicker">ARKADAŞLARINLA PAYLAŞ</span><h2>Klibi gönder</h2><p class="muted-note">Karşılıklı takip ettiğin bir arkadaşını seç. Klip bağlantısı BUFF sohbetine gönderilir.</p><div id="clipShareContacts" class="group-contact-list"><div class="compact-empty">Kişiler yükleniyor…</div></div><button id="clipCopyLinkBtn" class="btn btn-ghost" type="button">Bağlantıyı Kopyala</button></div></div>

<div class="app-modal" id="clipPlayerModal"><div class="modal-card clip-player-modal-v16"><button class="modal-close" type="button" data-close-modal="clipPlayerModal">×</button><div id="clipGiftLayer" class="gift-animation-layer"></div><video id="clipPlayer" controls playsinline preload="metadata"></video><div class="clip-modal-meta"><div><h2 id="clipPlayerTitle">Klip</h2><p id="clipPlayerMeta" class="muted-note"></p></div><div id="clipModalActions" class="clip-engagement"></div></div><div class="clip-comment-panel"><div class="section-heading compact"><div><span class="section-kicker">TOPLULUK</span><h3>Yorumlar</h3></div><span id="clipCommentCount" class="mini-status">0</span></div><div id="clipCommentList" class="clip-comment-list"><div class="compact-empty">Yorumlar yükleniyor…</div></div><form id="clipCommentForm" class="clip-comment-form"><input id="clipCommentInput" maxlength="600" placeholder="Yorum yaz…"><button class="btn btn-primary btn-small" type="submit">Gönder</button></form></div></div></div>

<script src="./assets/js/i18n.js?v=18.0.0"></script><script src="./assets/js/common.js?v=18.0.0"></script><script src="./assets/js/v12.js?v=18.0.0"></script><script src="./assets/js/clips.js?v=18.0.0"></script><script src="./assets/js/v14.js?v=18.0.0"></script>
</body></html>
