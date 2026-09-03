<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sektor extends Model
{
    protected $fillable = [
        'nama_sektor',
        'luas_ha',
        'status',
        'latitude',
        'longitude',
        'radius'
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius' => 'float',
    ];

    protected static function booted()
    {
        static::created(function (Sektor $sektor) {
            RiwayatAktivitas::catat('Menambah Sektor', "Sektor baru '{$sektor->nama_sektor}' ditambahkan.");
        });

        static::updated(function (Sektor $sektor) {
            RiwayatAktivitas::catat('Mengubah Sektor', "Sektor '{$sektor->nama_sektor}' diperbarui.");
        });

        static::deleted(function (Sektor $sektor) {
            RiwayatAktivitas::catat('Menghapus Sektor', "Sektor '{$sektor->nama_sektor}' dihapus.");
        });
    }
}
