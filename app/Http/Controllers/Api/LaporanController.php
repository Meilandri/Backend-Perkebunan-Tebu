<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Laporan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LaporanController extends Controller
{
    /**
     * Mengambil daftar laporan dengan filter (Status, Jenis Kejadian, Rentang Waktu) & Pagination
     */
    public function index(Request $request)
    {
        $query = Laporan::with('pelapor');

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

        $query = Laporan::select('id_laporan', 'jenis_kejadian', 'latitude', 'longitude', 'status_penanganan', 'waktu_lapor', 'foto_bukti', 'keterangan_tambahan');

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
            'jenis_kejadian' => ['required', Rule::in(['Kebakaran tebu', 'Serangan hama', 'Penyakit tanaman', 'Banjir/genangan', 'Kendala lainnya'])],
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
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
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'foto_bukti' => $fotoUrl,
            'keterangan_tambahan' => $request->keterangan_tambahan,
            'status_penanganan' => 'Open',
            'waktu_lapor' => $request->waktu_lapor ?? now(),
        ]);

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
        $request->validate([
            'status_penanganan' => ['required', Rule::in(['Open', 'On-Progress', 'Closed'])],
            'catatan_tindak_lanjut' => 'nullable|string',
        ]);

        $laporan = Laporan::findOrFail($id);
        $laporan->status_penanganan = $request->status_penanganan;
        
        if ($request->filled('catatan_tindak_lanjut')) {
            $laporan->keterangan_tambahan .= "\n[Catatan Tindak Lanjut]: " . $request->catatan_tindak_lanjut;
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
     * Metrik Ringkasan untuk Dashboard (Menggunakan Caching - Spesifikasi PDF Hal 12)
     */
    public function summaryMetrics()
    {
        $metrics = Cache::remember('dashboard_summary_metrics', 300, function () {
            return [
                'total_laporan' => Laporan::count(),
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
