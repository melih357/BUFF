<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';
// bootstrap API'ler için JSON başlığı ayarlar; bu dosya gerçek HTML sayfasıdır.
header('Content-Type: text/html; charset=utf-8');
$adminId=(int)($_SESSION['admin_user_id']??0);
if($adminId<1){header('Location: ./admin-login.php');exit;}
$admin=fetch_user_by_id($adminId);
if(!$admin||empty($admin['is_admin'])||(($admin['account_status']??'active')!=='active')){unset($_SESSION['admin_user_id'],$_SESSION['admin_authenticated_at']);header('Location: ./admin-login.php');exit;}
if(!is_bootstrap_admin_account($admin)&&!admin_has_active_assignment($adminId)){
  try{db()->prepare('UPDATE users SET is_admin=0 WHERE id=?')->execute([$adminId]);}catch(Throwable $e){}
  unset($_SESSION['admin_user_id'],$_SESSION['admin_authenticated_at']);header('Location: ./admin-login.php');exit;
}
?>
<!doctype html>
<html lang="tr" data-theme="dark">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>BUFF Control Center</title>
  <script>try{var __buffAdminLang=(localStorage.getItem('buff_admin_language')||'tr').toLowerCase();if(__buffAdminLang!=='en')__buffAdminLang='tr';document.documentElement.dataset.i18nLanguage=__buffAdminLang;document.documentElement.lang=__buffAdminLang;}catch(e){}</script>
  <link rel="stylesheet" href="./assets/css/style.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v12.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/admin-v13.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/motion-v15.css?v=18.0.0">
  <script src="./assets/js/motion-v15.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v17.css?v=18.0.0"><script src="./assets/js/v17.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v18.css?v=18.0.0">
