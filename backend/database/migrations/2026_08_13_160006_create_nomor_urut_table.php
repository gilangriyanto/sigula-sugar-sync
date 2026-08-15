<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Counter bernomor untuk kwitansi & invoice. Dikunci (lockForUpdate) saat
     * dipakai supaya dua request bersamaan tidak menghasilkan nomor kembar.
     */
    public function up(): void
    {
        Schema::create('nomor_urut', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 60)->unique();
            $table->unsignedBigInteger('nilai')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomor_urut');
    }
};
