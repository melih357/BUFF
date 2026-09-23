# BUFF V18 — arayüz, canlı sohbet ve hediye akışı

## Kurulum

Mevcut V17 kurulumunda `frontend` klasörünü, `backend/api/gifts/events.php` dosyasını ve bu pakete eklenen font dosyalarını birlikte güncelleyin. Diğer proje dosyalarını da aynı paketten kullanın. Mevcut `.env` ve sunucudaki `uploads` klasörünü kendi kurulumunuzda koruyun. Yeni veritabanı migrasyonu gerekmez; V17 şeması kullanılır. Tarayıcıda eski dosyalar önbellekte kalırsa sayfayı yenileyin.

## Değişiklikler

- Koyu ve açık tema için ortak zemin, yüzey, yazı ve vurgu renkleri; daha yalın kart, menü, buton ve form düzeni. Manrope, Source Sans 3 ve Noto Sans Arabic fontlarının gereken alt kümeleri pakette bulunur.
- Ana sayfanın öne çıkan yayın alanı ve mobil başlığı yeniden düzenlendi. Menü, mevcut sayfa yolları, paylaşım/izleme/ödeme işlemleri ve admin sayfa yapısı korunur.
- Yayıncı sohbeti, uzun yayın ayarlarının üstüne, yayın önizlemesinin yanına alındı. Arama, @bahsetmeler filtresi, yanıt, yayıncı okuma alanı, zaman gösterimi, yazı büyütme ve klavye erişimli odak modu eklendi. Eski mesaj okunurken akış zorla aşağı kaymıyor; yeni mesaj sayısı ve canlı akışa dönüş düğmesi gösteriliyor. Mevcut /komutlar ile moderasyon işlemleri çalışmaya devam eder.
- Hediye, birim fiyat/coin bilgisi ve ×1 adediyle gösterilir. Aynı tıklama üst üste istek yollamaz. Ağ cevabı kaybolursa yenileme sonrasındaki tekrar denemede aynı işlem kimliği korunur; sunucu işlemi tekrarlamaz. Geçmiş sorgusu ile canlı bildirim aynı hediye olay kimliğini kullanır; görsel yalnızca bir kez oynatılır ve 2,5 saniye içinde kalkar. Destek listesi gerçek işlemlerin toplamını göstermeye devam eder.
- Hareketleri azalt ayarı ve sistem tercihi hem CSS geçişleri hem hediye geri bildiriminde uygulanır. Yeni sohbet etiketleri mevcut beş dilde sunulur.

## Kontrol

`backend/api/gifts/transaction.php` sunucuda her gönderim için tam bir hediye ve tek bakiye kaydı yapar. Bakiyenin sunucu cevabından önce başarı olarak gösterilmemesi korunmuştur. Çok yoğun hediyede en fazla beş bekleyen görsel tutulur; gerçek işlemler ve kayıtlı destek sayıları bundan etkilenmez. Canlı yayın ve ödeme sağlayıcısının üretim ortamı ayarları sunucunuzdaki yapılandırmaya bağlıdır.
