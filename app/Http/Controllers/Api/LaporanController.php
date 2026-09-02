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
            'foto_bukti' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120', // maks 5MB
            'keterangan_tambahan' => 'nullable|string',
            'waktu_lapor' => 'nullable|date',
        ]);

        $path = $request->file('foto_bukti')->store('laporans', 'public');
        $fotoUrl = asset('storage/' . $path);

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
            'foto_bukti' => $fotoUrl,
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
        // Tambahan Pengecekan Otorisasi secara implisit di controller (Middleware juga sudah aktif)
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
        // tim_penanggung_jawab & kendala disimpan terpisah dari catatan --
        // SEBELUMNYA ketiganya digabung jadi satu string di
        // catatan_tindak_lanjut dan tidak pernah bisa dibaca ulang untuk
        // prefill form Tindak Lanjut.
        if ($request->has('tim_penanggung_jawab')) {
            $laporan->tim_penanggung_jawab = $request->tim_penanggung_jawab;
        }
        if ($request->has('kendala')) {
            $laporan->kendala = $request->kendala;
        }

        $laporan->save();

        // Clear Cache Dashboard setelah update status
        Cache::forget('dashboard_summary_metrics');

        return response()->json([
            'message' => 'Status laporan berhasil diperbarui',
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
    public function summaryMetrics()
    {
        $metrics = Cache::remember('dashboard_summary_metrics', 300, function () {
            return [
                // Pakai counter historis (laporan_counters), BUKAN
                // Laporan::count() -- lihat komentar di migration
                // laporan_counters kenapa ini penting untuk fitur
                // "Hapus Semua Laporan".
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
