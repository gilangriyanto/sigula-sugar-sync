<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hasil pembagian rata otomatis (÷2) dari satu sesi tungku ke dua karyawan.
     * Sumber data tunggal untuk modul penggajian.
     */
    public function up(): void
    {
        Schema::create('produksi_karyawan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_tungku_id')->constrained('sesi_tungku')->cascadeOnDelete();
            $table->foreignId('karyawan_id')->constrained('karyawan')->restrictOnDelete();
            // disalin dari sesi untuk mempermudah query rekap mingguan
            $table->date('tanggal');
            $table->decimal('kg_bahan_mentah_porsi', 12, 2);
            $table->decimal('kg_kristal_porsi', 12, 2);
            $table->decimal('kg_brondol_porsi', 12, 2);
            $table->timestamps();

            $table->unique(['sesi_tungku_id', 'karyawan_id']);
            $table->index(['karyawan_id', 'tanggal']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produksi_karyawan');
    }
};
