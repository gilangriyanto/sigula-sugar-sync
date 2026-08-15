<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unit produksi terkecil: 1 tungku, tepat 2 karyawan, satu kali masak
     * menghasilkan kristal + brondol sekaligus. Banyak tungku berjalan paralel
     * dalam satu hari.
     */
    public function up(): void
    {
        Schema::create('sesi_tungku', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->index();
            $table->string('kode_tungku', 30)->nullable();
            $table->string('grade', 20)->index();
            $table->decimal('kg_bahan_mentah', 12, 2);
            $table->foreignId('karyawan_1_id')->constrained('karyawan')->restrictOnDelete();
            $table->foreignId('karyawan_2_id')->constrained('karyawan')->restrictOnDelete();
            $table->decimal('kg_kristal_total', 12, 2)->nullable();
            $table->decimal('kg_brondol_total', 12, 2)->nullable();
            $table->decimal('rendemen', 8, 2)->nullable();
            // sedang_diproses | selesai
            $table->string('status', 20)->default('sedang_diproses')->index();
            $table->dateTime('selesai_pada')->nullable();
            $table->string('catatan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tanggal', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_tungku');
    }
};
