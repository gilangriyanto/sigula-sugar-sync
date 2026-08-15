<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Grade;
use App\Enums\JenisTarif;
use App\Enums\KategoriStok;
use App\Enums\Role;
use App\Models\GajiMingguan;
use App\Models\Karyawan;
use App\Models\TarifUpah;
use App\Services\ProduksiService;
use Tests\TestCase;

class PenggajianTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMaster();
        // Kamis 13 Agustus 2026 — periode gaji berjalan: Senin 10 s.d. Jumat 14.
        $this->travelTo('2026-08-13 09:00:00');
    }

    /**
     * Contoh dari client: Asep ikut 3 sesi tungku pada HARI YANG SAMA.
     * Kg-nya terakumulasi, tapi hari kerjanya tetap 1 (bukan 3).
     */
    public function test_tiga_sesi_dalam_satu_hari_tetap_dihitung_satu_hari_kerja(): void
    {
        $this->masukSebagai(Role::OWNER);
        $asep = $this->karyawan('Asep Saepudin');
        $pardi = $this->karyawan('Pardi');

        for ($i = 0; $i < 3; $i++) {
            $this->sesiSelesai($asep, $pardi, '2026-08-11', 100, 80, 20);
        }

        $baris = $this->barisGaji($asep);

        $this->assertEquals(1, $baris['hariKerja']);
        // 3 sesi × (80 ÷ 2) kristal dan (20 ÷ 2) brondol
        $this->assertEqualsWithDelta(120.0, $baris['kgKristal'], 0.001);
        $this->assertEqualsWithDelta(30.0, $baris['kgBrondol'], 0.001);
        // upah = 120 × 1.150 + 30 × 800 + 1 hari × 5.000
        $this->assertEqualsWithDelta(138_000.0, $baris['upahKristal'], 0.01);
        $this->assertEqualsWithDelta(24_000.0, $baris['upahBrondol'], 0.01);
        $this->assertEqualsWithDelta(5_000.0, $baris['uangMakan'], 0.01);
        $this->assertEqualsWithDelta(167_000.0, $baris['total'], 0.01);
    }

    public function test_hari_kerja_dihitung_per_tanggal_berbeda(): void
    {
        $this->masukSebagai();
        $asep = $this->karyawan('Asep');
        $pardi = $this->karyawan('Pardi');

        $this->sesiSelesai($asep, $pardi, '2026-08-10', 100, 80, 20);
        $this->sesiSelesai($asep, $pardi, '2026-08-11', 100, 80, 20);
        $this->sesiSelesai($asep, $pardi, '2026-08-12', 100, 80, 20);

        $baris = $this->barisGaji($asep);

        $this->assertEquals(3, $baris['hariKerja']);
        $this->assertEqualsWithDelta(15_000.0, $baris['uangMakan'], 0.01);
    }

    /** Periode gaji Senin-Jumat: produksi Sabtu/Minggu tidak masuk periode ini. */
    public function test_periode_gaji_hanya_senin_sampai_jumat(): void
    {
        $this->masukSebagai();
        $asep = $this->karyawan('Asep');
        $pardi = $this->karyawan('Pardi');

        $this->sesiSelesai($asep, $pardi, '2026-08-14', 100, 80, 20); // Jumat, masuk
        $this->sesiSelesai($asep, $pardi, '2026-08-15', 100, 80, 20); // Sabtu, tidak masuk

        $response = $this->getJson('/api/v1/penggajian?tanggal=2026-08-13')->assertOk();

        $this->assertSame('2026-08-10', $response->json('data.periode.senin'));
        $this->assertSame('2026-08-14', $response->json('data.periode.jumat'));

        $baris = collect($response->json('data.baris'))->firstWhere('karyawanId', (string) $asep->id);
        $this->assertEqualsWithDelta(40.0, $baris['kgKristal'], 0.001);
        $this->assertEquals(1, $baris['hariKerja']);
    }

    /** Tarif yang dipakai adalah tarif pada tanggal produksi, bukan tarif terbaru. */
    public function test_memakai_tarif_yang_berlaku_pada_tanggal_produksi(): void
    {
        $this->masukSebagai();
        $asep = $this->karyawan('Asep');
        $pardi = $this->karyawan('Pardi');

        $this->sesiSelesai($asep, $pardi, '2026-08-10', 100, 80, 20);

        // Tarif naik SETELAH produksi terjadi.
        TarifUpah::create([
            'jenis' => JenisTarif::KRISTAL->value,
            'nilai' => 2000,
            'berlaku_dari' => '2026-08-12 00:00:00',
        ]);

        $baris = $this->barisGaji($asep);

        // Tetap memakai tarif lama 1.150 untuk produksi tanggal 10.
        $this->assertEqualsWithDelta(46_000.0, $baris['upahKristal'], 0.01);
    }

    public function test_kenaikan_tarif_di_tengah_minggu_hanya_berlaku_untuk_hari_setelahnya(): void
    {
        $this->masukSebagai();
        $asep = $this->karyawan('Asep');
        $pardi = $this->karyawan('Pardi');

        $this->sesiSelesai($asep, $pardi, '2026-08-10', 100, 80, 20);

        TarifUpah::create([
            'jenis' => JenisTarif::KRISTAL->value,
            'nilai' => 1500,
            'berlaku_dari' => '2026-08-12 00:00:00',
        ]);

        $this->sesiSelesai($asep, $pardi, '2026-08-12', 100, 80, 20);

        $baris = $this->barisGaji($asep);

        // 40 kg × 1.150 (tgl 10) + 40 kg × 1.500 (tgl 12)
        $this->assertEqualsWithDelta(46_000.0 + 60_000.0, $baris['upahKristal'], 0.01);
    }

    public function test_bayar_gaji_membekukan_angka_dan_mengubah_status(): void
    {
        $this->masukSebagai(Role::OWNER);
        $asep = $this->karyawan('Asep');
        $pardi = $this->karyawan('Pardi');
        $this->sesiSelesai($asep, $pardi, '2026-08-11', 100, 80, 20);

        $this->postJson("/api/v1/penggajian/{$asep->id}/bayar", ['tanggal' => '2026-08-13'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Sudah Dibayar')
            ->assertJsonPath('data.periodeSenin', '2026-08-10')
            ->assertJsonPath('data.periodeJumat', '2026-08-14');

        // 40 kg × 1.150 + 10 kg × 800 + 1 hari × 5.000
        $snapshot = GajiMingguan::query()->where('karyawan_id', $asep->id)->firstOrFail();
        $this->assertEqualsWithDelta(59_000.0, (float) $snapshot->total, 0.01);
        $this->assertNotNull($snapshot->dibayar_pada);

        $baris = $this->barisGaji($asep);
        $this->assertTrue($baris['dibayar']);
    }

    public function test_bayar_ulang_bersifat_idempoten(): void
    {
        $this->masukSebagai();
        $asep = $this->karyawan('Asep');
        $pardi = $this->karyawan('Pardi');
        $this->sesiSelesai($asep, $pardi, '2026-08-11', 100, 80, 20);

        $this->postJson("/api/v1/penggajian/{$asep->id}/bayar", ['tanggal' => '2026-08-13'])->assertOk();
        $this->postJson("/api/v1/penggajian/{$asep->id}/bayar", ['tanggal' => '2026-08-13'])->assertOk();

        $this->assertSame(1, GajiMingguan::query()->where('karyawan_id', $asep->id)->count());
    }

    public function test_bayar_semua_menandai_seluruh_karyawan_periode_itu(): void
    {
        $this->masukSebagai();
        $asep = $this->karyawan('Asep');
        $pardi = $this->karyawan('Pardi');
        $this->sesiSelesai($asep, $pardi, '2026-08-11', 100, 80, 20);

        $this->postJson('/api/v1/penggajian/bayar-semua', ['tanggal' => '2026-08-13'])
            ->assertOk()
            ->assertJsonPath('data.jumlahKaryawan', 2);

        $this->assertSame(2, GajiMingguan::query()->count());
    }

    public function test_slip_gaji_berisi_rincian_lengkap(): void
    {
        $this->masukSebagai();
        $asep = $this->karyawan('Asep');
        $pardi = $this->karyawan('Pardi');
        $this->sesiSelesai($asep, $pardi, '2026-08-11', 100, 80, 20);

        $this->getJson("/api/v1/penggajian/slip/{$asep->id}?tanggal=2026-08-13")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'periode' => ['senin', 'jumat', 'label'],
                    'tarif' => ['kristal', 'brondol', 'uangMakan'],
                    'baris' => [
                        'karyawanId', 'nama', 'kgKristal', 'kgBrondol', 'hariKerja',
                        'upahKristal', 'upahBrondol', 'uangMakan', 'total', 'dibayar',
                    ],
                ],
            ]);
    }

    public function test_sesi_tidak_bisa_dibatalkan_setelah_gajinya_dibayar(): void
    {
        $this->masukSebagai();
        $asep = $this->karyawan('Asep');
        $pardi = $this->karyawan('Pardi');
        $sesi = $this->sesiSelesai($asep, $pardi, '2026-08-11', 100, 80, 20);

        $this->postJson('/api/v1/penggajian/bayar-semua', ['tanggal' => '2026-08-13'])->assertOk();

        $this->deleteJson("/api/v1/produksi/sesi/{$sesi}")->assertStatus(422);
    }

    /** @return int id sesi tungku */
    private function sesiSelesai(
        Karyawan $k1,
        Karyawan $k2,
        string $tanggal,
        float $kgBahan,
        float $kgKristal,
        float $kgBrondol,
    ): int {
        $this->tambahStok(KategoriStok::NS1, $kgBahan, $tanggal);

        $produksi = app(ProduksiService::class);

        $sesi = $produksi->mulai([
            'tanggal' => $tanggal,
            'grade' => Grade::NS1,
            'kg_bahan_mentah' => $kgBahan,
            'karyawan_1_id' => $k1->id,
            'karyawan_2_id' => $k2->id,
        ]);

        $produksi->selesaikan($sesi, $kgKristal, $kgBrondol);

        return (int) $sesi->id;
    }

    /** @return array<string, mixed> */
    private function barisGaji(Karyawan $karyawan, string $tanggal = '2026-08-13'): array
    {
        $baris = collect($this->getJson('/api/v1/penggajian?tanggal='.$tanggal)->assertOk()->json('data.baris'))
            ->firstWhere('karyawanId', (string) $karyawan->id);

        $this->assertNotNull($baris, 'Baris gaji karyawan tidak ditemukan.');

        return $baris;
    }
}
