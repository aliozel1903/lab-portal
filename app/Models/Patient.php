<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    // Dışarıdan (API üzerinden) toplu veri atanmasına izin verdiğimiz sütunlar
    protected $fillable = [
        'identity_number',
        'full_name',
    ];

    // Kimlik numarası hassas veridir; JSON çıktısında varsayılan olarak yer almaz.
    // Yalnızca resmî belge uç noktası makeVisible() ile açıkça görünür kılar.
    protected $hidden = [
        'identity_number',
    ];

    // Bir hastanın birden fazla tahlil sonucu olabilir (HasMany ilişkisi)
    public function testResults()
    {
        return $this->hasMany(TestResult::class);
    }
}