</head>
<body class="buff-admin-body" data-page="admin-v13">
<div class="admin-app-shell">
  <aside class="admin-v13-sidebar" id="adminSidebar">
    <div class="admin-v13-brand"><img src="./assets/img/buff-logo.png" alt="BUFF"><div><strong>CONTROL CENTER</strong><small>Yönetim Konsolu</small></div></div>
    <button class="admin-sidebar-collapse" id="adminSidebarCollapse" type="button">☰ <span>Menüyü daralt</span></button>
    <nav class="admin-v13-nav" id="adminNav">
      <button class="active" data-view="dashboard"><span>⌂</span><b>Dashboard</b></button>
      <div class="admin-nav-group"><em>İÇERİK YÖNETİMİ</em>
        <button data-view="content_titles"><span>▤</span><b>Katalog & Meta Veri</b></button>
        <button data-view="assets"><span>▧</span><b>Medya Deposu</b></button>
        <button data-view="seasons"><span>≡</span><b>Sezonlar</b></button>
        <button data-view="episodes"><span>▶</span><b>Bölümler</b></button>
        <button data-view="collections"><span>◆</span><b>Koleksiyonlar</b></button>
        <button data-view="categories"><span>⌗</span><b>Kategoriler</b></button>
        <button data-view="vod_clips"><span>◫</span><b>VOD / Klipler</b></button>
      </div>
      <div class="admin-nav-group"><em>KULLANICI & ABONELİK</em>
        <button data-view="users"><span>◉</span><b>Kullanıcılar</b></button>
        <button data-view="sessions"><span>⌁</span><b>Oturumlar / Cihazlar</b></button>
        <button data-view="memberships"><span>✦</span><b>Paketler</b></button>
        <button data-view="user_memberships"><span>♢</span><b>Aktif Üyelikler</b></button>
        <button data-view="payments"><span>₺</span><b>Ödemeler & Faturalar</b></button>
      </div>
      <div class="admin-nav-group"><em>YAYIN OPERASYONLARI</em>
        <button data-view="streams"><span>●</span><b>Canlı Yayınlar</b></button>
        <button data-view="stream_keys"><span>⌘</span><b>Stream Key</b></button>
        <button data-view="epg"><span>◷</span><b>EPG Takvimi</b></button>
        <button data-view="transcoding"><span>⇄</span><b>Transcoding</b></button>
        <button data-view="drm"><span>◇</span><b>DRM / Geo-block</b></button>
        <button data-view="security"><span>🛡</span><b>Güvenlik / IP</b></button>
      </div>
      <div class="admin-nav-group"><em>MODERASYON & DESTEK</em>
        <button data-view="reports"><span>⚑</span><b>Kanal / İçerik Şikayetleri</b><i id="reportBadge"></i></button>
        <button data-view="site_complaints"><span>!</span><b>Site Şikayetleri</b></button>
        <button data-view="support"><span>?</span><b>Destek Kutusu</b><i id="supportBadge"></i></button>
      </div>
      <div class="admin-nav-group"><em>ANALİTİK & FİNANS</em>
        <button data-view="analytics"><span>▥</span><b>İzleyici Analizleri</b></button>
        <button data-view="revenue"><span>↗</span><b>Gelir & Finans</b></button>
      </div>
      <div class="admin-nav-group"><em>SİSTEM & ROLLER</em>
        <button data-view="roles"><span>♙</span><b>RBAC Roller</b></button>
        <button data-view="role_users"><span>♟</span><b>Rol Atamaları</b></button>
        <button data-view="announcements"><span>◔</span><b>Bildirim Yönetimi</b></button>
        <button data-view="settings"><span>⚙</span><b>Site Ayarları</b></button>
        <button data-view="logs"><span>≣</span><b>Sistem & Audit Logları</b></button>
      </div>
    </nav>
    <div class="admin-v13-user"><span class="admin-avatar">A</span><div><strong>BUFF Admin</strong><small>Süper Admin</small></div><button id="adminLogoutBtn" title="Çıkış">↪</button></div>
  </aside>

  <main class="admin-v13-main">
    <header class="admin-v13-topbar">
      <button id="adminMobileMenu" class="admin-mobile-menu" type="button">☰</button>
      <div class="admin-global-search-wrap"><div class="admin-global-search"><span>⌕</span><input id="adminSearch" type="search" placeholder="İçerik, kullanıcı, e-posta veya fatura/ID ara"><kbd>Ctrl K</kbd></div><div id="adminSearchResults" class="admin-search-results hidden"></div></div>
      <div class="admin-top-actions"><span class="admin-role-pill">SUPER ADMIN</span><label class="admin-language-select" title="Dil / Language"><span>🌐</span><select id="adminLanguageSelect"><option value="tr">TR</option><option value="en">EN</option></select></label><button id="adminAccountBtn" class="admin-icon-btn admin-account-btn" type="button" title="Admin hesabı">Hesabım</button><button id="adminRefreshBtn" class="admin-icon-btn" title="Yenile">↻</button><button class="admin-quick-btn" data-quick="content_titles">+ Yeni İçerik</button><a class="admin-quick-btn secondary" href="./studio.php" target="_blank">● Yayın Başlat</a></div>
    </header>

    <section class="admin-v13-content">
      <div class="admin-page-head"><div><span id="adminKicker">YÖNETİM</span><h1 id="adminPageTitle">Dashboard</h1><p id="adminPageDesc">Platformun canlı durumu, moderasyon kuyruğu ve operasyon metrikleri.</p></div><div id="adminContextActions" class="admin-context-actions"></div></div>

      <section id="adminDashboard" class="admin-dashboard-view">
        <div id="adminMetrics" class="admin-v13-metrics"></div>
        <div class="admin-dashboard-grid">
          <article class="admin-v13-card span-2"><div class="admin-card-head"><div><span>OPERASYON</span><h2>Platform Sağlığı</h2></div><span class="health-dot ok"></span></div><div id="adminHealth" class="health-grid"></div></article>
          <article class="admin-v13-card"><div class="admin-card-head"><div><span>MODERASYON</span><h2>Son Şikayetler</h2></div><button data-jump="reports">Tümü →</button></div><div id="recentReports" class="admin-activity-list"></div></article>
          <article class="admin-v13-card"><div class="admin-card-head"><div><span>DESTEK</span><h2>Son Talepler</h2></div><button data-jump="support">Tümü →</button></div><div id="recentSupport" class="admin-activity-list"></div></article>
          <article class="admin-v13-card span-2"><div class="admin-card-head"><div><span>CANLI</span><h2>Yayın Operasyonları</h2></div><button data-jump="streams">Yönet →</button></div><div id="liveOperations" class="admin-activity-list two-col"></div></article>
          <article class="admin-v13-card span-2"><div class="admin-card-head"><div><span>GELİR</span><h2>Aylık Gelir Eğilimi</h2></div><button data-jump="revenue">Detay →</button></div><div id="revenueChart" class="admin-bar-chart"></div></article>
        </div>
      </section>

      <section id="adminDataView" class="admin-data-view hidden">
        <div class="admin-v13-card table-card"><div class="admin-table-toolbar"><div><strong id="adminTableTitle">Kayıtlar</strong><small id="adminRowCount">0 kayıt</small></div><div id="adminTableActions" class="admin-table-actions"></div></div><div class="admin-v13-table-wrap"><table class="admin-v13-table"><thead id="adminTableHead"></thead><tbody id="adminTableBody"></tbody></table></div><div id="adminTableEmpty" class="admin-empty hidden"><span>⌕</span><strong>Kayıt bulunamadı</strong><p>Bu modülde henüz gösterilecek veri yok.</p></div></div>
      </section>
    </section>
  </main>
