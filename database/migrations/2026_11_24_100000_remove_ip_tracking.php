<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IP adresi ve tarayıcı bilgisi artık hiçbir yerde saklanmıyor (veri minimizasyonu).
 *
 * - Erişim loglarındaki ip_address ve user_agent sütunları, içlerindeki eski
 *   verilerle birlikte kaldırılır.
 * - Uygulama PHP oturumu kullanmadığı halde Laravel her ziyaretçi için
 *   sessions tablosuna IP ve tarayıcı bilgisiyle bir satır yazıyordu; oturum
 *   artık başlatılmadığı için tablodaki eski satırlar da silinir.
 *
 * Geri alınamaz: down() sütunları boş olarak geri ekler, silinen veriler dönmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->delete();
        }
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
        });
    }
};
