<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\SystemLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Eski sistem loglarında açık yazılmış hasta adlarını maskeler.
 *
 * Tek seferlik bir düzeltmedir ve geri alınamaz (eski ad hiçbir yerde
 * saklanmaz). Bu yüzden varsayılan olarak yalnızca önizleme yapar;
 * değişiklik ancak --uygula seçeneğiyle veritabanına yazılır.
 */
class MaskSystemLogNames extends Command
{
    protected $signature = 'loglar:ad-maskele {--uygula : Değişiklikleri veritabanına yaz (geri alınamaz)}';

    protected $description = 'Sistem loglarındaki hasta adlarını maskeler (varsayılan: yalnızca önizleme)';

    // "LP-XXXX barkodlu hastanın (Ayşe Yılmaz) tahlil sonucu eklendi."
    private const PATTERN = '/^(.*barkodlu hastanın \()(.+)(\) tahlil sonucu eklendi\.)$/us';

    public function handle(): int
    {
        $changes = [];

        SystemLog::where('description', 'like', '%barkodlu hastanın (%')
            ->orderBy('id')
            ->each(function (SystemLog $log) use (&$changes) {
                if (! preg_match(self::PATTERN, $log->description, $m)) {
                    return;
                }

                $masked = Patient::maskName($m[2]);

                // Zaten maskelenmiş kayıtlar atlanır; komut tekrar çalıştırılabilir
                if ($masked === $m[2] || self::isMasked($m[2])) {
                    return;
                }

                $changes[] = [
                    'id' => $log->id,
                    'before' => $log->description,
                    'after' => $m[1].$masked.$m[3],
                ];
            });

        if ($changes === []) {
            $this->info('Maskelenecek log kaydı yok.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Önce', 'Sonra'], array_map(fn ($c) => [$c['id'], $c['before'], $c['after']], $changes));

        if (! $this->option('uygula')) {
            $this->warn(count($changes).' kayıt değişecek. Bu yalnızca önizlemedir, veritabanına bir şey yazılmadı.');
            $this->line('Uygulamak için: php artisan loglar:ad-maskele --uygula (geri alınamaz)');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $change) {
                // updated_at'e dokunmuyoruz; logun ne zaman yazıldığı bilgisi korunur
                SystemLog::whereKey($change['id'])->toBase()->update(['description' => $change['after']]);
            }
        });

        $this->info(count($changes).' log kaydındaki hasta adı maskelendi.');

        return self::SUCCESS;
    }

    private static function isMasked(string $name): bool
    {
        return (bool) preg_match('/^(\S'.preg_quote(Patient::MASK, '/').')(\s\S'.preg_quote(Patient::MASK, '/').')*$/u', $name);
    }
}
