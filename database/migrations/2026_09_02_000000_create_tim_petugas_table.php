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
        Schema::create('tim_petugas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_tim');
            $table->string('nama_ketua')->nullable();
            $table->string('nomor_wa');
            $table->string('spesialisasi')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tim_petugas');
    }
};
