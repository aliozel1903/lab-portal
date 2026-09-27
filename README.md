# Lab Portal

Laboratuvar tahlil sonuçlarının kaydedildiği ve hastaların kendi sonuçlarını
sorgulayabildiği tek sayfalık web uygulaması. Laravel 12 ve PostgreSQL ile
geliştirilmiştir.

## Özellikler

- **Hasta sorgulama** — Hasta, barkod numarası ve T.C. Kimlik Numarasının son
  4 hanesiyle yalnızca kendi sonucuna ulaşır.
- **Laborant paneli** — Tahlil sonucu ekleme, düzenleme, arama ve sayfalama.
- **Yönetici yetkileri** — Kayıt silme, çöp kutusu ve sistem işlem logları
  yalnızca `admin` rolündeki kullanıcılara açıktır.
- **Çöp kutusu** — Silinen kayıtlar kalıcı olarak kaybolmaz, geri yüklenebilir.
- **Resmî belge çıktısı** — Tahlil sonucu yazdırılabilir/PDF olarak kaydedilebilir.
- **İşlem logları** — Ekleme, güncelleme, silme ve geri yükleme işlemleri
  kullanıcı bilgisiyle birlikte kaydedilir.

Sorgulama ekranı, personel girişi ve yönetim paneli tek bir sayfada
(`/`) toplanmıştır; bölümler arası geçişte sayfa yenilenmez.

## Güvenlik Notları

- **T.C. Kimlik Numarası doğrulaması** — Hem sunucuda
  (`app/Rules/TurkishIdentityNumber.php`) hem tarayıcıda aynı algoritma çalışır:
  11 hane, yalnızca rakam, ilk hane sıfır olamaz ve iki basamak doğrulama
  kuralı sağlanmalıdır. Bu kuralın doğal sonucu olarak geçerli bir numara
  daima çift rakamla biter.
- **Kimlik numarası sızdırılmaz** — `Patient` modelinde varsayılan olarak
  gizlidir; yalnızca yetkili belge uç noktasında görünür kılınır. Herkese açık
  sorgulama yanıtında hiçbir koşulda yer almaz.
- **İstek sınırlama** — Giriş ve hasta sorgulama uç noktalarında IP başına
  dakikalık istek limiti vardır; barkod deneme yanılmasını engeller.
- **Token tabanlı kimlik doğrulama** — Laravel Sanctum. Çıkış yapıldığında
  token sunucu tarafında da iptal edilir.
- **XSS koruması** — Veritabanından gelen tüm metinler arayüze basılmadan önce
  kaçışlanır; hasta adı gibi alanlar ayrıca sunucuda biçim doğrulamasından geçer.

## Kurulum

Gereksinimler: PHP 8.2+, Composer, PostgreSQL, Node.js

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

`.env` dosyasındaki veritabanı bilgilerini ve `SEED_*` şifrelerini doldurun,
ardından:

```bash
php artisan migrate --seed
php artisan serve
```

Uygulama `http://localhost:8000` adresinde çalışır.

## Testler

```bash
./vendor/bin/pest
```

Testler kimlik numarası doğrulama algoritmasını, yetkilendirme kurallarını ve
herkese açık sorgulama uç noktasının veri sızdırmadığını kapsar.

## Dağıtım (Vercel)

Uygulama Vercel'in ücretsiz Hobby planında, topluluk PHP runtime'ı ile çalışır.
Gerekli dosyalar depoda hazırdır: `vercel.json`, `api/index.php`, `.vercelignore`.

Vercel sunucusuz (serverless) çalıştığı için iki kısıt vardır:

1. **Dosya sistemi salt-okunurdur.** `api/index.php`, Laravel'in önbellek, log
   ve derlenmiş görünüm dizinlerini `/tmp` altına yönlendirir. `/tmp` kalıcı
   değildir, bu yüzden veritabanı mutlaka harici olmalıdır (SQLite kullanılamaz).
2. **Her istek ayrı bir örnekte çalışabilir.** Oturum ve önbellek `file`
   sürücüsüyle çalışmaz. Özellikle giriş denemesi sınırlaması önbelleğe
   dayandığı için `CACHE_STORE=database` olmalıdır; `array` seçilirse sınırlama
   her istekte sıfırlanır ve işlevsiz kalır.

### Adımlar

1. Ücretsiz bir PostgreSQL veritabanı oluşturun (ör. Neon). Bağlantı bilgilerini alın.
2. Depoyu GitHub'a gönderin ve Vercel'de "Import Project" ile bağlayın.
3. Vercel proje ayarlarında aşağıdaki ortam değişkenlerini tanımlayın.
4. Veritabanı tablolarını yerelden oluşturun (Vercel build sırasında migration
   çalıştırılmaz — birden fazla örnek aynı anda başlayabilir):

```bash
php artisan migrate --force
php artisan db:seed --force
```

### Vercel ortam değişkenleri

| Değişken | Değer |
|---|---|
| `APP_KEY` | `php artisan key:generate --show` çıktısı |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | Vercel'in verdiği adres |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Veritabanı sağlayıcısından |
| `CACHE_STORE` | `database` |
| `SESSION_DRIVER` | `cookie` |
| `QUEUE_CONNECTION` | `sync` |
| `LOG_CHANNEL` | `stderr` |
| `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD` | Yönetici hesabı |
| `SEED_LAB_EMAIL` / `SEED_LAB_PASSWORD` | Laborant hesabı |

`APP_DEBUG` değerinin `false` kaldığından emin olun; aksi halde hata sayfaları
veritabanı bilgilerini açığa çıkarır.

## Proje Yapısı

```
app/Http/Controllers/Api/   API denetleyicileri (kimlik doğrulama, tahlil sonuçları)
app/Http/Middleware/        Yönetici yetki kontrolü
app/Models/                 Patient, TestResult, SystemLog, User
app/Rules/                  T.C. Kimlik Numarası doğrulama kuralı
resources/views/portal.blade.php   Tek sayfalık arayüz
resources/views/print.blade.php    Yazdırılabilir sonuç belgesi
routes/api.php              API rotaları
routes/web.php              Sayfa rotaları
tests/                      Pest testleri
```
