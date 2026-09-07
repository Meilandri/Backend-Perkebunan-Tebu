<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Laporan;
use App\Http\Resources\LaporanResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LaporanController extends Controller
{
    /**
     * Mengambil daftar laporan dengan filter (Status, Jenis Kejadian, Rentang Waktu) & Pagination
     */
    public function index(Request $request)
    {
        $query = Laporan::with('pelapor');

        // Global Search
        if ($request->has('keyword') && !empty($request->keyword)) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('id_laporan', 'like', "%{$keyword}%")
                  ->orWhere('wilayah', 'like', "%{$keyword}%")
                  ->orWhere('keterangan_tambahan', 'like', "%{$keyword}%")
                  ->orWhere('jenis_kejadian', 'like', "%{$keyword}%");
            });
        }

        // Filter Jenis Kejadian
        if ($request->has('jenis_kejadian') && !empty($request->jenis_kejadian)) {
            $jenis = is_array($request->jenis_kejadian) ? $request->jenis_kejadian : explode(',', $request->jenis_kejadian);
            $query->whereIn('jenis_kejadian', $jenis);
        }

        // Filter Status Penanganan
        if ($request->has('status_penanganan') && !empty($request->status_penanganan)) {
            $status = is_array($request->status_penanganan) ? $request->status_penanganan : explode(',', $request->status_penanganan);
            $query->whereIn('status_penanganan', $status);
        }

        // Filter Periode Waktu (Hari ini, Minggu ini, Bulan ini, Tahun ini, atau Custom Date)
        if ($request->has('periode')) {
            switch ($request->periode) {
                case 'today':
                    $query->whereDate('waktu_lapor', now()->toDateString());
                    break;
                case 'this_week':
                    $query->whereBetween('waktu_lapor', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereMonth('waktu_lapor', now()->month)->whereYear('waktu_lapor', now()->year);
                    break;
                case 'this_year':
                    $query->whereYear('waktu_lapor', now()->year);
                    break;
            }
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('waktu_lapor', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        }

        // Filter berdasarkan pelapor -- dipakai halaman "Riwayat Laporan"
        // milik petani supaya hanya menampilkan laporan milik akun yang
        // sedang login, bukan seluruh laporan di server.
        if ($request->has('id_pelapor') && !empty($request->id_pelapor)) {
            $query->where('id_pelapor', $request->id_pelapor);
        }

        $laporans = $query->orderBy('waktu_lapor', 'desc')->paginate($request->get('per_page', 15));

        $laporans->through(function ($laporan) {
            return new LaporanResource($laporan);
        });

        return response()->json($laporans);
    }

    /**
     * Endpoint Peta Interaktif menggunakan metode Bounding Box (BBox / Viewport)
     * Sesuai Spesifikasi PDF Halaman 12 (Optimasi Pemuatan Data Peta)
     */
    public function mapData(Request $request)
    {
        $request->validate([
            'min_lat' => 'nullable|numeric',
            'max_lat' => 'nullable|numeric',
            'min_lng' => 'nullable|numeric',
            'max_lng' => 'nullable|numeric',
        ]);

        $query = Laporan::select(
            'id_laporan', 'kode_laporan', 'jenis_kejadian', 'wilayah', 'latitude', 'longitude',
            'location_type', 'radius', 'radius_unit', 'area_type', 'area_dimension_1', 'area_dimension_2',
            'status_penanganan', 'waktu_lapor', 'foto_bukti', 'keterangan_tambahan',
            'tim_penanggung_jawab', 'kendala', 'catatan_selesai', 'durasi_penanganan', 'alat_digunakan', 'tgl_selesai', 'foto_selesai'
        );

        // Bounding Box Filter jika parameter viewport diberikan oleh Leaflet/React
        if ($request->filled(['min_lat', 'max_lat', 'min_lng', 'max_lng'])) {
            $query->whereBetween('latitude', [$request->min_lat, $request->max_lat])
                  ->whereBetween('longitude', [$request->min_lng, $request->max_lng]);
        }

        // Apply Status Filter
        if ($request->has('status_penanganan') && !empty($request->status_penanganan)) {
            $status = is_array($request->status_penanganan) ? $request->status_penanganan : explode(',', $request->status_penanganan);
            $query->whereIn('status_penanganan', $status);
        }

        // Apply Jenis Kejadian Filter
        if ($request->has('jenis_kejadian') && !empty($request->jenis_kejadian)) {
            $jenis = is_array($request->jenis_kejadian) ? $request->jenis_kejadian : explode(',', $request->jenis_kejadian);
            $query->whereIn('jenis_kejadian', $jenis);
        }

        $pins = $query->get();

        return response()->json([
            'total' => $pins->count(),
            'data' => $pins
        ]);
    }

    /**
     * Menyimpan Laporan Baru dari Petani / Petugas Lapangan
     */
    public function store(Request $request)
    {
        $request->validate([
            'jenis_kejadian' => ['required', 'string', 'max:255'],
            'wilayah' => 'nullable|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'location_type' => 'nullable|in:titik,radius,area',
            'radius' => 'nullable|numeric|min:0',
            'radius_unit' => 'nullable|in:m,km',
            'area_type' => 'nullable|in:persegi,lingkaran',
            'area_dimension_1' => 'nullable|numeric|min:0',
            'area_dimension_2' => 'nullable|numeric|min:0',
            'keterangan_tambahan' => 'nullable|string',
            'waktu_lapor' => 'nullable|date',
        ]);

        $fotoUrls = [];
        if ($request->hasFile('foto_bukti')) {
            $files = is_array($request->file('foto_bukti')) ? $request->file('foto_bukti') : [$request->file('foto_bukti')];
            foreach ($files as $file) {
                $path = $file->store('laporans', 'public');
                $fotoUrls[] = asset('storage/' . $path);
            }
        } elseif ($request->hasFile('foto')) { // fallback just in case
            $files = is_array($request->file('foto')) ? $request->file('foto') : [$request->file('foto')];
            foreach ($files as $file) {
                $path = $file->store('laporans', 'public');
                $fotoUrls[] = asset('storage/' . $path);
            }
        }

        // SEBELUMNYA di sini pakai $request->user() (guard default) --
        // tapi route /laporan ini sengaja PUBLIK (tidak ada middleware
        // auth:sanctum), jadi guard default tidak pernah membaca token
        // Bearer yang dikirim FE. Akibatnya $user selalu null dan
        // id_pelapor selalu tersimpan null, walau petani/petugas yang
        // submit sebenarnya sedang login dengan token valid. Perbaikannya
        // sama seperti pola yang sudah dipakai di summaryMetrics() di
        // bawah: pakai guard 'sanctum' secara eksplisit, yang tetap bisa
        // membaca Bearer token di route publik sekalipun.
        $user = $request->user('sanctum');

        $laporan = Laporan::create([
            'id_pelapor' => $user ? $user->id : null,
            'jenis_kejadian' => $request->jenis_kejadian,
            'wilayah' => $request->wilayah,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'location_type' => $request->location_type ?? 'titik',
            'radius' => $request->location_type === 'radius' ? $request->radius : null,
            'radius_unit' => $request->location_type === 'radius' ? ($request->radius_unit ?? 'm') : null,
            'area_type' => $request->location_type === 'area' ? $request->area_type : null,
            'area_dimension_1' => $request->location_type === 'area' ? $request->area_dimension_1 : null,
            'area_dimension_2' => $request->location_type === 'area' ? $request->area_dimension_2 : null,
            'foto_bukti' => $fotoUrls,
            'keterangan_tambahan' => $request->keterangan_tambahan,
            'status_penanganan' => 'Open',
            'waktu_lapor' => $request->waktu_lapor ?? now(),
        ]);

        // Tambah counter historis -- lihat komentar di migration
        // laporan_counters. Counter ini SENGAJA terpisah dari jumlah baris
        // aktual di tabel `laporans`, supaya "Semua Insiden Hingga Saat
        // Ini" di dashboard tetap akurat secara historis walau ada laporan
        // yang dihapus/diarsipkan nanti.
        DB::table('laporan_counters')->where('id', 1)->increment('total_count');

        // Clear Cache Dashboard saat ada laporan baru
        Cache::forget('dashboard_summary_metrics');
        // SEBELUMNYA cache PER-USER (dashboard_summary_metrics_user_{id})
        // tidak pernah di-forget di sini -- cuma cache GLOBAL yang
        // dibersihkan. Akibatnya summary milik petani/petugas yang
        // login (dashboard mereka sendiri) tetap menampilkan angka lama
        // sampai TTL 300 detik habis, walau laporan baru sudah masuk.
        if ($laporan->id_pelapor) {
            Cache::forget("dashboard_summary_metrics_user_{$laporan->id_pelapor}");
        }

        return response()->json([
            'message' => 'Laporan berhasil dikirim!',
            'data' => $laporan
        ], 201);
    }

    /**
     * Detail Laporan
     */
    public function show($id)
    {
        $laporan = Laporan::with('pelapor')->findOrFail($id);
        return new LaporanResource($laporan);
    }

    /**
     * Mengubah Status Penanganan Laporan (Open -> On-Progress -> Closed)
     * Khusus Manajemen / Petugas Tindak Lanjut
     */
    public function updateStatus(Request $request, $id)
    {
        if ($request->user() && $request->user()->peran_user !== 'Manajemen') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status_penanganan' => ['required', Rule::in(['Open', 'On-Progress', 'Closed'])],
            'catatan_tindak_lanjut' => 'nullable|string',
            'tim_penanggung_jawab' => 'nullable|string|max:255',
            'kendala' => 'nullable|string|max:255',
            'catatan_selesai' => 'nullable|string',
            'durasi_penanganan' => 'nullable|string|max:255',
            'alat_digunakan' => 'nullable|string',
            'tgl_selesai' => 'nullable',
            'foto_selesai' => 'nullable',
        ]);

        $laporan = Laporan::findOrFail($id);
        $laporan->status_penanganan = $request->status_penanganan;

        if ($request->has('catatan_tindak_lanjut')) {
            $laporan->catatan_tindak_lanjut = $request->catatan_tindak_lanjut;
        }
        if ($request->has('tim_penanggung_jawab')) {
            $laporan->tim_penanggung_jawab = $request->tim_penanggung_jawab;
        }
        if ($request->has('kendala')) {
            $laporan->kendala = $request->kendala;
        }
        if ($request->has('catatan_selesai')) {
            $laporan->catatan_selesai = $request->catatan_selesai;
        }
        if ($request->has('durasi_penanganan')) {
            $laporan->durasi_penanganan = $request->durasi_penanganan;
        }
        if ($request->has('alat_digunakan')) {
            $laporan->alat_digunakan = $request->alat_digunakan;
        }

        if ($request->has('tgl_selesai') && !empty($request->tgl_selesai)) {
            $laporan->tgl_selesai = $request->tgl_selesai;
        } elseif ($request->status_penanganan === 'Closed' && !$laporan->tgl_selesai) {
            $laporan->tgl_selesai = now();
        }

        // Handle foto_selesai (Base64 Data URL, uploaded file, or relative path string)
        if ($request->hasFile('foto_selesai')) {
            $file = $request->file('foto_selesai');
            if (is_array($file)) {
                $file = $file[0];
            }
            $path = $file->store('foto_selesai', 'public');
            $laporan->foto_selesai = $path;
        } elseif ($request->has('foto_selesai')) {
            $fotoSelesai = $request->foto_selesai;
            if (is_string($fotoSelesai) && preg_match('/^data:image\/([a-zA-Z0-9\+\-]+).*?;base64,(.+)$/s', $fotoSelesai, $matches)) {
                $extension = strtolower(explode('+', $matches[1])[0]);
                if ($extension === 'jpeg') {
                    $extension = 'jpg';
                }
                $imageData = base64_decode(trim($matches[2]));
                if ($imageData !== false) {
                    $fileName = 'foto_selesai_' . time() . '_' . uniqid() . '.' . $extension;
                    $relativePath = 'foto_selesai/' . $fileName;
                    Storage::disk('public')->put($relativePath, $imageData);
                    $laporan->foto_selesai = $relativePath;
                }
            } elseif (is_string($fotoSelesai)) {
                $laporan->foto_selesai = $fotoSelesai;
            } elseif (is_null($fotoSelesai)) {
                $laporan->foto_selesai = null;
            }
        }

        $laporan->save();

        Cache::forget('dashboard_summary_metrics');
        // Sama seperti di store() -- cache per-user milik pelapor laporan
        // ini juga harus di-forget, supaya begitu status berubah (mis.
        // ditutup oleh Manajemen), summary yang dilihat pelapornya sendiri
        // langsung update juga.
        if ($laporan->id_pelapor) {
            Cache::forget("dashboard_summary_metrics_user_{$laporan->id_pelapor}");
        }

        return response()->json([
            'message' => 'Status laporan berhasil diperbarui',
            'data' => new LaporanResource($laporan->fresh(['pelapor']))
        ]);
    }

    /**
     * Menyelesaikan laporan secara khusus dengan detail penanganan
     */
    public function selesai(Request $request, $id)
    {
        $laporan = Laporan::findOrFail($id);

        $request->validate([
            'tgl_selesai' => 'nullable',
            'durasi_penanganan' => 'nullable|string',
            'alat_digunakan' => 'nullable|string',
            'catatan_selesai' => 'nullable|string',
            'foto_selesai' => 'nullable',
        ]);

        if ($request->has('catatan_selesai')) {
            $laporan->catatan_selesai = $request->catatan_selesai;
        }
        if ($request->has('durasi_penanganan')) {
            $laporan->durasi_penanganan = $request->durasi_penanganan;
        }
        if ($request->has('alat_digunakan')) {
            $laporan->alat_digunakan = $request->alat_digunakan;
        }

        $laporan->status_penanganan = 'Closed';
        $laporan->tgl_selesai = ($request->has('tgl_selesai') && !empty($request->tgl_selesai))
            ? $request->tgl_selesai
            : ($laporan->tgl_selesai ?: now());

        if ($request->hasFile('foto_selesai')) {
            $file = $request->file('foto_selesai');
            if (is_array($file)) {
                $file = $file[0];
            }
            $path = $file->store('foto_selesai', 'public');
            $laporan->foto_selesai = $path;
        } elseif ($request->has('foto_selesai')) {
            $fotoSelesai = $request->foto_selesai;
            if (is_string($fotoSelesai) && preg_match('/^data:image\/([a-zA-Z0-9\+\-]+).*?;base64,(.+)$/s', $fotoSelesai, $matches)) {
                $extension = strtolower(explode('+', $matches[1])[0]);
                if ($extension === 'jpeg') {
                    $extension = 'jpg';
                }
                $imageData = base64_decode(trim($matches[2]));
                if ($imageData !== false) {
                    $fileName = 'foto_selesai_' . time() . '_' . uniqid() . '.' . $extension;
                    $relativePath = 'foto_selesai/' . $fileName;
                    Storage::disk('public')->put($relativePath, $imageData);
                    $laporan->foto_selesai = $relativePath;
                }
            } elseif (is_string($fotoSelesai)) {
                $laporan->foto_selesai = $fotoSelesai;
            } elseif (is_null($fotoSelesai)) {
                $laporan->foto_selesai = null;
            }
        }

        $laporan->save();

        Cache::forget('dashboard_summary_metrics');
        // Sama seperti di store()/updateStatus() -- cache per-user milik
        // pelapor laporan ini juga harus di-forget.
        if ($laporan->id_pelapor) {
            Cache::forget("dashboard_summary_metrics_user_{$laporan->id_pelapor}");
        }

        return response()->json([
            'message' => 'Laporan berhasil diselesaikan',
            'data' => new LaporanResource($laporan->fresh(['pelapor']))
        ]);
    }

    /**
     * Hapus SELURUH laporan sekaligus. Endpoint ini sengaja tidak menerima
     * parameter apapun (selalu menghapus semua baris) -- kontrol keamanan
     * utamanya ada di frontend (wajib export Excel/PDF dulu sebelum tombol
     * konfirmasi aktif) dan di middleware role:Manajemen pada route-nya.
     */
    public function destroyAll(Request $request)
    {
        // Kumpulkan dulu id_pelapor mana saja yang punya laporan, SEBELUM
        // baris-barisnya dihapus -- dipakai di bawah untuk membersihkan
        // cache summary per-user mereka juga (bukan cuma cache global).
        $affectedPelaporIds = Laporan::whereNotNull('id_pelapor')->distinct()->pluck('id_pelapor');

        // Hapus juga file foto bukti yang tersimpan di storage supaya
        // tidak jadi sampah orphan setelah baris databasenya hilang.
        Laporan::whereNotNull('foto_bukti')->chunkById(200, function ($chunk) {
            foreach ($chunk as $laporan) {
                $path = str_replace(asset('storage') . '/', '', $laporan->foto_bukti);
                if ($path && $path !== $laporan->foto_bukti) {
                    Storage::disk('public')->delete($path);
                }
            }
        });

        // Hapus juga file foto bukti penyelesaian
        Laporan::whereNotNull('foto_selesai')->chunkById(200, function ($chunk) {
            foreach ($chunk as $laporan) {
                if ($laporan->foto_selesai && Storage::disk('public')->exists($laporan->foto_selesai)) {
                    Storage::disk('public')->delete($laporan->foto_selesai);
                }
            }
        });

        $total = Laporan::count();
        Laporan::query()->delete();

        // PENTING: `laporan_counters` SENGAJA tidak disentuh di sini.
        // "Semua Insiden Hingga Saat Ini" di Ringkasan Operasional adalah
        // angka historis kumulatif, bukan jumlah baris yang sedang ada --
        // jadi harus tetap sama walau semua laporan aktif dihapus.
        Cache::forget('dashboard_summary_metrics');
        foreach ($affectedPelaporIds as $uid) {
            Cache::forget("dashboard_summary_metrics_user_{$uid}");
        }

        return response()->json([
            'message' => "Berhasil menghapus {$total} laporan.",
            'deleted' => $total,
        ]);
    }

    /**
     * Metrik Ringkasan untuk Dashboard (Menggunakan Caching - Spesifikasi PDF Hal 12)
     */
    public function summaryMetrics(Request $request)
    {
        $idPelapor = $request->query('id_pelapor');
        $user = $request->user('sanctum'); // Gunakan guard sanctum agar tidak throw error jika unauthenticated di route public

        // Jika id_pelapor tidak dikirim secara eksplisit, tapi user terautentikasi dan bukan Manajemen
        if (!$idPelapor && $user && in_array($user->peran_user, ['Petani', 'Petugas Lapangan'])) {
            $idPelapor = $user->id;
        }

        if ($idPelapor) {
            // Metrics spesifik pengguna, tidak perlu global cache atau gunakan cache key unik per user
            $metrics = Cache::remember("dashboard_summary_metrics_user_{$idPelapor}", 300, function () use ($idPelapor) {
                $baseQuery = Laporan::where('id_pelapor', $idPelapor);
                
                return [
                    'total_laporan' => (clone $baseQuery)->count(),
                    'open' => (clone $baseQuery)->where('status_penanganan', 'Open')->count(),
                    'on_progress' => (clone $baseQuery)->where('status_penanganan', 'On-Progress')->count(),
                    'closed' => (clone $baseQuery)->where('status_penanganan', 'Closed')->count(),
                    'by_jenis' => [
                        'kebakaran' => (clone $baseQuery)->where('jenis_kejadian', 'Kebakaran tebu')->count(),
                        'hama' => (clone $baseQuery)->where('jenis_kejadian', 'Serangan hama')->count(),
                        'penyakit' => (clone $baseQuery)->where('jenis_kejadian', 'Penyakit tanaman')->count(),
                        'banjir' => (clone $baseQuery)->where('jenis_kejadian', 'Banjir/genangan')->count(),
                        'lainnya' => (clone $baseQuery)->where('jenis_kejadian', 'Kendala lainnya')->count(),
                    ]
                ];
            });

            return response()->json($metrics);
        }

        // Global metrics
        $metrics = Cache::remember('dashboard_summary_metrics', 300, function () {
            return [
                'total_laporan' => DB::table('laporan_counters')->value('total_count') ?? Laporan::count(),
                'open' => Laporan::where('status_penanganan', 'Open')->count(),
                'on_progress' => Laporan::where('status_penanganan', 'On-Progress')->count(),
                'closed' => Laporan::where('status_penanganan', 'Closed')->count(),
                'by_jenis' => [
                    'kebakaran' => Laporan::where('jenis_kejadian', 'Kebakaran tebu')->count(),
                    'hama' => Laporan::where('jenis_kejadian', 'Serangan hama')->count(),
                    'penyakit' => Laporan::where('jenis_kejadian', 'Penyakit tanaman')->count(),
                    'banjir' => Laporan::where('jenis_kejadian', 'Banjir/genangan')->count(),
                    'lainnya' => Laporan::where('jenis_kejadian', 'Kendala lainnya')->count(),
                ]
            ];
        });

        return response()->json($metrics);
    }
}
