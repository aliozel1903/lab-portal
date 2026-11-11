<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    use HasFactory;

    // İşlem tipleri (log ekranındaki etiketler bunlara göre renklenir)
    public const ACTION_CREATE = 'Ekleme';

    public const ACTION_UPDATE = 'Güncelleme';

    public const ACTION_DELETE = 'Silme';

    public const ACTION_RESTORE = 'Geri Yükleme';

    public const ACTION_VIEW_NAME = 'Görüntüleme';

    public const ACTION_PRINT = 'Yazdırma';

    // Toplu veri atamasına izin verilen sütunlar
    protected $fillable = ['user_id', 'action', 'description'];

    // Bir log kaydı, bir kullanıcıya aittir
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
