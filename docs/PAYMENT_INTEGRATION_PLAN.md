# Logi Gerçek Ödeme Sistemi Entegrasyon Planı

## Önerilen ilk sağlayıcı: PayTR iFrame API

Türkiye odaklı ilk üretim entegrasyonu için **PayTR iFrame API** önerilir. Kart verisi Logi sunucusuna gelmez; Logi yalnızca ödeme siparişi oluşturur, PayTR'dan `iframe_token` alır ve ödeme formunu sağlayıcının iFrame'i içinde açar. Ödemenin kesin sonucu yönlendirme sayfasından değil, **PayTR Bildirim URL / callback** isteğinden alınmalıdır.

## Faz 1 — Coin satın alma

Akış:

1. Kullanıcı Ayarlar → Ödemeler bölümünde coin paketi seçer.
2. Logi `payment_orders` tablosunda benzersiz bir sipariş açar.
3. Backend PayTR iFrame token'ı oluşturur.
4. Kart / ödeme ekranı PayTR iFrame içinde gösterilir.
5. PayTR ödeme sonucunu Logi callback URL'sine POST eder.
6. Callback hash doğrulaması yapılır.
7. Sipariş daha önce işlenmediyse ve tutar doğruysa coin **bir kez** hesaba yüklenir.
8. Callback tekrar gelirse ikinci kez coin yüklenmez.

Bu yapı için V9 paketinde şu dosyalar hazırdır:

- `backend/api/payments/paytr/create.php`
- `backend/api/payments/paytr/callback.php`
- `backend/api/payments/paytr/status.php`
- `frontend/payment.php`
- `database/BUFF_DATABASE_SETUP.sql`

## Faz 2 — Canlı ortam gereksinimleri

`.env` içine PayTR mağaza panelindeki bilgiler girilecek:

```env
PAYMENT_PROVIDER=paytr
PUBLIC_BASE_URL=https://alanadiniz.com
PAYTR_MERCHANT_ID=...
PAYTR_MERCHANT_KEY=...
PAYTR_MERCHANT_SALT=...
PAYTR_TEST_MODE=1
PAYTR_DEBUG_ON=1
```

Test bittiğinde:

```env
PAYTR_TEST_MODE=0
PAYTR_DEBUG_ON=0
```

Callback URL örneği:

```text
https://alanadiniz.com/backend/api/payments/paytr/callback.php
```

`localhost` callback için yeterli değildir. Testte geçici public HTTPS tüneli veya gerçek test domaini gerekir.

## Faz 3 — Yayıncı para çekme

Coin satın alma ile yayıncı para çekme birbirinden ayrı tutulmalıdır.

- Kullanıcı satın aldığı coinleri hediye için harcar.
- Yayıncı hediyeden `creator_earnings` kazanır.
- Yayıncı Ayarlar → Ödemeler bölümünde IBAN doğrular.
- Minimum çekim limiti uygulanır.
- Çekim talebi `payout_requests` tablosuna düşer.
- İlk üretim sürümünde finans ekibi / admin onayından sonra banka transferi yapılır.
- Sonraki aşamada sağlayıcı destekliyorsa toplu payout / marketplace çözümü bağlanır.

Admin paneli geldiğinde payout onay, reddetme, fraud kontrolü ve finans raporu eklenmelidir.

## Güvenlik zorunlulukları

- Kart numarası / CVV hiçbir zaman Logi veritabanına yazılmamalı.
- Sadece sağlayıcı token'ları ve sipariş referansları tutulmalı.
- Callback hash doğrulanmadan bakiye yüklenmemeli.
- Aynı sipariş callback'i birden fazla kez gelebileceği için işlem idempotent olmalı.
- Ödeme tutarı callback'te Logi sipariş tutarıyla karşılaştırılmalı.
- Canlı ortam yalnızca HTTPS üzerinden çalışmalı.
- Merchant key / salt frontend'e gönderilmemeli.
- Ödeme ve payout hareketleri audit log ile tutulmalı.

## Admin paneline geçince eklenecek ödeme ekranları

- Ödeme siparişleri
- Başarılı / başarısız ödemeler
- Şüpheli tutar uyuşmazlıkları
- Coin iade işlemleri
- Yayıncı para çekme talepleri
- IBAN doğrulama durumu
- Fraud / chargeback notları
- Günlük / aylık finans raporları
