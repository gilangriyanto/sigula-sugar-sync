<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kartu stok: log append-only seluruh mutasi. Tidak pernah di-update/dihapus,
     * pembatalan transaksi dicatat sebagai mutasi balik agar audit trail utuh.
     */
    public function up(): void
    {
        Schema::create('kartu_stok', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('kategori', 20);
            // masuk | keluar
            $table->string('jenis', 10);
            $table->decimal('jumlah_kg', 14, 2);
            $table->decimal('saldo_setelah', 14, 2);
            $table->string('keterangan');
            // referensi polimorfik ke transaksi asal (pembelian/sesi tungku/penjualan)
            $table->nullableMorphs('referensi');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kategori', 'tanggal']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kartu_stok');
    }
};
