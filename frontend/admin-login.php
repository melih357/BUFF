<!doctype html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BUFF • Admin Girişi</title>
  <link rel="stylesheet" href="./assets/css/style.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v12.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/motion-v15.css?v=18.0.0">
  <script src="./assets/js/motion-v15.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v17.css?v=18.0.0"><script src="./assets/js/v17.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v18.css?v=18.0.0">
</head>
<body class="auth-body auth-v12 admin-auth-page" data-page="admin-login">
  <main class="auth-v12-shell">
    <section class="auth-v12-visual">
      <div class="auth-v12-wall" aria-hidden="true"></div>
      <div class="auth-v12-overlay"></div>
      <div class="auth-v12-brand">
        <a class="auth-buff-logo" href="./index.php"><img src="./assets/img/buff-logo.png" alt="BUFF"></a>
        <span class="auth-eyebrow">BUFF CONTROL CENTER</span>
        <h1>Yönetim alanı.</h1>
        <p>Şikayetler, destek talepleri, kullanıcılar, yayınlar, ödemeler ve içerik yönetimi bu alandan kontrol edilir.</p>
      </div>
    </section>
    <section class="auth-v12-formside">
      <div class="auth-v12-formcard">
        <div class="auth-mobile-brand"><img src="./assets/img/buff-logo.png" alt="BUFF"></div>
        <span class="section-kicker">YÖNETİCİ</span>
        <h2>Admin paneline giriş</h2>
        <p class="auth-subtitle">Yalnızca yönetici yetkisi bulunan hesaplar devam edebilir.</p>
        <form id="adminLoginForm" class="form-stack pro-form auth-form-v12">
          <label><span>Admin e-posta / kullanıcı adı</span><div class="auth-input-icon"><b>＠</b><input name="identity" required autocomplete="username" placeholder="buffyonetim@gmail.com"></div></label>
          <label><span>Şifre</span><div class="auth-password-wrap"><input id="adminPassword" type="password" name="password" required autocomplete="current-password" placeholder="••••••••"><button type="button" class="password-toggle" id="adminPasswordToggle">Göster</button></div></label>
          <button class="btn btn-primary auth-submit auth-submit-v12" type="submit"><span>Admin Panelini Aç</span><b>→</b></button>
        </form>
        <div id="adminLoginMessage" class="form-message"></div>
        <div class="auth-security-row"><span>🛡 Yönetici oturumu</span><span>•</span><span>Yetki sunucu tarafında doğrulanır</span></div>
        <div class="auth-bottom"><a href="./index.php">← Siteye dön</a></div>
      </div>
    </section>
  </main>
  <script src="./assets/js/i18n.js?v=18.0.0"></script>
  <script src="./assets/js/common.js?v=18.0.0"></script>
  <script src="./assets/js/admin-login.js?v=18.0.0"></script>
</body>
</html>
