<!doctype html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BUFF • Giriş Yap</title>
  <link rel="stylesheet" href="./assets/css/style.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v12.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/motion-v15.css?v=18.0.0">
  <script src="./assets/js/motion-v15.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v17.css?v=18.0.0"><script src="./assets/js/v17.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v18.css?v=18.0.0">
</head>
<body class="auth-body auth-v12" data-page="login">
  <div class="auth-language"><span>🌐</span><select data-language-select aria-label="Dil"><option value="tr">Türkçe</option><option value="en">English</option><option value="de">Deutsch</option><option value="ar">العربية</option><option value="es">Español</option></select></div>
  <main class="auth-v12-shell">
    <section class="auth-v12-visual">
      <div class="auth-v12-wall" aria-hidden="true"></div>
      <div class="auth-v12-overlay"></div>
      <div class="auth-v12-brand">
        <a class="auth-buff-logo" href="./index.php"><img src="./assets/img/buff-logo.png" alt="BUFF"></a>
        <span class="auth-eyebrow">CANLI YAYIN • TOPLULUK • CREATOR</span>
        <h1>Yayın dünyana tek hesaptan dön.</h1>
        <p>Canlı yayınları izle, topluluğuna katıl, kliplerini yönet ve Creator Studio üzerinden yayına çık.</p>
        <div class="auth-v12-pills"><span><i></i> Canlı yayın</span><span>💬 Gelişmiş chat</span><span>🎁 Destek sistemi</span></div>
        <div class="auth-v12-stats"><article><strong>5</strong><span>Arayüz dili</span></article><article><strong>30/60</strong><span>sn klip</span></article><article><strong>VOD</strong><span>Yayın tekrarları</span></article></div>
      </div>
    </section>
    <section class="auth-v12-formside">
      <div class="auth-v12-formcard">
        <div class="auth-mobile-brand"><img src="./assets/img/buff-logo.png" alt="BUFF"></div>
        <span class="section-kicker">HOŞ GELDİN</span>
        <h2>Hesabına giriş yap</h2>
        <p class="auth-subtitle">Takiplerin, yayın ayarların ve topluluğun kaldığı yerden devam etsin.</p>
        <form id="loginForm" class="form-stack pro-form auth-form-v12">
          <label><span>Kullanıcı adı veya e-posta</span><div class="auth-input-icon"><b>＠</b><input name="identity" required autocomplete="username" placeholder="kullaniciadi veya mail@adres.com"></div></label>
          <label><span>Şifre</span><div class="auth-password-wrap"><input id="loginPassword" type="password" name="password" required autocomplete="current-password" placeholder="••••••••"><button type="button" class="password-toggle" data-password-toggle="loginPassword" aria-label="Şifreyi göster">Göster</button></div></label>
          <div class="auth-form-meta"><label class="auth-check"><input type="checkbox" id="rememberLogin" checked><span>Beni hatırla</span></label><a href="./settings.php#support">Giriş desteği</a></div>
          <button class="btn btn-primary auth-submit auth-submit-v12" type="submit"><span>Giriş Yap</span><b>→</b></button>
        </form>
        <div id="formMessage" class="form-message"></div>
        <div class="auth-security-row"><span>🔒 Oturum güvenliği</span><span>•</span><span>Şifreler hash'li saklanır</span></div>
        <div class="auth-bottom">Henüz hesabın yok mu? <a href="./register.php">Ücretsiz kayıt ol</a></div>
      </div>
    </section>
  </main>
  <script src="./assets/js/i18n.js?v=18.0.0"></script>
  <script src="./assets/js/common.js?v=18.0.0"></script>
  <script src="./assets/js/v12.js?v=18.0.0"></script>
  <script src="./assets/js/auth.js?v=18.0.0"></script>
</body>
</html>
