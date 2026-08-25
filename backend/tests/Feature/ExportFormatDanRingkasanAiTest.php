<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\RingkasanKeuanganAgent;
use App\Enums\Grade;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Services\ProduksiService;
use Laravel\Ai\Ai;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExportFormatDanRingkasanAiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMaster();
        $this->travelTo('2026-08-13 09:00:00');
    }

    private function siapkanTransaksi(): void
    {
        $petani = $this->petani('Sukirman');
        $eksportir = $this->eksportir('PT Global Sweet Export');
        $k1 = $this->karyawan('Pardi');
        $k2 = $this->karyawan('Asep Saepudin');

        $this->postJson('/api/v1/pembelian', [
            'tanggal' => '2026-08-11', 'petaniId' => $petani->id, 'grade' => 'NS 1', 'kg' => 100,
        ])->assertCreated();

        $produksi = app(ProduksiService::class);
        $sesi = $produksi->mulai([
            'tanggal' => '2026-08-11', 'grade' => Grade::NS1, 'kg_bahan_mentah' => 100,
            'karyawan_1_id' => $k1->id, 'karyawan_2_id' => $k2->id,
        ]);
        $produksi->selesaikan($sesi, 80, 20);

        $this->postJson('/api/v1/penjualan', [
            'tanggal' => '2026-08-12', 'eksportirId' => $eksportir->id,
            'kristal' => ['kg' => 80, 'harga' => 24500],
            'brondol' => ['kg' => 20, 'harga' => 16500],
        ])->assertCreated();
    }

    // ------------------------------------------------------------ format Excel

    public function test_export_xlsx_menghasilkan_file_excel_yang_bisa_dibuka(): void
    {
        $this->masukSebagai(Role::OWNER);
        $this->siapkanTransaksi();

        $response = $this->get('/api/v1/keuangan/laba-rugi/export?format=xlsx');
        $response->assertOk()
            ->assertDownload('laba-rugi_2026-08-01_sd_2026-08-31.xlsx')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $file = tempnam(sys_get_temp_dir(), 'sigula').'.xlsx';
        file_put_contents($file, $response->streamedContent());

        $sheet = IOFactory::load($file)->getActiveSheet();

        $this->assertSame('PT Nira Sari Murni', $sheet->getCell('A1')->getValue());
        $this->assertSame('LAPORAN LABA RUGI', $sheet->getCell('A2')->getValue());
        $this->assertSame('Keterangan', $sheet->getCell('A6')->getValue());

        // Nilai uang harus tersimpan sebagai ANGKA, bukan teks — kalau teks,
        // rumus SUM di Excel tidak jalan.
        $nilai = $sheet->getCell('B7')->getValue();
        $this->assertIsFloat($nilai);
        $this->assertEqualsWithDelta(2_290_000.0, $nilai, 0.01);

        unlink($file);
    }

    public function test_xlsx_menjaga_teks_tetap_teks(): void
    {
        $this->masukSebagai(Role::OWNER);
        $this->siapkanTransaksi();

        $response = $this->get('/api/v1/produksi/sesi/export?format=xlsx')->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'sigula').'.xlsx';
        file_put_contents($file, $response->streamedContent());

        $sheet = IOFactory::load($file)->getActiveSheet();
        // formatData=false supaya nilai asli sel (float) terbaca, bukan hasil format.
        $isi = $sheet->toArray(null, true, false);

        $barisSesi = collect($isi)->first(fn ($r) => ($r[1] ?? null) === 'TGK-01');

        $this->assertNotNull($barisSesi, 'Baris sesi tungku tidak ditemukan.');
        $this->assertSame('NS 1', $barisSesi[2]);
        $this->assertSame('Pardi', $barisSesi[4]);
        $this->assertIsFloat($barisSesi[3]);         // kg bahan = angka
        $this->assertSame('Selesai', $barisSesi[9]);

        unlink($file);
    }

    // -------------------------------------------------------------- format PDF

    public function test_export_pdf_menghasilkan_berkas_pdf(): void
    {
        $this->masukSebagai(Role::OWNER);
        $this->siapkanTransaksi();

        $response = $this->get('/api/v1/keuangan/laba-rugi/export?format=pdf');
        $response->assertOk()
            ->assertDownload('laba-rugi_2026-08-01_sd_2026-08-31.pdf')
            ->assertHeader('Content-Type', 'application/pdf');

        $isi = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $isi, 'Berkas harus diawali magic bytes PDF.');
        $this->assertGreaterThan(1000, strlen($isi));
    }

    public function test_pdf_bisa_dipakai_untuk_laporan_daftar(): void
    {
        $this->masukSebagai(Role::OWNER);
        $this->siapkanTransaksi();

        $isi = $this->get('/api/v1/pembelian/export?format=pdf')->assertOk()->getContent();

        $this->assertStringStartsWith('%PDF-', $isi);
    }

    // ------------------------------------------------------------- validasi

    public function test_format_tidak_dikenal_ditolak(): void
    {
        $this->masukSebagai(Role::OWNER);

        $this->getJson('/api/v1/keuangan/laba-rugi/export?format=docx')
            ->assertStatus(422)
            ->assertJsonValidationErrors('format');
    }

    public function test_tanpa_parameter_format_tetap_csv(): void
    {
        $this->masukSebagai(Role::OWNER);

        $this->get('/api/v1/keuangan/laba-rugi/export')
            ->assertOk()
            ->assertDownload('laba-rugi_2026-08-01_sd_2026-08-31.csv');
    }

    public function test_format_tercatat_di_audit_log(): void
    {
        $this->masukSebagai(Role::OWNER);

        $this->get('/api/v1/pembelian/export?format=xlsx')->assertOk();

        $log = AuditLog::query()->where('aksi', 'laporan.export')->latest('id')->firstOrFail();
        $this->assertSame('xlsx', $log->data['format']);
    }

    // -------------------------------------------------------- ringkasan AI

    public function test_ringkasan_ai_mengembalikan_teks_dan_angka_pendukung(): void
    {
        config(['ai.providers.anthropic.key' => 'kunci-uji']);
        $this->masukSebagai(Role::OWNER);
        $this->siapkanTransaksi();

        Ai::fakeAgent(RingkasanKeuanganAgent::class, [
            'Periode ini laba bersih Rp 222.000 dengan margin 9,69%.',
        ]);

        $data = $this->getJson('/api/v1/keuangan/ringkasan-ai')->assertOk()->json('data');

        $this->assertStringContainsString('laba bersih', $data['ringkasan']);
        $this->assertSame('claude-opus-5', $data['model']);
        $this->assertFalse($data['dariCache']);
        $this->assertEqualsWithDelta(2_290_000.0, $data['angka']['labaRugi']['pendapatan'], 0.01);
        $this->assertCount(6, $data['angka']['tren']);
    }

    /** Model tidak boleh menghitung sendiri — angka dikirim sebagai fakta di prompt. */
    public function test_prompt_berisi_angka_nyata_dari_transaksi(): void
    {
        config(['ai.providers.anthropic.key' => 'kunci-uji']);
        $this->masukSebagai(Role::OWNER);
        $this->siapkanTransaksi();

        Ai::fakeAgent(RingkasanKeuanganAgent::class, ['ringkasan uji']);

        $this->getJson('/api/v1/keuangan/ringkasan-ai')->assertOk();

        Ai::assertAgentWasPrompted(RingkasanKeuanganAgent::class, function ($prompt): bool {
            return str_contains($prompt->prompt, 'Pendapatan penjualan: Rp 2.290.000')
                && str_contains($prompt->prompt, 'Laba bersih: Rp 722.000')
                && str_contains($prompt->prompt, 'TREN 6 BULAN TERAKHIR')
                && str_contains($prompt->prompt, 'POSISI STOK SAAT INI');
        });
    }

    public function test_hasil_ringkasan_dicache_agar_tidak_memanggil_model_berulang(): void
    {
        config(['ai.providers.anthropic.key' => 'kunci-uji']);
        $this->masukSebagai(Role::OWNER);
        $this->siapkanTransaksi();

        Ai::fakeAgent(RingkasanKeuanganAgent::class, ['ringkasan uji']);

        $this->getJson('/api/v1/keuangan/ringkasan-ai')->assertOk()->assertJsonPath('data.dariCache', false);
        $this->getJson('/api/v1/keuangan/ringkasan-ai')->assertOk()->assertJsonPath('data.dariCache', true);

        Ai::assertAgentWasPromptedTimes(RingkasanKeuanganAgent::class, 1);
    }

    public function test_tanpa_kunci_api_memberi_pesan_yang_jelas(): void
    {
        config(['ai.providers.anthropic.key' => null]);
        $this->masukSebagai(Role::OWNER);

        $this->getJson('/api/v1/keuangan/ringkasan-ai')
            ->assertStatus(503)
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'ANTHROPIC_API_KEY'));
    }

    public function test_ringkasan_ai_hanya_untuk_role_yang_boleh_lihat_keuangan(): void
    {
        config(['ai.providers.anthropic.key' => 'kunci-uji']);

        $this->masukSebagai(Role::STAFF_GUDANG);
        $this->getJson('/api/v1/keuangan/ringkasan-ai')->assertStatus(403);

        $this->masukSebagai(Role::STAFF_PRODUKSI);
        $this->getJson('/api/v1/keuangan/ringkasan-ai')->assertStatus(403);
    }

    public function test_ringkasan_ai_tercatat_di_audit_log(): void
    {
        config(['ai.providers.anthropic.key' => 'kunci-uji']);
        $user = $this->masukSebagai(Role::OWNER);
        $this->siapkanTransaksi();

        Ai::fakeAgent(RingkasanKeuanganAgent::class, ['ringkasan uji']);
        $this->getJson('/api/v1/keuangan/ringkasan-ai')->assertOk();

        $log = AuditLog::query()->where('aksi', 'laporan.ringkasan_ai')->latest('id')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
    }

    // ---------------------------------------------------- ganti provider AI

    /**
     * Fitur dibangun di atas Laravel AI SDK, jadi berpindah provider hanya soal
     * konfigurasi — tidak ada kode yang perlu diubah.
     */
    public function test_bisa_berpindah_ke_gemini_tanpa_mengubah_kode(): void
    {
        config([
            'ai.default' => 'gemini',
            'ai.providers.gemini.key' => 'kunci-gemini-uji',
            'ai.providers.gemini.models.text.default' => 'gemini-3.7-flash',
        ]);

        $this->masukSebagai(Role::OWNER);
        $this->siapkanTransaksi();

        Ai::fakeAgent(RingkasanKeuanganAgent::class, ['Ringkasan versi Gemini.']);

        $data = $this->getJson('/api/v1/keuangan/ringkasan-ai')->assertOk()->json('data');

        $this->assertSame('Ringkasan versi Gemini.', $data['ringkasan']);
        $this->assertSame('gemini-3.7-flash', $data['model']);

        // Prompt yang dikirim tetap berisi angka nyata dari transaksi.
        Ai::assertAgentWasPrompted(
            RingkasanKeuanganAgent::class,
            fn ($prompt): bool => str_contains($prompt->prompt, 'Pendapatan penjualan: Rp 2.290.000'),
        );
    }

    public function test_pesan_kunci_kosong_menyebut_env_provider_yang_aktif(): void
    {
        config(['ai.default' => 'gemini', 'ai.providers.gemini.key' => null]);
        $this->masukSebagai(Role::OWNER);

        $this->getJson('/api/v1/keuangan/ringkasan-ai')
            ->assertStatus(503)
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'GEMINI_API_KEY'));
    }
}
