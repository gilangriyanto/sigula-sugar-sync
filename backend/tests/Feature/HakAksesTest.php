<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KategoriStok;
use App\Enums\Role;
use Tests\TestCase;

class HakAksesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMaster();
        $this->travelTo('2026-08-13 09:00:00');
    }

    public function test_staff_gudang_tidak_bisa_membuka_keuangan(): void
    {
        $this->masukSebagai(Role::STAFF_GUDANG);

        $this->getJson('/api/v1/keuangan/laba-rugi')->assertStatus(403);
        $this->getJson('/api/v1/keuangan/tren')->assertStatus(403);
        $this->getJson('/api/v1/keuangan/biaya')->assertStatus(403);
        $this->getJson('/api/v1/audit-log')->assertStatus(403);
    }

    public function test_staff_gudang_tidak_bisa_membuka_penggajian_dan_penjualan(): void
    {
        $this->masukSebagai(Role::STAFF_GUDANG);

        $this->getJson('/api/v1/penggajian')->assertStatus(403);
        $this->getJson('/api/v1/penjualan')->assertStatus(403);
    }

    public function test_staff_gudang_boleh_mengelola_pembelian_dan_stok(): void
    {
        $this->masukSebagai(Role::STAFF_GUDANG);
        $petani = $this->petani();

        $this->postJson('/api/v1/pembelian', [
            'tanggal' => '2026-08-13', 'petaniId' => $petani->id, 'grade' => 'NS 1', 'kg' => 100,
        ])->assertCreated();

        $this->getJson('/api/v1/stok')->assertOk();
        $this->postJson('/api/v1/stok/opname', [
            'kategori' => 'NS 1', 'stokFisik' => 95, 'alasan' => 'Susut',
        ])->assertCreated();
    }

    public function test_staff_produksi_hanya_boleh_menyentuh_modul_produksi(): void
    {
        $this->masukSebagai(Role::STAFF_PRODUKSI);
        $k1 = $this->karyawan('Pardi');
        $k2 = $this->karyawan('Asep');
        $this->tambahStok(KategoriStok::NS1, 100);

        $this->postJson('/api/v1/produksi/sesi', [
            'tanggal' => '2026-08-13', 'grade' => 'NS 1', 'kgBahan' => 100,
            'karyawan1Id' => $k1->id, 'karyawan2Id' => $k2->id,
        ])->assertCreated();

        $this->getJson('/api/v1/stok')->assertOk();

        // Tidak boleh: pembelian, penjualan, penggajian, keuangan, master data.
        $this->postJson('/api/v1/pembelian', [
            'tanggal' => '2026-08-13', 'petaniId' => 1, 'grade' => 'NS 1', 'kg' => 10,
        ])->assertStatus(403);
        $this->getJson('/api/v1/pembelian')->assertStatus(403);
        $this->getJson('/api/v1/penjualan')->assertStatus(403);
        $this->getJson('/api/v1/penggajian')->assertStatus(403);
        $this->postJson('/api/v1/master/harga', ['grade' => 'NS 1', 'harga' => 15000])->assertStatus(403);
        $this->postJson('/api/v1/stok/opname', [
            'kategori' => 'NS 1', 'stokFisik' => 90, 'alasan' => 'Coba',
        ])->assertStatus(403);
    }

    public function test_owner_punya_akses_penuh(): void
    {
        $this->masukSebagai(Role::OWNER);

        $this->getJson('/api/v1/dashboard')->assertOk();
        $this->getJson('/api/v1/petani')->assertOk();
        $this->getJson('/api/v1/master/harga')->assertOk();
        $this->getJson('/api/v1/pembelian')->assertOk();
        $this->getJson('/api/v1/stok')->assertOk();
        $this->getJson('/api/v1/produksi/sesi')->assertOk();
        $this->getJson('/api/v1/penggajian')->assertOk();
        $this->getJson('/api/v1/penjualan')->assertOk();
        $this->getJson('/api/v1/keuangan/laba-rugi')->assertOk();
        $this->getJson('/api/v1/audit-log')->assertOk();
    }

    public function test_dashboard_menyembunyikan_angka_keuangan_dari_non_owner(): void
    {
        $this->masukSebagai(Role::STAFF_PRODUKSI);
        $produksi = $this->getJson('/api/v1/dashboard')->assertOk();

        $produksi->assertJsonMissingPath('data.keuangan');
        $this->assertNotNull($produksi->json('data.stok'));

        $this->masukSebagai(Role::OWNER);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonStructure([
            'data' => ['keuangan' => ['pendapatanBulanIni', 'labaBulanIni'], 'tren'],
        ]);
    }
}
