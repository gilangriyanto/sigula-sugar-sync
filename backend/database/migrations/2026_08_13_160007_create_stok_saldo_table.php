<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saldo stok berjalan per kategori. Sengaja dipisah dari kartu_stok supaya
     * pembacaan saldo tidak perlu SUM seluruh histori transaksi.
     */
    public function up(): void
    {
        Schema::create('stok_saldo', function (Blueprint $table) {
            $table->id();
            // ns1 | ns2 | kecap | kristal | brondol
            $table->string('kategori', 20)->unique();
            $table->decimal('saldo_kg', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_saldo');
    }
};
