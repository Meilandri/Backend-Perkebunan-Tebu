<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimPetugas;
use Illuminate\Http\Request;

class TimPetugasController extends Controller
{
    public function index()
    {
        $timPetugas = TimPetugas::all();
        return response()->json($timPetugas);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_tim' => 'required|string|max:255',
            'nama_ketua' => 'nullable|string|max:255',
            'nomor_wa' => 'required|string|max:255',
            'spesialisasi' => 'nullable|string|max:255',
        ]);

        $timPetugas = TimPetugas::create($validated);

        return response()->json([
            'message' => 'Tim petugas berhasil ditambahkan',
            'data' => $timPetugas,
        ], 201);
    }

    public function show($id)
    {
        $timPetugas = TimPetugas::findOrFail($id);
        return response()->json($timPetugas);
    }

    public function update(Request $request, $id)
    {
        $timPetugas = TimPetugas::findOrFail($id);

        $validated = $request->validate([
            'nama_tim' => 'sometimes|required|string|max:255',
            'nama_ketua' => 'nullable|string|max:255',
            'nomor_wa' => 'sometimes|required|string|max:255',
            'spesialisasi' => 'nullable|string|max:255',
        ]);

        $timPetugas->update($validated);

        return response()->json([
            'message' => 'Tim petugas berhasil diperbarui',
            'data' => $timPetugas,
        ]);
    }

    public function destroy($id)
    {
        $timPetugas = TimPetugas::findOrFail($id);
        $timPetugas->delete();

        return response()->json([
            'message' => 'Tim petugas berhasil dihapus',
        ]);
    }
}
