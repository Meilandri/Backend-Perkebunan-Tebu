<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom geometri (radius/area) yang sebelumnya hanya ada di
     * UI Form Laporan (state lokal React) dan tidak pernah benar-benar
     * disimpan ke database. Akibatnya visualisasi radius/area cuma tampak
     * sesaat di form pengisian, tapi hilang total begitu laporan dibuka
     * lagi lewat Peta Interaktif -- karena datanya memang tidak pernah
     * dikirim & disimpan.
     */
    public function up(): void
    {
        Schema::table('laporans', function (Blueprint $table) {
            $table->enum('location_type', ['titik', 'radius', 'area'])->default('titik')->after('longitude');
            $table->decimal('radius', 10, 2)->nullable()->after('location_type');
            $table->string('radius_unit', 5)->nullable()->after('radius');
            $table->enum('area_type', ['persegi', 'lingkaran'])->nullable()->after('radius_unit');
            $table->decimal('area_dimension_1', 10, 2)->nullable()->after('area_type');
            $table->decimal('area_dimension_2', 10, 2)->nullable()->after('area_dimension_1');
        });
    }

    public function down(): void
    {
        Schema::table('laporans', function (Blueprint $table) {
            $table->dropColumn([
                'location_type',
                'radius',
                'radius_unit',
                'area_type',
                'area_dimension_1',
                'area_dimension_2',
            ]);
        });
    }
};
