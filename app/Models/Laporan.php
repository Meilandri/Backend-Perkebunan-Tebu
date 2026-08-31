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
        'jenis_kejadian',
        'latitude',
        'longitude',
        'foto_bukti',
        'keterangan_tambahan',
        'status_penanganan',
        'waktu_lapor',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'waktu_lapor' => 'datetime',
    ];

    /**
     * Relasi ke pelapor (User)
     */
    public function pelapor()
    {
        return $this->belongsTo(User::class, 'id_pelapor', 'id');
    }
}
