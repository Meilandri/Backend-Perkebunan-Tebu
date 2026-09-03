<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RiwayatAktivitas;

class RiwayatController extends Controller
{
    public function index(Request $request)
    {
        $query = RiwayatAktivitas::query();

        // Optional filtering by role or action
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        if ($request->has('aksi')) {
            $query->where('aksi', 'like', '%' . $request->aksi . '%');
        }

        $riwayat = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 20));
        
        return response()->json($riwayat);
    }

    public function store(Request $request)
    {
        $request->validate([
            'aksi' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
        ]);

        RiwayatAktivitas::catat($request->aksi, $request->deskripsi);

        return response()->json([
            'message' => 'Riwayat berhasil dicatat'
        ], 201);
    }
}
