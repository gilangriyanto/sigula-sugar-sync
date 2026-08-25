<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\RingkasanKeuanganAgent;
use App\Exceptions\BusinessRuleException;
use App\Support\Periode;
use App\Support\RentangPeriode;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Ai;
use Throwable;

/**
 * Ringkasan naratif laporan yang dihasilkan AI (Laravel AI SDK + Claude).
 *
 * Angka tidak pernah dihitung oleh model: seluruhnya diambil dari
 * LaporanService/ProduksiService lalu dikirim sebagai fakta di dalam prompt.
 * Model hanya menafsirkan dan menyusun kalimat.
 */
final class RingkasanAiService
{
    /** Hasil dicache supaya membuka halaman berulang kali tidak memanggil model terus. */
    private const CACHE_MENIT = 30;

    public function __construct(
        private readonly LaporanService $laporan,
        private readonly ProduksiService $produksi,
        private readonly StokService $stok,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{periode: array{dari: string, sampai: string, label: string}, ringkasan: string, model: string, dariCache: bool, angka: array<string, mixed>}
     */
    public function untukPeriode(string $dari, string $sampai, bool $segarkan = false, ?object $user = null): array
    {
        $this->pastikanTerkonfigurasi();

        $kunci = sprintf('sigula:ringkasan-ai:%s:%s', $dari, $sampai);

        if ($segarkan) {
            Cache::forget($kunci);
        }

        $dariCache = Cache::has($kunci);

        $hasil = Cache::remember($kunci, now()->addMinutes(self::CACHE_MENIT), function () use ($dari, $sampai, $user): array {
            $angka = $this->kumpulkanAngka($dari, $sampai);

            $respons = (new RingkasanKeuanganAgent)->prompt($this->susunPrompt($dari, $sampai, $angka));

            $this->audit->catat(
                'laporan.ringkasan_ai',
                sprintf('Ringkasan AI dibuat untuk periode %s s.d. %s', $dari, $sampai),
                null,
                ['dari' => $dari, 'sampai' => $sampai],
                $user,
            );

            return [
                'ringkasan' => trim($respons->text),
                'model' => $this->modelAktif(),
                'angka' => $angka,
            ];
        });

        return [
            'periode' => [
                'dari' => $dari,
                'sampai' => $sampai,
                'label' => RentangPeriode::label($dari, $sampai),
            ],
            'ringkasan' => $hasil['ringkasan'],
            'model' => $hasil['model'],
            'dariCache' => $dariCache,
            'angka' => $hasil['angka'],
        ];
    }

    /**
     * Fakta yang dikirim ke model — semuanya berasal dari transaksi nyata.
     *
     * @return array<string, mixed>
     */
    private function kumpulkanAngka(string $dari, string $sampai): array
    {
        $labaRugi = $this->laporan->labaRugi($dari, $sampai);
        $tren = $this->laporan->trenBulanan(6, $sampai);
        $saldo = $this->stok->semuaSaldo();

        return [
            'labaRugi' => $labaRugi,
            'tren' => $tren,
            'stok' => $saldo,
            'produksiTerakhir' => $this->produksi->ringkasanHarian($sampai),
        ];
    }

    /** @param array<string, mixed> $angka */
    private function susunPrompt(string $dari, string $sampai, array $angka): string
    {
        $lr = $angka['labaRugi'];
        $gaji = $lr['hpp']['gaji'];
        $stok = $angka['stok'];
        $produksi = $angka['produksiTerakhir'];

        $rupiah = static fn (float $v): string => 'Rp '.number_format($v, 0, ',', '.');
        $kg = static fn (float $v): string => number_format($v, 2, ',', '.').' kg';

        $barisTren = [];
        foreach ($angka['tren'] as $bulan) {
            $barisTren[] = sprintf(
                '- %s: pendapatan %s, pembelian bahan %s, gaji %s, biaya operasional %s, laba %s, margin %s%%',
                $bulan['label'],
                $rupiah($bulan['pendapatan']),
                $rupiah($bulan['pembelian']),
                $rupiah($bulan['gaji']),
                $rupiah($bulan['biayaOperasional']),
                $rupiah($bulan['laba']),
                number_format($bulan['margin'], 1, ',', '.'),
            );
        }

        return implode("\n", [
            sprintf('PERIODE LAPORAN: %s', RentangPeriode::label($dari, $sampai)),
            '',
            'LABA RUGI PERIODE INI',
            sprintf('- Pendapatan penjualan: %s', $rupiah($lr['pendapatan'])),
            sprintf('- HPP pembelian bahan baku: %s', $rupiah($lr['hpp']['bahan'])),
            sprintf('- HPP upah gula kristal: %s', $rupiah($gaji['upahKristal'])),
            sprintf('- HPP upah gula brondol: %s', $rupiah($gaji['upahBrondol'])),
            sprintf('- HPP uang makan: %s', $rupiah($gaji['uangMakan'])),
            sprintf('- Total HPP: %s', $rupiah($lr['hpp']['total'])),
            sprintf('- Biaya operasional lain-lain: %s', $rupiah($lr['biayaOperasional'])),
            sprintf('- Laba bersih: %s', $rupiah($lr['labaBersih'])),
            sprintf('- Margin: %s%%', number_format($lr['margin'], 2, ',', '.')),
            '',
            'TREN 6 BULAN TERAKHIR',
            ...$barisTren,
            '',
            'POSISI STOK SAAT INI',
            sprintf('- Bahan mentah NS 1: %s', $kg($stok['ns1'])),
            sprintf('- Bahan mentah NS 2: %s', $kg($stok['ns2'])),
            sprintf('- Bahan mentah Kecap: %s', $kg($stok['kecap'])),
            sprintf('- Produk jadi Gula Kristal: %s', $kg($stok['kristal'])),
            sprintf('- Produk jadi Gula Brondol: %s', $kg($stok['brondol'])),
            '',
            sprintf('PRODUKSI PADA %s', Periode::tanggalIndonesia($sampai)),
            sprintf('- Sesi tungku tercatat: %d (%d masih berjalan)', $produksi['jumlahSesi'], $produksi['tungkuAktif']),
            sprintf('- Bahan mentah dimasak: %s', $kg($produksi['kgBahan'])),
            sprintf('- Hasil: %s gula kristal, %s gula brondol', $kg($produksi['kgKristal']), $kg($produksi['kgBrondol'])),
            sprintf('- Rendemen hari itu: %s', $produksi['rendemen'] === null ? 'belum ada data' : number_format($produksi['rendemen'], 2, ',', '.').'%'),
            '',
            'Buat ringkasan berdasarkan data di atas.',
        ]);
    }

    /**
     * Fitur ini opsional — kalau kunci API belum diisi, kembalikan pesan yang
     * jelas alih-alih membiarkan request gagal dengan error teknis.
     */
    private function pastikanTerkonfigurasi(): void
    {
        $provider = (string) config('ai.default');

        if (blank(config("ai.providers.{$provider}.key"))) {
            throw new BusinessRuleException(
                sprintf(
                    'Fitur ringkasan AI belum aktif. Provider "%s" belum punya API key — isi %s pada .env server lalu jalankan `php artisan config:cache`.',
                    $provider,
                    self::namaEnvKunci($provider),
                ),
                [],
                503,
            );
        }
    }

    /**
     * Model yang benar-benar dipakai provider aktif.
     *
     * Diambil dari provider (bukan konstanta) supaya tetap benar saat AI_PROVIDER
     * diganti ke Gemini, OpenAI, atau provider lain tanpa mengubah kode.
     */
    private function modelAktif(): string
    {
        try {
            return Ai::textProvider()->defaultTextModel();
        } catch (Throwable) {
            return (string) config('ai.default');
        }
    }

    /** Nama variabel .env yang menyimpan kunci API tiap provider. */
    private static function namaEnvKunci(string $provider): string
    {
        return match ($provider) {
            'azure' => 'AZURE_OPENAI_API_KEY',
            'eleven' => 'ELEVENLABS_API_KEY',
            default => strtoupper($provider).'_API_KEY',
        };
    }

    /** Menerjemahkan kegagalan pemanggilan model jadi pesan yang bisa dibaca pengguna. */
    public static function pesanGagal(Throwable $e): string
    {
        return 'Ringkasan AI gagal dibuat: '.$e->getMessage();
    }
}
