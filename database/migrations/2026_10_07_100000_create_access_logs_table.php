<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Özel nitelikli verilere (sağlık verisi, T.C. Kimlik Numarası) erişimin
     * kaydı. KVKK'nın veri minimizasyonu ilkesi gereği kimlik numarası veya
     * son 4 hanesi gibi değerler bu tabloya hiçbir zaman yazılmaz.
     */
    public function up(): void
    {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            // hasta_sorgu, giris, kayit_goruntuleme
            $table->string('event', 40);
            // basarili, basarisiz, kilitli
            $table->string('outcome', 20);
            // İşlemi yapan personel (herkese açık sorgularda boş)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Erişilen kayıt: barkod veya tahlil kaydı numarası
            $table->string('subject', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
