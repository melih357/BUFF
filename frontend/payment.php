<!doctype html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>BUFF • Güvenli Ödeme</title>
  <link rel="stylesheet" href="./assets/css/style.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/v12.css?v=18.0.0">
  <link rel="stylesheet" href="./assets/css/motion-v15.css?v=18.0.0">
  <script src="./assets/js/motion-v15.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v17.css?v=18.0.0"><script src="./assets/js/v17.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v18.css?v=18.0.0">
</head>
<body class="app-body payment-page" data-page="payment">
  <header class="app-header kick-header-v9">
    <a class="logo-lockup logo-lockup-v9" href="./index.php"><img src="./assets/img/buff-logo.png" alt="BUFF"><span>BUFF</span></a>
    <div class="header-search compact-search"><strong>Güvenli Ödeme</strong></div>
    <div id="authActions" class="header-actions"></div>
  </header>

  <main class="payment-shell-v9">
    <section class="surface-card payment-card-v9">
      <span class="section-kicker">GERÇEK ÖDEME AKIŞI</span>
      <h1 id="paymentTitle">Coin satın al</h1>
      <p id="paymentIntro">Kartını seç veya yeni kart bilgilerini gir. Tam kart numarası ve CVV veritabanına kaydedilmez; gerçek tahsilat ödeme sağlayıcısının güvenli ekranında tamamlanır.</p>

      <form id="paymentPreForm" class="form-stack pro-form payment-pre-form">
        <div class="payment-package-summary"><span id="paymentLabel">Paket</span><strong id="selectedPackage">—</strong></div>
        <div class="payment-method-picker">
          <div class="payment-method-head"><strong>Ödeme kartı</strong><span>🔒 Güvenli</span></div>
          <div id="paymentSavedCards" class="payment-saved-cards"></div>
          <input type="hidden" id="selectedPaymentMethodId" value="">
          <div id="paymentNewCard" class="payment-new-card">
            <label><span>Kart üzerindeki ad</span><input id="paymentCardHolder" name="cardHolder" required maxlength="120" placeholder="Ad Soyad" autocomplete="cc-name"></label>
            <label><span>Kart numarası</span><input id="paymentCardNumber" name="cardNumber" required inputmode="numeric" maxlength="23" placeholder="0000 0000 0000 0000" autocomplete="cc-number"></label>
            <div class="form-row"><label><span>Son kullanma</span><input id="paymentCardExpiry" name="expiry" required maxlength="7" placeholder="AA/YYYY" autocomplete="cc-exp"></label><label><span>CVV</span><input id="paymentCardCvv" name="cvv" required inputmode="numeric" maxlength="4" placeholder="•••" autocomplete="cc-csc"></label></div>
            <label class="save-card-check"><input id="saveCardForLater" type="checkbox" checked><span>Bu kartı sonraki ödemeler için kaydet</span></label>
          </div>
        </div>
        <label><span>Telefon</span><input id="paymentPhone" name="phone" required placeholder="05xx xxx xx xx" autocomplete="tel"></label>
        <label><span>Fatura / iletişim adresi</span><textarea id="paymentAddress" name="address" rows="3" required placeholder="İlçe, şehir ve adres bilgisi"></textarea></label>
        <button class="btn btn-primary" type="submit">Güvenli Ödemeye Geç</button>
      </form>

      <div id="paymentResult" class="payment-result hidden"></div>
      <iframe id="paytrIframe" title="PayTR güvenli ödeme" class="payment-iframe hidden" scrolling="no" frameborder="0"></iframe>
      <div id="paymentMessage" class="form-message"></div>
      <div class="security-note">Kart numarası ve CVV BUFF veritabanına kaydedilmez. Kayıtlı kartta yalnızca marka, son 4 hane, son kullanma tarihi ve sağlayıcı/token referansı tutulur. Ödeme sağlayıcısı kartı güvenli ekranda yeniden doğrulayabilir.</div>
      <a class="btn btn-secondary" href="./settings.php#payments">Ödemelere Dön</a>
    </section>
  </main>

  <script src="./assets/js/i18n.js?v=18.0.0"></script>
  <script src="./assets/js/common.js?v=18.0.0"></script>
  <script src="./assets/js/v12.js?v=18.0.0"></script>
  <script src="./assets/js/payment.js?v=18.0.0"></script>
</body>
</html>
