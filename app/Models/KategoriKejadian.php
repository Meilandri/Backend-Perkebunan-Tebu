<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KategoriKejadian extends Model
{
    protected $fillable = [
        'nama_kategori',
        'icon'
    ];

    protected static function booted()
    {
        static::created(function (KategoriKejadian $kategori) {
            RiwayatAktivitas::catat('Menambah Kategori', "Kategori baru '{$kategori->nama_kategori}' ditambahkan.");
        });

        static::updated(function (KategoriKejadian $kategori) {
            RiwayatAktivitas::catat('Mengubah Kategori', "Kategori '{$kategori->nama_kategori}' diperbarui.");
        });

        static::deleted(function (KategoriKejadian $kategori) {
            RiwayatAktivitas::catat('Menghapus Kategori', "Kategori '{$kategori->nama_kategori}' dihapus.");
        });
    }
}
