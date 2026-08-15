<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Grade;
use App\Enums\Role;
use App\Services\ProduksiService;
use Tests\TestCase;

class KeuanganTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMaster();
        $this->travelTo('2026-08-13 09:00:00');
    }

    /**
     * Pendapatan − (pembelian bahan + gaji termasuk uang makan) − biaya operasional.
     * Seluruh angka harus berasal dari transaksi nyata.
     */
    public function test_laba_rugi_dihitung_dari_transaksi_riil(): void
    {
        $this->masukSebagai(Role::OWNER);
        $petani = $this->petani();
        $eksportir = $this->eksportir();
        $k1 = $this->karyawan('Pardi');
        $k2 = $this->karyawan('Asep');

        // Pembelian: 100 kg × Rp 14.500 = Rp 1.450.000
        $this->postJson('/api/v1/pembelian', [
            'tanggal' => '2026-08-11', 'petaniId' => $petani->id, 'grade' => 'NS 1', 'kg' => 100,
        ])->assertCreated();

        // Produksi: 80 kg kristal + 20 kg brondol, dikerjakan 2 orang dalam 1 hari
        $produksi = app(ProduksiService::class);
        $sesi = $produksi->mulai([
            'tanggal' => '2026-08-11',
            'grade' => Grade::NS1,
            'kg_bahan_mentah' => 100,
            'karyawan_1_id' => $k1->id,
            'karyawan_2_id' => $k2->id,
        ]);
        $produksi->selesaikan($sesi, 80, 20);

        // Penjualan: 80 kg × Rp 24.500 + 20 kg × Rp 16.500 = Rp 2.290.000
        $this->postJson('/api/v1/penjualan', [
            'tanggal' => '2026-08-12',
            'eksportirId' => $eksportir->id,
            'kristal' => ['kg' => 80, 'harga' => 24500],
            'brondol' => ['kg' => 20, 'harga' => 16500],
        ])->assertCreated();

        // Biaya operasional
        $this->postJson('/api/v1/keuangan/biaya', [
            'tanggal' => '2026-08-12',
            'keterangan' => 'Tagihan listrik pabrik',
            'kategori' => 'Listrik',
            'jumlah' => 500000,
        ])->assertCreated();

        $data = $this->getJson('/api/v1/keuangan/laba-rugi?periode=bulan_ini')->assertOk()->json('data');

        // gaji = 80 × 1.150 + 20 × 800 + 2 orang × 1 hari × 5.000 = 92.000 + 16.000 + 10.000
        $gaji = 118_000.0;
        $hpp = 1_450_000.0 + $gaji;
        $pendapatan = 2_290_000.0;

        $this->assertEqualsWithDelta($pendapatan, $data['pendapatan'], 0.01);
        $this->assertEqualsWithDelta(1_450_000.0, $data['hpp']['bahan'], 0.01);
        $this->assertEqualsWithDelta($gaji, $data['hpp']['gaji']['total'], 0.01);
        $this->assertEqualsWithDelta(10_000.0, $data['hpp']['gaji']['uangMakan'], 0.01);
        $this->assertEqualsWithDelta($hpp, $data['hpp']['total'], 0.01);
        $this->assertEqualsWithDelta(500_000.0, $data['biayaOperasional'], 0.01);
        $this->assertEqualsWithDelta($pendapatan - $hpp - 500_000.0, $data['labaBersih'], 0.01);
    }

    public function test_periode_custom_membatasi_rentang_transaksi(): void
    {
        $this->masukSebagai();
        $petani = $this->petani();

        $this->postJson('/api/v1/pembelian', [
            'tanggal' => '2026-07-15', 'petaniId' => $petani->id, 'grade' => 'NS 1', 'kg' => 100,
        ])->assertCreated();
        $this->postJson('/api/v1/pembelian', [
            'tanggal' => '2026-08-11', 'petaniId' => $petani->id, 'grade' => 'NS 1', 'kg' => 200,
        ])->assertCreated();

        $bulanIni = $this->getJson('/api/v1/keuangan/laba-rugi?periode=bulan_ini')->assertOk()->json('data');
        $this->assertEqualsWithDelta(2_900_000.0, $bulanIni['hpp']['bahan'], 0.01);

        $bulanLalu = $this->getJson('/api/v1/keuangan/laba-rugi?periode=bulan_lalu')->assertOk()->json('data');
        $this->assertEqualsWithDelta(1_450_000.0, $bulanLalu['hpp']['bahan'], 0.01);

        $custom = $this->getJson('/api/v1/keuangan/laba-rugi?periode=custom&dari=2026-07-01&sampai=2026-08-31')
            ->assertOk()->json('data');
        $this->assertEqualsWithDelta(4_350_000.0, $custom['hpp']['bahan'], 0.01);
    }

    public function test_tren_bulanan_mengembalikan_enam_bulan(): void
    {
        $this->masukSebagai();

        $tren = $this->getJson('/api/v1/keuangan/tren')->assertOk()->json('data');

        $this->assertCount(6, $tren);
        $this->assertSame('2026-08', $tren[5]['bulan']);
        $this->assertSame('Agu 26', $tren[5]['label']);
        $this->assertSame('2026-03', $tren[0]['bulan']);
    }

    public function test_biaya_operasional_bisa_ditambah_diubah_dan_dihapus(): void
    {
        $this->masukSebagai();

        $id = $this->postJson('/api/v1/keuangan/biaya', [
            'tanggal' => '2026-08-12',
            'keterangan' => 'Sewa gudang',
            'kategori' => 'Sewa',
            'jumlah' => 2500000,
        ])->assertCreated()->assertJsonPath('data.kategori', 'Sewa')->json('data.id');

        $this->putJson("/api/v1/keuangan/biaya/{$id}", ['jumlah' => 3000000])
            ->assertOk()
            ->assertJsonPath('data.jumlah', 3000000);

        $this->deleteJson("/api/v1/keuangan/biaya/{$id}")->assertOk();
        $this->assertSoftDeleted('biaya_operasional', ['id' => $id]);
    }

    public function test_biaya_negatif_ditolak(): void
    {
        $this->masukSebagai();

        $this->postJson('/api/v1/keuangan/biaya', [
            'tanggal' => '2026-08-12',
            'keterangan' => 'Salah input',
            'kategori' => 'Lainnya',
            'jumlah' => -1000,
        ])->assertStatus(422)->assertJsonValidationErrors('jumlah');
    }
}
