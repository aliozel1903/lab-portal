<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const MIN_PASSWORD_LENGTH = 12;

    /**
     * Başlangıç kullanıcılarını oluşturur.
     *
     * Şifreler .env dosyasındaki SEED_ADMIN_PASSWORD / SEED_LAB_PASSWORD
     * değerlerinden okunur. Kodda varsayılan şifre yoktur: değer eksik ya da
     * kısaysa seeder durur. Aksi halde herkese açık depoda yazan bir şifreyle
     * yönetici hesabı oluşturulabilirdi.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@lab.test')],
            [
                'name' => 'Sistem Yöneticisi',
                'role' => 'admin',
                'password' => Hash::make($this->requirePassword('SEED_ADMIN_PASSWORD')),
            ]
        );

        User::updateOrCreate(
            ['email' => env('SEED_LAB_EMAIL', 'laborant@lab.test')],
            [
                'name' => 'Laborant',
                'role' => 'laborant',
                'password' => Hash::make($this->requirePassword('SEED_LAB_PASSWORD')),
            ]
        );
    }

    private function requirePassword(string $key): string
    {
        $password = (string) env($key, '');

        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new RuntimeException(
                "{$key} tanımlı değil ya da ".self::MIN_PASSWORD_LENGTH.' karakterden kısa. '
                .'Başlangıç kullanıcıları oluşturulmadı; .env dosyasına güçlü bir şifre girip tekrar deneyin.'
            );
        }

        return $password;
    }
}
