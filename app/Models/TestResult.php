<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Bunu ekledik

class TestResult extends Model
{
    use HasFactory, SoftDeletes; // Buraya da SoftDeletes ekledik

    protected $fillable = [
        'patient_id',
        'barcode_number',
        'test_name',
        'result_details',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
