<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Histori harga beli per grade. Perubahan harga SELALU insert record baru
     * (tidak pernah update record lama) supaya transaksi historis tetap
     * merujuk harga yang berlaku saat transaksi terjadi.
     */
    public function up(): void
    {
        Schema::create('grade_harga', function (Blueprint $table) {
            $table->id();
            $table->string('grade', 20);
            $table->decimal('harga_per_kg', 16, 2);
            $table->dateTime('berlaku_dari');
            $table->string('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['grade', 'berlaku_dari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_harga');
    }
};
