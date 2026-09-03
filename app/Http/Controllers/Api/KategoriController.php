<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\KategoriKejadian;

class KategoriController extends Controller
{
    public function index()
    {
        $kategoris = KategoriKejadian::all();
        return response()->json($kategoris);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_kategori' => 'required|string|max:255|unique:kategori_kejadians,nama_kategori',
            'icon' => 'nullable|string|max:50',
        ]);

        $kategori = KategoriKejadian::create([
            'nama_kategori' => $request->nama_kategori,
            'icon' => $request->icon ?? 'help-circle',
        ]);

        return response()->json(['message' => 'Kategori berhasil ditambahkan', 'data' => $kategori], 201);
    }

    public function update(Request $request, $id)
    {
        $kategori = KategoriKejadian::findOrFail($id);

        $request->validate([
            'nama_kategori' => 'sometimes|required|string|max:255|unique:kategori_kejadians,nama_kategori,' . $id,
            'icon' => 'nullable|string|max:50',
        ]);

        if ($request->has('nama_kategori')) {
            $kategori->nama_kategori = $request->nama_kategori;
        }
        if ($request->has('icon')) {
            $kategori->icon = $request->icon;
        }

        $kategori->save();

        return response()->json(['message' => 'Kategori berhasil diubah', 'data' => $kategori]);
    }

    public function destroy($id)
    {
        $kategori = KategoriKejadian::findOrFail($id);
        $kategori->delete();

        return response()->json(['message' => 'Kategori berhasil dihapus']);
    }
}
