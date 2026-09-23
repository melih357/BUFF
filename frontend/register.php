<!doctype html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BUFF • Kayıt Ol</title>
  <link rel="stylesheet" href="./assets/css/style.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v12.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/motion-v15.css?v=18.0.0">
  <script src="./assets/js/motion-v15.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v17.css?v=18.0.0"><script src="./assets/js/v17.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v18.css?v=18.0.0">
</head>
<body class="auth-body auth-v12" data-page="register">
  <div class="auth-language"><span>🌐</span><select data-language-select aria-label="Dil"><option value="tr">Türkçe</option><option value="en">English</option><option value="de">Deutsch</option><option value="ar">العربية</option><option value="es">Español</option></select></div>
  <main class="auth-v12-shell">
    <section class="auth-v12-visual">
      <div class="auth-v12-wall" aria-hidden="true"></div>
      <div class="auth-v12-overlay"></div>
      <div class="auth-v12-brand">
        <a class="auth-buff-logo" href="./index.php"><img src="./assets/img/buff-logo.png" alt="BUFF"></a>
        <span class="auth-eyebrow">KÜRESEL TOPLULUĞA KATIL</span>
        <h1>İzleyici olarak başla, istediğin anda yayıncı ol.</h1>
        <p>Tek hesapla keşfet, topluluk gönderileri, klipler, yayın tekrarları ve Creator Studio deneyimine eriş.</p>
        <div class="auth-v12-pills"><span>🌐 5 dil</span><span>📱 Mobil uyumlu</span><span>🔐 Güvenli ödeme</span></div>
        <div class="auth-v12-stats"><article><strong>LIVE</strong><span>WebRTC yayın</span></article><article><strong>VIP</strong><span>Plus / Pro / Premium</span></article><article><strong>MOD</strong><span>Chat araçları</span></article></div>
      </div>
    </section>
    <section class="auth-v12-formside">
      <div class="auth-v12-formcard register-card">
        <div class="auth-mobile-brand"><img src="./assets/img/buff-logo.png" alt="BUFF"></div>
        <span class="section-kicker">YENİ HESAP</span>
        <h2>BUFF hesabını oluştur</h2>
        <p class="auth-subtitle">Hesap tipini şimdi seçebilirsin; daha sonra Ayarlar'dan değiştirebilirsin.</p>
        <form id="registerForm" class="form-stack pro-form auth-form-v12">
          <div class="form-row"><label><span>Kullanıcı adı</span><input name="username" required minlength="3" maxlength="24" autocomplete="username" placeholder="buffplayer"></label><label><span>Görünen ad</span><input name="displayName" required maxlength="60" placeholder="BUFF Player"></label></div>
          <label><span>E-posta</span><input type="email" name="email" required autocomplete="email" placeholder="mail@ornek.com"></label>
          <label><span>Şifre</span><div class="auth-password-wrap"><input id="registerPassword" type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="En az 8 karakter"><button type="button" class="password-toggle" data-password-toggle="registerPassword" aria-label="Şifreyi göster">Göster</button></div><div class="password-strength"><i id="passwordStrengthBar"></i><span id="passwordStrengthText">Güçlü bir şifre kullan</span></div></label>
          <fieldset class="account-mode-picker"><legend>Hesap tipi</legend><label><input type="radio" name="accountMode" value="viewer" checked><span><b>İzleyici</b><small>İzle, takip et, topluluğa katıl.</small></span></label><label><input type="radio" name="accountMode" value="creator"><span><b>Yayıncı</b><small>Creator Studio ve canlı yayın araçları açık gelir.</small></span></label></fieldset>
          <label class="auth-check terms-check"><input type="checkbox" required><span>Topluluk kurallarını ve hesap güvenliği koşullarını kabul ediyorum.</span></label>
          <button class="btn btn-primary auth-submit auth-submit-v12" type="submit"><span>Hesap Oluştur</span><b>→</b></button>
        </form>
        <div id="formMessage" class="form-message"></div>
        <div class="auth-bottom">Zaten hesabın var mı? <a href="./login.php">Giriş yap</a></div>
      </div>
    </section>
  </main>
  <script src="./assets/js/i18n.js?v=18.0.0"></script>
  <script src="./assets/js/common.js?v=18.0.0"></script>
  <script src="./assets/js/v12.js?v=18.0.0"></script>
  <script src="./assets/js/auth.js?v=18.0.0"></script>
</body>
</html>