</div>

<div class="admin-modal" id="adminFormModal"><div class="admin-modal-card"><div class="admin-modal-head"><div><span>HIZLI İŞLEM</span><h2 id="adminFormTitle">Yeni kayıt</h2></div><button data-close="adminFormModal">×</button></div><form id="adminDynamicForm" class="admin-form-grid"></form></div></div>
<div class="admin-modal" id="adminReplyModal"><div class="admin-modal-card small"><div class="admin-modal-head"><div><span>YANIT</span><h2 id="adminReplyTitle">Kullanıcıya yanıt ver</h2></div><button data-close="adminReplyModal">×</button></div><form id="adminReplyForm" class="admin-form-grid"><input type="hidden" name="id"><input type="hidden" name="action"><label class="full"><span>Mesaj</span><textarea name="reply" rows="7" maxlength="2000" required placeholder="Kullanıcının bildirim kutusuna düşecek yanıtı yaz..."></textarea></label><button class="admin-primary-btn full" type="submit">Yanıtla ve bildir</button></form></div></div>
<div class="admin-modal" id="adminPreviewModal"><div class="admin-modal-card preview"><div class="admin-modal-head"><div><span>MEDYA ÖNİZLEME</span><h2 id="previewTitle">Önizleme</h2></div><button data-close="adminPreviewModal">×</button></div><div id="adminPreviewBody" class="admin-preview-body"></div></div></div>

<div class="admin-modal" id="adminVipModal"><div class="admin-modal-card small"><div class="admin-modal-head"><div><span>ÜYELİK</span><h2>VIP üyelik tanımla</h2></div><button data-close="adminVipModal">×</button></div><form id="adminVipForm" class="admin-form-grid"><input type="hidden" name="userId"><label><span>Üyelik seviyesi</span><select name="tier" required><option value="plus">Plus</option><option value="pro">Pro</option><option value="premium">Premium</option></select></label><label><span>Süre (gün)</span><input type="number" name="days" min="1" max="3650" value="30" required></label><button class="admin-primary-btn full" type="submit">Üyeliği Tanımla</button></form></div></div>
<div class="admin-modal" id="adminGrantModal"><div class="admin-modal-card small"><div class="admin-modal-head"><div><span>YETKİ</span><h2>Admin yetkisi ver</h2></div><button data-close="adminGrantModal">×</button></div><form id="adminGrantForm" class="admin-form-grid"><input type="hidden" name="userId"><label><span>Rol</span><select name="roleCode" required><option value="super_admin">Süper Admin</option><option value="content_manager">İçerik Yöneticisi</option><option value="editor">Editör</option><option value="support_agent">Müşteri Temsilcisi</option></select></label><label><span>Yetki süresi</span><select name="duration" required><option value="1h">1 saat</option><option value="3h">3 saat</option><option value="1d">1 gün</option><option value="7d">1 hafta</option><option value="permanent">Süresiz</option></select></label><button class="admin-primary-btn full" type="submit">Yetkiyi Ver</button></form></div></div>
<div class="admin-modal" id="adminAccountModal"><div class="admin-modal-card"><div class="admin-modal-head"><div><span>ADMIN HESABI</span><h2>E-posta ve şifre</h2></div><button data-close="adminAccountModal">×</button></div><div class="admin-account-settings"><form id="adminEmailForm" class="admin-form-grid"><h3 class="full">E-posta değiştir</h3><label class="full"><span>Yeni e-posta</span><input type="email" name="email" required autocomplete="email"></label><label class="full"><span>Mevcut şifre</span><input type="password" name="currentPassword" required autocomplete="current-password"></label><button class="admin-primary-btn full" type="submit">E-postayı Güncelle</button><div id="adminEmailMessage" class="form-message full"></div></form><form id="adminPasswordForm" class="admin-form-grid"><h3 class="full">Şifre değiştir</h3><label><span>Mevcut şifre</span><input type="password" name="currentPassword" required autocomplete="current-password"></label><label><span>Yeni şifre</span><input type="password" name="newPassword" minlength="8" required autocomplete="new-password"></label><label class="full"><span>Yeni şifre tekrar</span><input type="password" name="confirmPassword" minlength="8" required autocomplete="new-password"></label><button class="admin-primary-btn full" type="submit">Şifreyi Güncelle</button><div id="adminPasswordMessage" class="form-message full"></div></form></div></div></div>

<div id="adminToastRoot" class="admin-toast-root"></div>

<script src="./assets/js/i18n.js?v=18.0.0"></script><script src="./assets/js/common.js?v=18.0.0"></script><script src="./assets/js/admin-v13.js?v=18.0.0"></script>
</body></html>
