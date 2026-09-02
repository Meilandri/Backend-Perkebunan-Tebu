<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Sektor;

class SektorController extends Controller
{
    public function index()
    {
        $sektors = Sektor::all();
        return response()->json($sektors);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_sektor' => 'required|string|max:255',
            'luas_ha' => 'nullable|numeric',
            'status' => 'nullable|string|max:50',
        ]);

        $sektor = Sektor::create([
            'nama_sektor' => $request->nama_sektor,
            'luas_ha' => $request->luas_ha,
            'status' => $request->status ?? 'Aktif',
        ]);

        return response()->json(['message' => 'Sektor berhasil ditambahkan', 'data' => $sektor], 201);
    }

    public function update(Request $request, $id)
    {
        $sektor = Sektor::findOrFail($id);

        $request->validate([
            'nama_sektor' => 'sometimes|required|string|max:255',
            'luas_ha' => 'nullable|numeric',
            'status' => 'nullable|string|max:50',
        ]);

        $sektor->update($request->all());

        return response()->json(['message' => 'Sektor berhasil diubah', 'data' => $sektor]);
    }

    public function destroy($id)
    {
        $sektor = Sektor::findOrFail($id);
        $sektor->delete();

        return response()->json(['message' => 'Sektor berhasil dihapus']);
    }
}
