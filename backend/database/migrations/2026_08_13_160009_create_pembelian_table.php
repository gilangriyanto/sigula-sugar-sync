<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembelian', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_kwitansi', 40)->unique();
            $table->date('tanggal')->index();
            $table->foreignId('petani_id')->constrained('petani')->restrictOnDelete();
            $table->string('grade', 20)->index();
            // harga yang berlaku saat transaksi; harga_per_kg tetap disalin
            // karena bisa dinego manual per transaksi.
            $table->foreignId('grade_harga_id')->nullable()->constrained('grade_harga')->nullOnDelete();
            $table->decimal('kilogram', 12, 2);
            $table->decimal('harga_per_kg', 16, 2);
            $table->decimal('total', 18, 2);
            $table->string('status_pembayaran', 20)->default('lunas');
            $table->string('catatan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['petani_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembelian');
    }
};
