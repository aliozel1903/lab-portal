<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Başlangıç kullanıcılarını oluşturur.
     *
     * Şifreler .env dosyasındaki SEED_ADMIN_PASSWORD / SEED_LAB_PASSWORD
     * değerlerinden okunur; böylece depoda sabit bir şifre tutulmaz.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@lab.test')],
            [
                'name' => 'Sistem Yöneticisi',
                'role' => 'admin',
                'password' => Hash::make(env('SEED_ADMIN_PASSWORD', 'degistir-beni')),
            ]
        );

        User::updateOrCreate(
            ['email' => env('SEED_LAB_EMAIL', 'laborant@lab.test')],
            [
                'name' => 'Laborant',
                'role' => 'laborant',
                'password' => Hash::make(env('SEED_LAB_PASSWORD', 'degistir-beni')),
            ]
        );
    }
}
