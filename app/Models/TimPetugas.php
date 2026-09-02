<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimPetugas extends Model
{
    protected $table = 'tim_petugas';

    protected $fillable = [
        'nama_tim',
        'nama_ketua',
        'nomor_wa',
        'spesialisasi',
    ];
}
