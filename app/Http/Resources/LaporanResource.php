<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LaporanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fotoSelesai = $this->foto_selesai;
        if (is_array($fotoSelesai)) {
            $fotoSelesai = !empty($fotoSelesai) ? $fotoSelesai[0] : null;
        }

        $fotoSelesaiUrl = null;
        if ($fotoSelesai) {
            $fotoSelesaiUrl = str_starts_with($fotoSelesai, 'http://') || str_starts_with($fotoSelesai, 'https://')
                ? $fotoSelesai
                : asset('storage/' . ltrim($fotoSelesai, '/'));
        }

        return [
            'id_laporan' => $this->id_laporan,
            'id_pelapor' => $this->id_pelapor,
            'kode_laporan' => $this->kode_laporan,
            'jenis_kejadian' => $this->jenis_kejadian,
            'wilayah' => $this->wilayah,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'location_type' => $this->location_type,
            'radius' => $this->radius,
            'radius_unit' => $this->radius_unit,
            'area_type' => $this->area_type,
            'area_dimension_1' => $this->area_dimension_1,
            'area_dimension_2' => $this->area_dimension_2,
            'foto_bukti' => $this->foto_bukti,
            'keterangan_tambahan' => $this->keterangan_tambahan,
            'catatan_tindak_lanjut' => $this->catatan_tindak_lanjut,
            'tim_penanggung_jawab' => $this->tim_penanggung_jawab,
            'kendala' => $this->kendala,
            'status_penanganan' => $this->status_penanganan,
            'waktu_lapor' => $this->waktu_lapor ? ($this->waktu_lapor instanceof \Carbon\CarbonInterface ? $this->waktu_lapor->toISOString() : $this->waktu_lapor) : null,
            'tgl_selesai' => $this->tgl_selesai ? ($this->tgl_selesai instanceof \Carbon\CarbonInterface ? $this->tgl_selesai->toISOString() : $this->tgl_selesai) : null,
            'durasi_penanganan' => $this->durasi_penanganan,
            'alat_digunakan' => $this->alat_digunakan,
            'catatan_selesai' => $this->catatan_selesai,
            'foto_selesai' => $fotoSelesai,
            'foto_selesai_url' => $fotoSelesaiUrl,
            'created_at' => $this->created_at ? ($this->created_at instanceof \Carbon\CarbonInterface ? $this->created_at->toISOString() : $this->created_at) : null,
            'updated_at' => $this->updated_at ? ($this->updated_at instanceof \Carbon\CarbonInterface ? $this->updated_at->toISOString() : $this->updated_at) : null,
            'pelapor' => $this->whenLoaded('pelapor'),
        ];
    }
}
