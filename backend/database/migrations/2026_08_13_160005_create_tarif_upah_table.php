<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Histori tarif upah & uang makan. Pola sama dengan grade_harga:
     * penggajian memakai tarif yang berlaku pada TANGGAL PRODUKSI,
     * bukan tarif terbaru saat rekap dijalankan.
     */
    public function up(): void
    {
        Schema::create('tarif_upah', function (Blueprint $table) {
            $table->id();
            // kristal | brondol | uang_makan
            $table->string('jenis', 20);
            $table->decimal('nilai', 16, 2);
            $table->dateTime('berlaku_dari');
            $table->string('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['jenis', 'berlaku_dari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif_upah');
    }
};
