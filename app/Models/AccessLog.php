<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Http\Request;

/**
 * Özel nitelikli verilere erişim kaydı (kim, ne zaman, nereden, neye).
 *
 * Kayıtlar 1 yıl saklanır. Sunucusuz ortamda zamanlanmış görev çalışmadığı
 * için eski kayıtlar, yeni kayıt yazılırken küçük bir olasılıkla temizlenir;
 * ayrıca `php artisan model:prune` ile elle de temizlenebilir.
 */
class AccessLog extends Model
{
    use Prunable;

    public const EVENT_PATIENT_LOOKUP = 'hasta_sorgu';

    public const EVENT_LOGIN = 'giris';

    public const EVENT_RECORD_VIEW = 'kayit_goruntuleme';

    public const OUTCOME_SUCCESS = 'basarili';

    public const OUTCOME_FAILURE = 'basarisiz';

    public const OUTCOME_LOCKED = 'kilitli';

    public const RETENTION_DAYS = 365;

    // Her 100 kayıttan birinde süresi dolmuş kayıtlar silinir
    private const PRUNE_LOTTERY = 100;

    public const UPDATED_AT = null;

    protected $fillable = ['event', 'outcome', 'user_id', 'subject', 'ip_address', 'user_agent'];

    public static function record(Request $request, string $event, string $outcome, ?int $userId = null, ?string $subject = null): void
    {
        static::create([
            'event' => $event,
            'outcome' => $outcome,
            'user_id' => $userId,
            'subject' => $subject !== null ? mb_substr($subject, 0, 100) : null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
        ]);

        if (random_int(1, self::PRUNE_LOTTERY) === 1) {
            (new static)->prunable()->delete();
        }
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
