<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Grade;
use App\Enums\KategoriStok;
use App\Enums\Role;
use App\Services\ProduksiService;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMaster();
        $this->travelTo('2026-08-13 09:00:00');
    }

    public function test_dashboard_merangkum_stok_produksi_dan_keuangan(): void
    {
        $this->masukSebagai(Role::OWNER);
        $k1 = $this->karyawan('Pardi');
        $k2 = $this->karyawan('Asep');
        $k3 = $this->karyawan('Joko');
        $k4 = $this->karyawan('Eko');
        $this->tambahStok(KategoriStok::NS1, 300);
        $this->tambahStok(KategoriStok::NS2, 2000);

        $produksi = app(ProduksiService::class);

        $selesai = $produksi->mulai([
            'tanggal' => '2026-08-13', 'grade' => Grade::NS1, 'kg_bahan_mentah' => 100,
            'karyawan_1_id' => $k1->id, 'karyawan_2_id' => $k2->id,
        ]);
        $produksi->selesaikan($selesai, 80, 20);

        // Satu tungku sengaja dibiarkan berjalan.
        $produksi->mulai([
            'tanggal' => '2026-08-13', 'grade' => Grade::NS1, 'kg_bahan_mentah' => 100,
            'karyawan_1_id' => $k3->id, 'karyawan_2_id' => $k4->id,
        ]);

        $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');

        $this->assertSame('2026-08-13', $data['tanggal']);
        $this->assertEqualsWithDelta(200.0, $data['stok']['bahanMentah']['NS 1'], 0.01);
        $this->assertEqualsWithDelta(2200.0, $data['stok']['bahanMentah']['total'], 0.01);
        $this->assertEqualsWithDelta(80.0, $data['stok']['kristal'], 0.01);
        $this->assertEqualsWithDelta(20.0, $data['stok']['brondol'], 0.01);

        $this->assertSame(2, $data['produksiHariIni']['jumlahSesi']);
        $this->assertSame(1, $data['produksiHariIni']['tungkuAktif']);
        $this->assertEqualsWithDelta(100.0, $data['produksiHariIni']['totalProduksi'], 0.01);
        $this->assertEqualsWithDelta(100.0, $data['rendemenBulanIni'], 0.01);

        $this->assertArrayHasKey('keuangan', $data);
        $this->assertCount(6, $data['tren']);
    }

    public function test_aktivitas_terbaru_mengambil_lintas_modul(): void
    {
        $this->masukSebagai(Role::OWNER);
        $petani = $this->petani('Sukirman');

        $this->postJson('/api/v1/pembelian', [
            'tanggal' => '2026-08-13', 'petaniId' => $petani->id, 'grade' => 'NS 1', 'kg' => 100,
        ])->assertCreated();

        $aktivitas = $this->getJson('/api/v1/dashboard')->assertOk()->json('data.aktivitasTerbaru');

        $this->assertNotEmpty($aktivitas);
        $this->assertSame('Pembelian', $aktivitas[0]['modul']);
        $this->assertStringContainsString('Sukirman', $aktivitas[0]['keterangan']);
    }

    public function test_tren_rendemen_mengembalikan_deret_harian(): void
    {
        $this->masukSebagai();

        $tren = $this->getJson('/api/v1/produksi/tren-rendemen?hari=14')->assertOk()->json('data');

        $this->assertCount(14, $tren);
        $this->assertSame('2026-08-13', $tren[13]['tanggal']);
        $this->assertSame('2026-07-31', $tren[0]['tanggal']);
    }
}
