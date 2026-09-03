<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Laporan;
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

        $query = Laporan::select('id_laporan', 'kode_laporan', 'jenis_kejadian', 'wilayah', 'latitude', 'longitude', 'location_type', 'radius', 'radius_unit', 'area_type', 'area_dimension_1', 'area_dimension_2', 'status_penanganan', 'waktu_lapor', 'foto_bukti', 'keterangan_tambahan');

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

        $user = $request->user();

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
        return response()->json($laporan);
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
        ]);

        $laporan = Laporan::findOrFail($id);
        $laporan->status_penanganan = $request->status_penanganan;

        if ($request->filled('catatan_tindak_lanjut')) {
            $laporan->catatan_tindak_lanjut = $request->catatan_tindak_lanjut;
        }
        if ($request->has('tim_penanggung_jawab')) {
            $laporan->tim_penanggung_jawab = $request->tim_penanggung_jawab;
        }
        if ($request->has('kendala')) {
            $laporan->kendala = $request->kendala;
        }

        // Support for penyelesaian fields if status is Closed
        if ($request->status_penanganan === 'Closed') {
            if ($request->has('tgl_selesai')) $laporan->tgl_selesai = $request->tgl_selesai;
            if ($request->has('durasi_penanganan')) $laporan->durasi_penanganan = $request->durasi_penanganan;
            if ($request->has('alat_digunakan')) $laporan->alat_digunakan = $request->alat_digunakan;
            if ($request->has('catatan_selesai')) $laporan->catatan_selesai = $request->catatan_selesai;
            
            // Note: Since this is often a PATCH/PUT, file uploads might be tricky if not sent as multipart/form-data.
            // But we will handle it if present.
            if ($request->hasFile('foto_selesai') || $request->has('foto_selesai')) {
                $fotoUrls = [];
                if ($request->hasFile('foto_selesai')) {
                    $files = is_array($request->file('foto_selesai')) ? $request->file('foto_selesai') : [$request->file('foto_selesai')];
                    foreach ($files as $file) {
                        $path = $file->store('penanganan', 'public');
                        $fotoUrls[] = asset('storage/' . $path);
                    }
                } elseif (is_array($request->foto_selesai)) {
                    $fotoUrls = $request->foto_selesai;
                }
                if (!empty($fotoUrls)) {
                    $laporan->foto_selesai = $fotoUrls;
                }
            }
        }

        $laporan->save();

        Cache::forget('dashboard_summary_metrics');

        return response()->json([
            'message' => 'Status laporan berhasil diperbarui',
            'data' => $laporan
        ]);
    }

    /**
     * Menyelesaikan laporan secara khusus dengan detail penanganan
     */
    public function selesai(Request $request, $id)
    {
        $laporan = Laporan::findOrFail($id);

        $request->validate([
            'tgl_selesai' => 'nullable|date',
            'durasi_penanganan' => 'nullable|string',
            'alat_digunakan' => 'nullable|string',
            'catatan_selesai' => 'nullable|string',
        ]);

        $fotoUrls = [];
        if ($request->hasFile('foto_selesai')) {
            $files = is_array($request->file('foto_selesai')) ? $request->file('foto_selesai') : [$request->file('foto_selesai')];
            foreach ($files as $file) {
                $path = $file->store('penanganan', 'public');
                $fotoUrls[] = asset('storage/' . $path);
            }
        } elseif (is_array($request->foto_selesai)) {
            $fotoUrls = $request->foto_selesai;
        }

        $laporan->update([
            'status_penanganan' => 'Closed',
            'tgl_selesai' => $request->tgl_selesai ?? now(),
            'durasi_penanganan' => $request->durasi_penanganan,
            'alat_digunakan' => $request->alat_digunakan,
            'catatan_selesai' => $request->catatan_selesai,
            'foto_selesai' => !empty($fotoUrls) ? $fotoUrls : $laporan->foto_selesai,
        ]);

        Cache::forget('dashboard_summary_metrics');

        return response()->json([
            'message' => 'Laporan berhasil diselesaikan',
            'data' => $laporan
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

        $total = Laporan::count();
        Laporan::query()->delete();

        // PENTING: `laporan_counters` SENGAJA tidak disentuh di sini.
        // "Semua Insiden Hingga Saat Ini" di Ringkasan Operasional adalah
        // angka historis kumulatif, bukan jumlah baris yang sedang ada --
        // jadi harus tetap sama walau semua laporan aktif dihapus.
        Cache::forget('dashboard_summary_metrics');

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
