<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kategori_kejadians', function (Blueprint $table) {
            $table->string('icon', 50)->nullable()->default('help-circle')->after('nama_kategori');
        });

        // Backfill data
        \Illuminate\Support\Facades\DB::table('kategori_kejadians')->where('nama_kategori', 'Kebakaran tebu')->update(['icon' => 'flame']);
        \Illuminate\Support\Facades\DB::table('kategori_kejadians')->where('nama_kategori', 'Serangan hama')->update(['icon' => 'bug']);
        \Illuminate\Support\Facades\DB::table('kategori_kejadians')->where('nama_kategori', 'Penyakit tanaman')->update(['icon' => 'tree']);
        \Illuminate\Support\Facades\DB::table('kategori_kejadians')->where('nama_kategori', 'Banjir/genangan')->update(['icon' => 'cloud-rain']);
        \Illuminate\Support\Facades\DB::table('kategori_kejadians')->where('nama_kategori', 'Kendala lainnya')->update(['icon' => 'alert-triangle']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kategori_kejadians', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
