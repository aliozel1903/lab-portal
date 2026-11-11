<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    // Maskelenmiş adda her kelimenin ilk harfinden sonra gelen sabit işaret.
    // Sabit uzunlukta olduğu için adın kaç harf olduğu anlaşılmaz.
    public const MASK = '***';

    // Dışarıdan (API üzerinden) toplu veri atanmasına izin verdiğimiz sütunlar
    protected $fillable = [
        'identity_number',
        'full_name',
    ];

    // Kimlik numarası ve tam ad kişisel veridir; JSON çıktısında varsayılan
    // olarak yer almaz. Bunları gerektiren uç noktalar (düzenleme formu,
    // yazdırma belgesi, ad açma) makeVisible() ile açıkça görünür kılar.
    // Listeler yalnızca maskelenmiş adı (masked_name) görür.
    protected $hidden = [
        'identity_number',
        'full_name',
    ];

    protected $appends = [
        'masked_name',
    ];

    /**
     * Adı KVKK'ya uygun biçimde maskeler: "Ayşe Yılmaz" → "A*** Y***".
     *
     * Her kelimenin yalnızca ilk harfi görünür. Türkçe harfler (Ş, İ, Ç, Ü,
     * Ö, Ğ) bozulmasın diye çok baytlı (mb_) fonksiyonlar kullanılır.
     */
    public static function maskName(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);

        if (! $words) {
            return '-';
        }

        return implode(' ', array_map(
            // İlk harf kayıtlı olduğu gibi bırakılır; mb_strtoupper Türkçe "i"yi
            // "İ" yerine "I" yapacağı için büyük harfe çevirmiyoruz.
            fn (string $word) => mb_substr($word, 0, 1, 'UTF-8').self::MASK,
            $words,
        ));
    }

    protected function maskedName(): Attribute
    {
        return Attribute::get(fn () => self::maskName($this->full_name));
    }

    // Bir hastanın birden fazla tahlil sonucu olabilir (HasMany ilişkisi)
    public function testResults()
    {
        return $this->hasMany(TestResult::class);
    }
}
