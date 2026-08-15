<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu invoice berisi maksimal 2 baris (kristal & brondol) dengan kg dan
     * harga masing-masing — bukan harga rata-rata gabungan.
     */
    public function up(): void
    {
        Schema::create('penjualan_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penjualan_id')->constrained('penjualan')->cascadeOnDelete();
            // kristal | brondol
            $table->string('jenis', 20);
            $table->decimal('kilogram', 12, 2);
            $table->decimal('harga_per_kg', 16, 2);
            $table->decimal('subtotal', 18, 2);
            $table->timestamps();

            $table->unique(['penjualan_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penjualan_item');
    }
};
