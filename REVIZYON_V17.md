# BUFF V17 — revizyon ve güncelleme

Bu paket PHP/MySQL uygulamasını, Node gerçek zamanlı servisini, medya worker'ını, frontend'i, mevcut uploads klasörünü ve tek veritabanı kurulum dosyasını içerir.

## Yapılan değişiklikler

- Mesajlar ekranında masaüstü menüsü daraltılıp açılır. Mobil menü düğme, dış alan veya Escape ile kapanır.
- Canlı yayın, klip ve tekrar hediyelerinde her gönderim bir hediyedir. Coin, kazanç ve hediye kaydı tek veritabanı işlemiyle kesinleşir. Aynı işlem kimliğiyle gelen tekrar veya eşzamanlı istek ikinci hediye oluşturmaz. Buton işlem sırasında kilitlenir; animasyon aynı olay için bir kez oynar ve temizlenir. Mesajlardaki coin transferi de tekrar isteklere karşı korunur.
- Ayarlar > Yayın bölümünden yeni tekrarların varsayılan görünürlüğü ve mevcut tekrarların görünürlüğü değiştirilebilir. Kayıt sahibinin tekrar oynatıcı ekranında da görünürlük seçimi vardır. Yeni kayıt varsayılanı gizlidir; önceki kayıtların tercihi korunur.
- Ayarlar > Gizlilik bölümünden hesap açık/gizli yapılabilir. Gizli hesap için takip isteği ve bildirim oluşturulur. Profilde istekler kabul/reddedilir, takipçi ve takip edilen kişiler listelenir.
- Coin satın alma ekranında 100, 500, 1.500, 3.000, 5.000 ve 12.000 coin paketleri bulunur. Ücretler sunucudaki tek paket tablosundan alınır; PayTR sipariş tutarı sunucuda doğrulanır.
- Kullanıcı klipleri 1 MB parçalarla yüklenir; PHP'nin tek dosya limitinden kaynaklanan büyük video hatası giderilmiştir. Tamamlanmış yüklemenin yeniden gönderilmesi ikinci içerik oluşturmaz.
- Anlar sayfasında fotoğraf, video ve metin paylaşılır. Profilde paylaşımlar ve klipler ayrı sekmelerdedir. Beğenenler, yorumlar ve yorumlara yanıtlar görüntülenir.
- Vurgu rengi ortak tema değişkenlerini günceller; arka plandaki yenilemeler seçimi sıfırlamaz. Yeni metinlere TR/EN/DE/AR/ES çevirileri eklenmiştir.
- Admin menüsünde ilk satırın sıkışıp görünmez olması giderilmiştir. Kart, düğme ve hediye animasyonlarında hareket azaltma tercihi uygulanır.
- Kurulumdaki SQL sonuç kümeleri doğru kapatılır; tekrar kurulum ve V16'dan eklemeli şema güncellemesi desteklenir.

## Tekrar görünürlükleri

| Seçim | Erişim |
| --- | --- |
| Herkese açık | Açık hesapta herkes; gizli hesapta sahibi ve onaylı takipçiler |
| Liste dışı | Listelerde görünmez; bağlantısı olan ve hesap gizliliğine göre yetkili kişiler |
| Gizli | Yalnızca kayıt sahibi |
| VIP | Kayıt sahibi ve aktif VIP üyeleri; hesap gizliliği kontrolü de uygulanır |

Liste dışı bir gizlilik parolası değildir. Gizli seçimi yalnızca sahibinin erişimine izin verir. VIP süresi bitince video dosyası isteği de engellenir. Gizli hesaba geçmek, zaten onaylı takipçileri silmez.

## Mevcut sunucuyu güncelleme

1. Veritabanını, mevcut `.env` dosyanızı ve `uploads` klasörünü yedekleyin.
2. Paket kodunu güncelleyin. Kendi sunucunuzdaki `.env` değerlerini ve mevcut yüklemeleri koruyun; test/örnek ayarlarla değiştirmeyin.
3. Aynı veritabanını kullanarak `install.php` dosyasını bir kez çalıştırın. Alternatif olarak `npm run db:setup` kullanabilirsiniz. Tek şema `database/BUFF_DATABASE_SETUP.sql` dosyasıdır; veritabanını silmeyin.
4. Node 20 veya sonrası ile `npm install` çalıştırın. Node servisini ve `npm run worker` sürecini yeniden başlatın. FFmpeg/FFprobe worker sisteminde bulunmalıdır.
5. PHP için PDO MySQL, fileinfo, mbstring ve OpenSSL gerekir. PayTR ve S3/R2 kullanıldığında cURL de gerekir. `upload_max_filesize` en az 2 MB, `post_max_size` en az 3 MB olmalıdır. Canlı yayın kaydının mevcut 4 MB parça sınırı için sunucudaki daha yüksek VOD yükleme limitlerini koruyun.
6. Apache'de paketteki `.htaccess` kurallarını etkin tutun. Nginx'te `docs/nginx-buff.conf.example` içindeki korunan medya klasörü kuralını uygulayın. PHP geliştirme sunucusu `.htaccess` okumaz; üretimde gizlilik için uygun Apache/Nginx yapılandırması gerekir.
7. Tarayıcıda sert yenileme yapın. Yeni varlık sürümü `17.0.0` olduğundan eski JS/CSS önbelleği kullanılmaz.

Medya artık oturum ve görünürlük kontrolü yapan `backend/api/media/file.php` üzerinden oynatılır. `uploads/clips`, `recordings`, `feed`, `thumbnails`, `variants` ve `.chunks` klasörlerine doğrudan web erişimi kapatılmalıdır. S3/R2 kullanıyorsanız nesneler özel olmalı; eski, herkese açık CDN bağlantıları uygulama koduyla geri alınamaz. Eski bağlantıların izin/önbellek ayarını da güncelleyin.

Kullanıcı klibi en fazla 500 MB, Anlar videosu 80 MB, Anlar fotoğrafı 8 MB'dır. MP4, WEBM, MOV ve OGV içerik tipi doğrulanır. Telefon videolarının tarayıcı uyumlu H.264/AAC MP4 dönüşümü worker ile yapılır; dönüşüm bitene kadar kaynak biçimin oynatılabilmesi tarayıcı codec desteğine bağlıdır.

## Doğrulama sınırı

Paket yerel PHP, MySQL, FFmpeg ve Chromium ile doğrulanmıştır; kontrol sonuçları `BUILD_VALIDATION_V17.txt` içindedir. Gerçek PayTR tahsilatı, canlı sunucudaki S3/R2 izinleri, iki gerçek cihaz arasında kamera yayını ve üretim yük testi bu test ortamında yapılmamıştır. Bu paket canlı sunucuya otomatik yüklenmez.
