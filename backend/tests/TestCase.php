<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\Grade;
use App\Enums\JenisTarif;
use App\Enums\KategoriStok;
use App\Enums\Role;
use App\Models\Eksportir;
use App\Models\GradeHarga;
use App\Models\Karyawan;
use App\Models\Petani;
use App\Models\TarifUpah;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function user(Role $role = Role::OWNER, bool $aktif = true): User
    {
        return User::factory()->create([
            'role' => $role->value,
            'aktif' => $aktif,
        ]);
    }

    /** Login sebagai role tertentu memakai token Sanctum. */
    protected function masukSebagai(Role $role = Role::OWNER): User
    {
        $user = $this->user($role);
        Sanctum::actingAs($user);

        return $user;
    }

    /** Harga beli & tarif upah standar perusahaan. */
    protected function seedMaster(?string $berlakuDari = null): void
    {
        $berlakuDari ??= now()->subYear()->toDateTimeString();

        $harga = ['ns1' => 14500, 'ns2' => 12750, 'kecap' => 9500];

        foreach (Grade::cases() as $grade) {
            GradeHarga::create([
                'grade' => $grade->value,
                'harga_per_kg' => $harga[$grade->value],
                'berlaku_dari' => $berlakuDari,
            ]);
        }

        foreach (JenisTarif::cases() as $jenis) {
            TarifUpah::create([
                'jenis' => $jenis->value,
                'nilai' => $jenis->nilaiDefault(),
                'berlaku_dari' => $berlakuDari,
            ]);
        }
    }

    protected function petani(string $nama = 'Sukirman'): Petani
    {
        return Petani::factory()->create(['nama' => $nama]);
    }

    protected function karyawan(string $nama): Karyawan
    {
        return Karyawan::factory()->create(['nama' => $nama]);
    }

    protected function eksportir(string $nama = 'PT Global Sweet Export'): Eksportir
    {
        return Eksportir::factory()->create(['nama' => $nama]);
    }

    /** Menambah saldo stok awal untuk kebutuhan skenario test. */
    protected function tambahStok(KategoriStok $kategori, float $kg, ?string $tanggal = null): void
    {
        DB::transaction(function () use ($kategori, $kg, $tanggal): void {
            app(StokService::class)->masuk($kategori, $kg, $tanggal ?? now()->toDateString(), 'Saldo awal test');
        });
    }

    protected function saldo(KategoriStok $kategori): float
    {
        return app(StokService::class)->saldo($kategori);
    }
}
