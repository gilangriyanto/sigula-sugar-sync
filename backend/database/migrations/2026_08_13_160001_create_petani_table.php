<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petani', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            // member | non_member
            $table->string('status', 20)->default('member')->index();
            // 3 digit, hanya untuk member. Ditampilkan sebagai "Petani 231".
            $table->string('nomor_member', 3)->nullable()->unique();
            $table->string('kontak', 40)->nullable();
            $table->text('alamat')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('nama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petani');
    }
};
