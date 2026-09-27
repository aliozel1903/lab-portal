<?php

/**
 * Vercel serverless giriş noktası.
 *
 * Vercel'de uygulama dosyaları salt-okunurdur; yalnızca /tmp yazılabilir.
 * Laravel'in önbellek, oturum, log ve derlenmiş Blade dosyalarını oraya
 * yönlendiriyoruz. /tmp her çalıştırmada boşalabilir, bu yüzden kalıcı olması
 * gereken hiçbir veri buraya yazılmamalıdır (veritabanı harici olmalıdır).
 */
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
