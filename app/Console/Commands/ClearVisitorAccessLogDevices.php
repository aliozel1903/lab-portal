<?php

namespace App\Console\Commands;

use App\Models\AccessLog;
use Illuminate\Console\Command;

/**
 * Eski erişim loglarındaki ziyaretçi IP adreslerini ve tarayıcı bilgilerini siler.
 *
 * Artık yalnızca personel hesabıyla ilgili kayıtlarda IP tutuluyor. Bu komut,
 * o kuraldan önce yazılmış ziyaretçi kayıtlarını (hasta sorguları ve var
 * olmayan hesaplara giriş denemeleri) aynı kurala getirir. Kayıtların kendisi
 * (olay, sonuç, barkod, zaman) korunur. Geri alınamaz; bu yüzden varsayılan
 * olarak yalnızca önizleme yapar, değişiklik --uygula ile yazılır.
 */
class ClearVisitorAccessLogDevices extends Command
{
    protected $signature = 'erisim-loglari:ip-temizle {--uygula : Değişiklikleri veritabanına yaz (geri alınamaz)}';

    protected $description = 'Ziyaretçi erişim loglarındaki IP ve tarayıcı bilgisini siler (varsayılan: yalnızca önizleme)';

    public function handle(): int
    {
        $query = AccessLog::query()
            ->whereNull('user_id')
            ->where(fn ($q) => $q->whereNotNull('ip_address')->orWhereNotNull('user_agent'));

        $counts = (clone $query)->selectRaw('event, outcome, count(*) as adet')
            ->groupBy('event', 'outcome')->orderBy('event')->orderBy('outcome')->get();

        if ($counts->isEmpty()) {
            $this->info('IP ya da tarayıcı bilgisi silinecek ziyaretçi kaydı yok.');

            return self::SUCCESS;
        }

        $this->table(['Olay', 'Sonuç', 'Kayıt sayısı'], $counts->map(fn ($r) => [$r->event, $r->outcome, $r->adet])->all());

        $total = $counts->sum('adet');

        if (! $this->option('uygula')) {
            $this->warn("{$total} kayıttaki IP ve tarayıcı bilgisi silinecek. Bu yalnızca önizlemedir, veritabanına bir şey yazılmadı.");
            $this->line('Uygulamak için: php artisan erisim-loglari:ip-temizle --uygula (geri alınamaz)');

            return self::SUCCESS;
        }

        $updated = $query->toBase()->update(['ip_address' => null, 'user_agent' => null]);

        $this->info("{$updated} ziyaretçi kaydındaki IP ve tarayıcı bilgisi silindi.");

        return self::SUCCESS;
    }
}
