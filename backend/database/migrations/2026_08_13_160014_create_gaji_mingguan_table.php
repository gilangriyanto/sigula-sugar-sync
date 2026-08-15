<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot gaji satu karyawan untuk satu periode Senin-Jumat.
     * Rekap berjalan dihitung live dari produksi_karyawan; begitu dibayar,
     * angkanya dibekukan di tabel ini supaya histori pembayaran tidak berubah
     * saat tarif/produksi lama disunting.
     */
    public function up(): void
    {
        Schema::create('gaji_mingguan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawan')->restrictOnDelete();
            $table->date('periode_senin');
            $table->date('periode_jumat');
            $table->decimal('kg_kristal', 12, 2);
            $table->decimal('kg_brondol', 12, 2);
            $table->unsignedSmallInteger('hari_kerja');
            $table->decimal('upah_kristal', 16, 2);
            $table->decimal('upah_brondol', 16, 2);
            $table->decimal('uang_makan', 16, 2);
            $table->decimal('total', 16, 2);
            // belum_dibayar | sudah_dibayar
            $table->string('status', 20)->default('sudah_dibayar');
            $table->dateTime('dibayar_pada')->nullable();
            $table->foreignId('dibayar_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['karyawan_id', 'periode_senin']);
            $table->index('periode_senin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gaji_mingguan');
    }
};
