<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penjualan', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_invoice', 40)->unique();
            $table->date('tanggal')->index();
            $table->foreignId('eksportir_id')->constrained('eksportir')->restrictOnDelete();
            $table->decimal('total', 18, 2);
            $table->string('status_pembayaran', 20)->default('lunas')->index();
            $table->dateTime('dibayar_pada')->nullable();
            $table->string('catatan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penjualan');
    }
};
