<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    use HasFactory;

    // Toplu veri atamasına izin verilen sütunlar
    protected $fillable = ['user_id', 'action', 'description'];

    // Bir log kaydı, bir kullanıcıya aittir
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
