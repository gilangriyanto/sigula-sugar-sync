<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KategoriStok;
use App\Enums\Role;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use Tests\TestCase;

class PenjualanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMaster();
        $this->travelTo('2026-08-13 09:00:00');
        $this->tambahStok(KategoriStok::KRISTAL, 5000);
        $this->tambahStok(KategoriStok::BRONDOL, 1000);
    }

    /** Satu invoice, dua baris dengan harga masing-masing (bukan harga rata-rata). */
    public function test_satu_invoice_berisi_dua_baris_dengan_harga_berbeda(): void
    {
        $this->masukSebagai(Role::OWNER);
        $eksportir = $this->eksportir();

        $response = $this->postJson('/api/v1/penjualan', [
            'tanggal' => '2026-08-13',
            'eksportirId' => $eksportir->id,
            'kristal' => ['kg' => 2000, 'harga' => 24500],
            'brondol' => ['kg' => 500, 'harga' => 16500],
        ])->assertCreated();

        $response->assertJsonPath('data.kristal.kg', 2000)
            ->assertJsonPath('data.kristal.harga', 24500)
            ->assertJsonPath('data.kristal.subtotal', 49_000_000)
            ->assertJsonPath('data.brondol.subtotal', 8_250_000)
            ->assertJsonPath('data.total', 57_250_000)
            ->assertJsonPath('data.noInvoice', 'INV/2026/0001');

        $this->assertSame(3000.0, $this->saldo(KategoriStok::KRISTAL));
        $this->assertSame(500.0, $this->saldo(KategoriStok::BRONDOL));
        $this->assertSame(1, Penjualan::query()->count());
        $this->assertSame(2, PenjualanItem::query()->count());
    }

    public function test_boleh_menjual_satu_jenis_saja(): void
    {
        $this->masukSebagai();
        $eksportir = $this->eksportir();

        $this->postJson('/api/v1/penjualan', [
            'tanggal' => '2026-08-13',
            'eksportirId' => $eksportir->id,
            'brondol' => ['kg' => 300, 'harga' => 16500],
        ])->assertCreated()
            ->assertJsonPath('data.kristal', null)
            ->assertJsonPath('data.total', 4_950_000);

        $this->assertSame(5000.0, $this->saldo(KategoriStok::KRISTAL));
        $this->assertSame(700.0, $this->saldo(KategoriStok::BRONDOL));
    }

    public function test_minimal_satu_baris_harus_diisi(): void
    {
        $this->masukSebagai();
        $eksportir = $this->eksportir();

        $this->postJson('/api/v1/penjualan', [
            'tanggal' => '2026-08-13',
            'eksportirId' => $eksportir->id,
        ])->assertStatus(422)->assertJsonValidationErrors('kristal');
    }

    public function test_penjualan_melebihi_stok_ditolak_tanpa_menyisakan_perubahan(): void
    {
        $this->masukSebagai();
        $eksportir = $this->eksportir();

        $this->postJson('/api/v1/penjualan', [
            'tanggal' => '2026-08-13',
            'eksportirId' => $eksportir->id,
            'kristal' => ['kg' => 1000, 'harga' => 24500],
            'brondol' => ['kg' => 5000, 'harga' => 16500],
        ])->assertStatus(422)->assertJsonValidationErrors('brondol.kg');

        // Baris kristal sempat diproses lebih dulu, tapi transaction harus rollback penuh.
        $this->assertSame(5000.0, $this->saldo(KategoriStok::KRISTAL));
        $this->assertSame(1000.0, $this->saldo(KategoriStok::BRONDOL));
        $this->assertSame(0, Penjualan::query()->count());
        $this->assertSame(0, PenjualanItem::query()->count());
    }

    public function test_kilogram_nol_ditolak(): void
    {
        $this->masukSebagai();
        $eksportir = $this->eksportir();

        $this->postJson('/api/v1/penjualan', [
            'tanggal' => '2026-08-13',
            'eksportirId' => $eksportir->id,
            'kristal' => ['kg' => 0, 'harga' => 24500],
        ])->assertStatus(422)->assertJsonValidationErrors('kristal.kg');
    }

    public function test_membatalkan_penjualan_mengembalikan_stok(): void
    {
        $this->masukSebagai();
        $eksportir = $this->eksportir();

        $id = $this->postJson('/api/v1/penjualan', [
            'tanggal' => '2026-08-13',
            'eksportirId' => $eksportir->id,
            'kristal' => ['kg' => 2000, 'harga' => 24500],
            'brondol' => ['kg' => 500, 'harga' => 16500],
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/penjualan/{$id}")->assertOk();

        $this->assertSame(5000.0, $this->saldo(KategoriStok::KRISTAL));
        $this->assertSame(1000.0, $this->saldo(KategoriStok::BRONDOL));
        $this->assertSoftDeleted('penjualan', ['id' => $id]);
    }

    public function test_status_pembayaran_bisa_diubah(): void
    {
        $this->masukSebagai();
        $eksportir = $this->eksportir();

        $id = $this->postJson('/api/v1/penjualan', [
            'tanggal' => '2026-08-13',
            'eksportirId' => $eksportir->id,
            'kristal' => ['kg' => 100, 'harga' => 24500],
            'statusPembayaran' => 'belum_lunas',
        ])->assertCreated()->assertJsonPath('data.statusPembayaran', 'Belum Lunas')->json('data.id');

        $this->patchJson("/api/v1/penjualan/{$id}/status", ['statusPembayaran' => 'lunas'])
            ->assertOk()
            ->assertJsonPath('data.statusPembayaran', 'Lunas');
    }

    public function test_invoice_siap_cetak_memuat_rincian_dua_baris(): void
    {
        $this->masukSebagai();
        $eksportir = $this->eksportir('PT Global Sweet Export');

        $invoice = $this->postJson('/api/v1/penjualan', [
            'tanggal' => '2026-08-13',
            'eksportirId' => $eksportir->id,
            'kristal' => ['kg' => 2000, 'harga' => 24500],
            'brondol' => ['kg' => 500, 'harga' => 16500],
        ])->assertCreated()->json('invoice');

        $this->assertSame('PT Global Sweet Export', $invoice['eksportir']);
        $this->assertSame('13 Agustus 2026', $invoice['tanggal']);
        $this->assertCount(2, $invoice['baris']);
        $this->assertSame('Gula Kristal', $invoice['baris'][0]['jenis']);
        $this->assertEqualsWithDelta(57_250_000.0, $invoice['total'], 0.01);
    }

    public function test_ringkasan_bulan_ini_dipecah_per_jenis(): void
    {
        $this->masukSebagai();
        $eksportir = $this->eksportir();

        $this->postJson('/api/v1/penjualan', [
            'tanggal' => '2026-08-13',
            'eksportirId' => $eksportir->id,
            'kristal' => ['kg' => 2000, 'harga' => 24500],
            'brondol' => ['kg' => 500, 'harga' => 16500],
        ])->assertCreated();

        $ringkasan = $this->getJson('/api/v1/penjualan')->assertOk()->json('ringkasan');

        $this->assertEqualsWithDelta(2000.0, $ringkasan['kgKristal'], 0.01);
        $this->assertEqualsWithDelta(500.0, $ringkasan['kgBrondol'], 0.01);
        $this->assertEqualsWithDelta(49_000_000.0, $ringkasan['rupiahKristal'], 0.01);
        $this->assertEqualsWithDelta(57_250_000.0, $ringkasan['rupiah'], 0.01);
    }
}
