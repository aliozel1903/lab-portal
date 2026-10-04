<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * T.C. Kimlik Numarası doğrulama kuralı.
 *
 * Kontrol edilen kurallar:
 *  1. Yalnızca rakamlardan oluşur (harf, boşluk, işaret kabul edilmez).
 *  2. Tam olarak 11 hanedir.
 *  3. İlk hane 0 olamaz.
 *  4. 10. hane: (1,3,5,7,9. hanelerin toplamı * 7 - 2,4,6,8. hanelerin toplamı) mod 10
 *  5. 11. hane: ilk 10 hanenin toplamı mod 10
 *
 * 5. kuralın doğal sonucu olarak geçerli bir TCKN her zaman çift rakamla biter:
 * ilk 10 hanenin toplamı, 4. kural yerine konduğunda 8 × (tek sıradaki
 * hanelerin toplamı) ile aynı kalana sahiptir ve bu her zaman çifttir.
 *
 * Demo modunda 5. kural tersine çevrilir: 11. hane, doğru değerin bir fazlası
 * (yani tek bir rakam) olmalıdır. Böylece demo sistemine girilen hiçbir numara
 * gerçek bir kişiye ait olamaz, ama 1-4. kurallar yine eksiksiz uygulanır.
 */
class TurkishIdentityNumber implements ValidationRule
{
    /**
     * Kural boş değerlerde de çalışsın. Aksi halde Laravel boş girdilerde
     * kuralı atlar ve alan 'required' ile birlikte kullanılmazsa boş geçer.
     */
    public bool $implicit = true;

    private bool $demoMode;

    public function __construct(?bool $demoMode = null)
    {
        $this->demoMode = $demoMode ?? (bool) config('app.demo_mode');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $number = is_string($value) || is_int($value) ? trim((string) $value) : '';

        if ($number === '') {
            $fail('T.C. Kimlik Numarası zorunludur.');

            return;
        }

        if (preg_match('/^[0-9]+$/', $number) !== 1) {
            $fail('T.C. Kimlik Numarası yalnızca rakamlardan oluşmalıdır.');

            return;
        }

        if (strlen($number) !== 11) {
            $fail('T.C. Kimlik Numarası 11 haneli olmalıdır.');

            return;
        }

        $digits = array_map('intval', str_split($number));

        if ($digits[0] === 0) {
            $fail('T.C. Kimlik Numarası 0 ile başlayamaz.');

            return;
        }

        $oddSum = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $evenSum = $digits[1] + $digits[3] + $digits[5] + $digits[7];

        // Fark negatif olabileceği için PHP'nin negatif mod sonucuna karşı normalize ediyoruz
        $checkDigit = (((($oddSum * 7) - $evenSum) % 10) + 10) % 10;

        if ($checkDigit !== $digits[9]) {
            $fail('Geçersiz bir T.C. Kimlik Numarası girdiniz.');

            return;
        }

        // Gerçek bir numarada 11. hanenin olması gereken değer (her zaman çift)
        $realLastDigit = array_sum(array_slice($digits, 0, 10)) % 10;

        if (! $this->demoMode) {
            if ($digits[10] !== $realLastDigit) {
                $fail('Geçersiz bir T.C. Kimlik Numarası girdiniz.');
            }

            return;
        }

        if ($digits[10] === $realLastDigit) {
            $fail('Demo sisteminde gerçek kişilere ait olabilecek T.C. Kimlik Numarası kullanılamaz. Test numarası için son haneyi bir artırın.');

            return;
        }

        if ($digits[10] !== $realLastDigit + 1) {
            $fail('Geçersiz bir test T.C. Kimlik Numarası girdiniz.');
        }
    }
}
