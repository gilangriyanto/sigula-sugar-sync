<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KategoriStok;
use App\Enums\Role;
use App\Models\KartuStok;
use Tests\TestCase;

class StokTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMaster();
        $this->travelTo('2026-08-13 09:00:00');
    }

    public function test_ringkasan_stok_memuat_lima_kategori(): void
    {
        $this->masukSebagai(Role::STAFF_GUDANG);
        $this->tambahStok(KategoriStok::NS1, 3200);
        $this->tambahStok(KategoriStok::KRISTAL, 1800);

        $response = $this->getJson('/api/v1/stok')->assertOk();

        $this->assertEqualsWithDelta(3200.0, $response->json('data.saldo.NS 1'), 0.01);
        $this->assertEqualsWithDelta(1800.0, $response->json('data.saldo.Kristal'), 0.01);
        $this->assertCount(3, $response->json('data.bahanMentah'));
        $this->assertCount(2, $response->json('data.produkJadi'));
        $this->assertEqualsWithDelta(3200.0, $response->json('data.totalBahanMentah'), 0.01);
    }

    public function test_indikator_menipis_muncul_di_bawah_ambang(): void
    {
        $this->masukSebagai();
        $this->tambahStok(KategoriStok::NS1, 3000);
        $this->tambahStok(KategoriStok::NS2, 500);

        $bahan = collect($this->getJson('/api/v1/stok')->assertOk()->json('data.bahanMentah'));

        $this->assertSame('aman', $bahan->firstWhere('kode', 'ns1')['status']);
        $this->assertSame('menipis', $bahan->firstWhere('kode', 'ns2')['status']);
    }

    public function test_opname_menambah_stok_saat_fisik_lebih_banyak(): void
    {
        $this->masukSebagai(Role::STAFF_GUDANG);
        $this->tambahStok(KategoriStok::NS1, 100);

        $this->postJson('/api/v1/stok/opname', [
            'kategori' => 'NS 1',
            'stokFisik' => 120,
            'alasan' => 'Hasil hitung fisik gudang',
        ])->assertCreated()
            ->assertJsonPath('data.jenis', 'Masuk')
            ->assertJsonPath('data.jumlah', 20)
            ->assertJsonPath('data.saldoSetelah', 120);

        $this->assertSame(120.0, $this->saldo(KategoriStok::NS1));
    }

    public function test_opname_mengurangi_stok_saat_fisik_lebih_sedikit(): void
    {
        $this->masukSebagai();
        $this->tambahStok(KategoriStok::KRISTAL, 100);

        $this->postJson('/api/v1/stok/opname', [
            'kategori' => 'Kristal',
            'stokFisik' => 92.5,
            'alasan' => 'Susut penyimpanan',
        ])->assertCreated()->assertJsonPath('data.jenis', 'Keluar');

        $this->assertSame(92.5, $this->saldo(KategoriStok::KRISTAL));

        $mutasi = KartuStok::query()->latest('id')->firstOrFail();
        $this->assertStringContainsString('Stok opname: Susut penyimpanan', $mutasi->keterangan);
    }

    public function test_opname_tanpa_selisih_ditolak(): void
    {
        $this->masukSebagai();
        $this->tambahStok(KategoriStok::NS1, 100);

        $this->postJson('/api/v1/stok/opname', [
            'kategori' => 'NS 1',
            'stokFisik' => 100,
            'alasan' => 'Cek rutin',
        ])->assertStatus(422)->assertJsonValidationErrors('stokFisik');
    }

    public function test_opname_tanpa_alasan_ditolak(): void
    {
        $this->masukSebagai();

        $this->postJson('/api/v1/stok/opname', [
            'kategori' => 'NS 1',
            'stokFisik' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('alasan');
    }

    public function test_kartu_stok_bisa_difilter_kategori_dan_jenis(): void
    {
        $this->masukSebagai();
        $this->tambahStok(KategoriStok::NS1, 100);
        $this->tambahStok(KategoriStok::KRISTAL, 50);

        $this->assertCount(1, $this->getJson('/api/v1/stok/kartu?kategori=ns1')->assertOk()->json('data'));
        $this->assertCount(2, $this->getJson('/api/v1/stok/kartu?jenis=masuk')->assertOk()->json('data'));
        $this->assertCount(0, $this->getJson('/api/v1/stok/kartu?jenis=keluar')->assertOk()->json('data'));
    }

    public function test_saldo_setelah_selalu_konsisten_dengan_urutan_mutasi(): void
    {
        $this->masukSebagai();
        $this->tambahStok(KategoriStok::NS1, 100);
        $this->tambahStok(KategoriStok::NS1, 250);
        $this->tambahStok(KategoriStok::NS1, 50);

        $saldo = KartuStok::query()
            ->where('kategori', 'ns1')
            ->orderBy('id')
            ->pluck('saldo_setelah')
            ->map(static fn ($nilai): float => (float) $nilai)
            ->all();

        $this->assertSame([100.0, 350.0, 400.0], $saldo);
        $this->assertSame(400.0, $this->saldo(KategoriStok::NS1));
    }
}
