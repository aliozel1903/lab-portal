<?php

/**
 * Vercel serverless giriş noktası.
 *
 * Vercel'de uygulama dosyaları salt-okunurdur; yalnızca /tmp yazılabilir.
 * Laravel'in önbellek, oturum, log ve derlenmiş Blade dosyalarını oraya
 * yönlendiriyoruz. /tmp her çalıştırmada boşalabilir, bu yüzden kalıcı olması
 * gereken hiçbir veri buraya yazılmamalıdır (veritabanı harici olmalıdır).
 */
/*
 * Vercel, projeyi içe aktarırken .env.example'daki anahtarları değersiz
 * ortam değişkenleri olarak ekledi. Laravel boş bir değişkeni "ayarlanmamış"
 * değil "boş metin" olarak okur; örneğin bakım modu sürücüsü boş kalınca
 * her istek 500 verir. Boş değişkenleri kaldırarak config dosyalarındaki
 * varsayılan değerlerin devreye girmesini sağlıyoruz.
 */
foreach (array_keys(getenv()) as $key) {
    if (getenv($key) === '') {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}

$storagePath = '/tmp/storage';

$directories = [
    $storagePath.'/app/public',
    $storagePath.'/framework/cache/data',
    $storagePath.'/framework/sessions',
    $storagePath.'/framework/views',
    $storagePath.'/logs',
];

foreach ($directories as $directory) {
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

// Laravel bu değişkeni okuyup storage_path() çağrılarını /tmp'e yönlendirir
$_ENV['LARAVEL_STORAGE_PATH'] = $storagePath;
$_SERVER['LARAVEL_STORAGE_PATH'] = $storagePath;

/*
 * Laravel ilk istekte bootstrap/cache altına paket ve servis listelerini
 * yazar. Vercel'de o klasör salt-okunur olduğu için uygulama hata sayfası
 * bile üretemeden (boş gövdeli 500) düşer. Bu dosyaları da /tmp'e alıyoruz.
 */
$cachePaths = [
    'APP_SERVICES_CACHE' => $storagePath.'/bootstrap/services.php',
    'APP_PACKAGES_CACHE' => $storagePath.'/bootstrap/packages.php',
    'APP_CONFIG_CACHE' => $storagePath.'/bootstrap/config.php',
    'APP_ROUTES_CACHE' => $storagePath.'/bootstrap/routes.php',
    'APP_EVENTS_CACHE' => $storagePath.'/bootstrap/events.php',
];

if (! is_dir($storagePath.'/bootstrap')) {
    mkdir($storagePath.'/bootstrap', 0755, true);
}

foreach ($cachePaths as $key => $path) {
    $_ENV[$key] = $path;
    $_SERVER[$key] = $path;
    putenv($key.'='.$path);
}

/*
 * Bu dosya "api" klasöründe durduğu için Laravel, adresin başındaki "/api"
 * bölümünü uygulamanın taban yolu sanır ve onu isteğin yolundan siler.
 * Uygulamanın kendi API rotaları da "/api/..." altında olduğundan bu, tüm
 * API isteklerinin 404 dönmesine yol açar. Giriş betiğini kök dizindeymiş
 * gibi tanıtarak Laravel'in isteği olduğu gibi görmesini sağlıyoruz.
 */
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../public/index.php';

require __DIR__.'/../public/index.php';
