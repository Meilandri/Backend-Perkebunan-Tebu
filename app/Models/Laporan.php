<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Laporan extends Model
{
    use HasFactory;

    protected $table = 'laporans';
    protected $primaryKey = 'id_laporan';

    protected $fillable = [
        'id_pelapor',
        'kode_laporan',
        'jenis_kejadian',
        'wilayah',
        'latitude',
        'longitude',
        'location_type',
        'radius',
        'radius_unit',
        'area_type',
        'area_dimension_1',
        'area_dimension_2',
        'foto_bukti',
        'keterangan_tambahan',
        'catatan_tindak_lanjut',
        'tim_penanggung_jawab',
        'kendala',
        'status_penanganan',
        'waktu_lapor',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius' => 'float',
        'area_dimension_1' => 'float',
        'area_dimension_2' => 'float',
        'waktu_lapor' => 'datetime',
    ];

    /**
     * Nomor laporan yang dilihat pengguna: berbasis waktu pembuatan
     * (detik-menit-jam-hari-bulan-tahun), bukan id database mentah.
     * Dibuat di event `created` (bukan `creating`) supaya bisa
     * menyertakan `id_laporan` yang baru saja ter-generate sebagai
     * jaminan keunikan kalau 2 laporan masuk di detik yang sama persis.
     */
    protected static function booted()
    {
        static::created(function (Laporan $laporan) {
            if ($laporan->kode_laporan) {
                return;
            }
            $waktu = $laporan->waktu_lapor ?: now();
            $kode = $waktu->format('s-i-H-d-m-y') . '-' . str_pad($laporan->id_laporan, 3, '0', STR_PAD_LEFT);
            $laporan->kode_laporan = $kode;
            $laporan->saveQuietly();
        });
    }

    /**
     * Relasi ke pelapor (User)
     */
    public function pelapor()
    {
        return $this->belongsTo(User::class, 'id_pelapor', 'id');
    }
}
