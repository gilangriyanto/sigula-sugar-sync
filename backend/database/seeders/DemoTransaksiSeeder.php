<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Grade;
use App\Enums\JenisProduk;
use App\Enums\KategoriBiaya;
use App\Enums\KategoriStok;
use App\Enums\StatusSesi;
use App\Exceptions\BusinessRuleException;
use App\Models\BiayaOperasional;
use App\Models\Eksportir;
use App\Models\Karyawan;
use App\Models\Petani;
use App\Models\SesiTungku;
use App\Services\PembelianService;
use App\Services\PenggajianService;
use App\Services\PenjualanService;
use App\Services\ProduksiService;
use App\Services\StokService;
use App\Support\Periode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data transaksi demo ±6 bulan supaya seluruh grafik dan laporan langsung
 * terisi. Semua transaksi dibuat lewat service yang sama dengan API, jadi
 * saldo stok, kartu stok, porsi karyawan, dan laporan dijamin konsisten.
 */
class DemoTransaksiSeeder extends Seeder
{
    private const HARI_HISTORI = 180;

    private const HARGA_JUAL = [
        'kristal' => 24500,
        'brondol' => 16500,
    ];

    public function __construct(
        private readonly StokService $stok,
        private readonly PembelianService $pembelian,
        private readonly ProduksiService $produksi,
        private readonly PenjualanService $penjualan,
        private readonly PenggajianService $penggajian,
    ) {}

    public function run(): void
    {
        if (SesiTungku::query()->exists()) {
            $this->command?->warn('Data transaksi demo sudah ada, seeder dilewati.');

            return;
        }

        mt_srand(20260813);

        $petani = Petani::query()->get();
        $karyawan = Karyawan::query()->get();
        $eksportir = Eksportir::query()->get();

        if ($petani->isEmpty() || $karyawan->count() < 2 || $eksportir->isEmpty()) {
            $this->command?->error('Master data belum lengkap. Jalankan MasterSeeder terlebih dahulu.');

            return;
        }

        $hariIni = CarbonImmutable::today();

        // Satu transaksi besar: jauh lebih cepat dan tetap konsisten karena
        // service di dalamnya memakai savepoint.
        DB::transaction(function () use ($petani, $karyawan, $eksportir, $hariIni): void {
            $this->saldoAwal($hariIni->subDays(self::HARI_HISTORI + 1));

            for ($mundur = self::HARI_HISTORI; $mundur >= 0; $mundur--) {
                $tanggal = $hariIni->subDays($mundur);

                if ($tanggal->isSunday()) {
                    continue;
                }

                $this->pembelianHarian($tanggal, $petani);
                $this->produksiHarian($tanggal, $karyawan, $mundur === 0);

                if ($mundur % 4 === 2) {
                    $this->penjualanHarian($tanggal, $eksportir);
                }

                if ($mundur % 9 === 0) {
                    $this->biayaOperasional($tanggal);
                }
            }
        });

        $this->bayarGajiMingguLalu($hariIni);

        $this->command?->info(sprintf(
            'Demo terisi: %d sesi tungku, %d di antaranya masih berjalan hari ini.',
            SesiTungku::query()->count(),
            SesiTungku::query()->where('status', StatusSesi::SEDANG_DIPROSES->value)->count(),
        ));
    }

    private function saldoAwal(CarbonImmutable $tanggal): void
    {
        $saldo = [
            KategoriStok::NS1->value => 3200,
            KategoriStok::NS2->value => 2400,
            KategoriStok::KECAP->value => 1500,
            KategoriStok::KRISTAL->value => 1800,
            KategoriStok::BRONDOL->value => 520,
        ];

        foreach ($saldo as $kategori => $jumlah) {
            $this->stok->masuk(KategoriStok::from($kategori), (float) $jumlah, $tanggal, 'Saldo awal sistem');
        }
    }

    /**
     * Volume pembelian sengaja diseimbangkan dengan konsumsi produksi harian
     * (±1,4 ton/hari) supaya stok tidak menumpuk tidak wajar sepanjang demo.
     *
     * @param  Collection<int, Petani>  $petani
     */
    private function pembelianHarian(CarbonImmutable $tanggal, $petani): void
    {
        $jumlah = mt_rand(3, 5);

        for ($i = 0; $i < $jumlah; $i++) {
            // Beli grade yang stoknya paling menipis supaya produksi tidak macet.
            $grade = $this->gradeStokTerendah();

            $this->pembelian->simpan([
                'tanggal' => $tanggal->toDateString(),
                'petani_id' => $petani->random()->id,
                'grade' => $grade,
                'kilogram' => (float) mt_rand(300, 420),
                'harga_per_kg' => null,
            ]);
        }
    }

    /**
     * Skala mengikuti kondisi riil client: belasan tungku paralel per hari,
     * masing-masing 2 orang, total ±1,5 ton bahan mentah per hari.
     *
     * @param  Collection<int, Karyawan>  $karyawan
     */
    private function produksiHarian(CarbonImmutable $tanggal, $karyawan, bool $hariIni): void
    {
        $jumlahTungku = min(mt_rand(10, 14), intdiv($karyawan->count(), 2));
        $urutan = $karyawan->shuffle()->values();

        for ($t = 0; $t < $jumlahTungku; $t++) {
            $grade = $this->gradeStokTertinggi();
            $kgBahan = (float) mt_rand(80, 160);

            if ($grade === null || $this->tersedia($grade) < $kgBahan) {
                continue;
            }

            $karyawan1 = $urutan[($t * 2) % $urutan->count()];
            $karyawan2 = $urutan[($t * 2 + 1) % $urutan->count()];

            if ($karyawan1->id === $karyawan2->id) {
                continue;
            }

            $sesi = $this->produksi->mulai([
                'tanggal' => $tanggal->toDateString(),
                'kode_tungku' => sprintf('TGK-%02d', $t + 1),
                'grade' => $grade,
                'kg_bahan_mentah' => $kgBahan,
                'karyawan_1_id' => $karyawan1->id,
                'karyawan_2_id' => $karyawan2->id,
            ]);

            // Beberapa tungku hari ini sengaja dibiarkan "Sedang Diproses".
            if ($hariIni && $t >= $jumlahTungku - 4) {
                continue;
            }

            $totalHasil = round($kgBahan * (0.90 + mt_rand(0, 60) / 1000), 2);
            $kgBrondol = round($totalHasil * (0.12 + mt_rand(0, 100) / 1000), 2);
            $kgKristal = round($totalHasil - $kgBrondol, 2);

            $this->produksi->selesaikan($sesi, $kgKristal, $kgBrondol);
        }
    }

    /** @param Collection<int, Eksportir> $eksportir */
    private function penjualanHarian(CarbonImmutable $tanggal, $eksportir): void
    {
        $stokKristal = $this->stok->saldo(KategoriStok::KRISTAL);
        $stokBrondol = $this->stok->saldo(KategoriStok::BRONDOL);

        $items = [];

        // Eksportir mengambil sebagian besar stok yang siap (55-75%), sisanya
        // ditahan sebagai buffer gudang.
        $kgKristal = round($stokKristal * (0.55 + mt_rand(0, 20) / 100), 2);
        $kgBrondol = round($stokBrondol * (0.55 + mt_rand(0, 20) / 100), 2);

        if ($kgKristal >= 100) {
            $items[] = [
                'jenis' => JenisProduk::KRISTAL,
                'kilogram' => $kgKristal,
                'harga_per_kg' => (float) self::HARGA_JUAL['kristal'] - ($tanggal->diffInDays(CarbonImmutable::today()) > 90 ? 900 : 0),
            ];
        }

        if ($kgBrondol >= 50) {
            $items[] = [
                'jenis' => JenisProduk::BRONDOL,
                'kilogram' => $kgBrondol,
                'harga_per_kg' => (float) self::HARGA_JUAL['brondol'] - ($tanggal->diffInDays(CarbonImmutable::today()) > 90 ? 700 : 0),
            ];
        }

        if ($items === []) {
            return;
        }

        $this->penjualan->simpan([
            'tanggal' => $tanggal->toDateString(),
            'eksportir_id' => $eksportir->random()->id,
            'items' => $items,
        ]);
    }

    private function biayaOperasional(CarbonImmutable $tanggal): void
    {
        $kategori = [KategoriBiaya::LISTRIK, KategoriBiaya::TRANSPORT, KategoriBiaya::SEWA, KategoriBiaya::LAINNYA];
        $pilih = $kategori[array_rand($kategori)];

        BiayaOperasional::create([
            'tanggal' => $tanggal->toDateString(),
            'keterangan' => match ($pilih) {
                KategoriBiaya::LISTRIK => 'Tagihan listrik pabrik',
                KategoriBiaya::TRANSPORT => 'BBM & transport pengiriman',
                KategoriBiaya::SEWA => 'Sewa gudang penyimpanan',
                KategoriBiaya::LAINNYA => 'Perawatan tungku & peralatan',
            },
            'kategori' => $pilih->value,
            'jumlah' => mt_rand(15, 90) * 100000,
        ]);
    }

    /** Menandai gaji 2 minggu sebelum minggu berjalan sebagai sudah dibayar. */
    private function bayarGajiMingguLalu(CarbonImmutable $hariIni): void
    {
        foreach ([2, 3] as $mingguKeBelakang) {
            try {
                $this->penggajian->bayarSemua(
                    Periode::senin($hariIni)->subWeeks($mingguKeBelakang)->toDateString()
                );
            } catch (BusinessRuleException) {
                // Tidak ada produksi pada minggu itu — aman untuk dilewati.
            }
        }
    }

    private function tersedia(Grade $grade): float
    {
        $komitmen = (float) SesiTungku::query()
            ->where('grade', $grade->value)
            ->where('status', StatusSesi::SEDANG_DIPROSES->value)
            ->sum('kg_bahan_mentah');

        return $this->stok->saldo($grade->kategoriStok()) - $komitmen;
    }

    private function gradeStokTertinggi(): ?Grade
    {
        $terpilih = null;
        $tertinggi = 0.0;

        foreach (Grade::cases() as $grade) {
            $tersedia = $this->tersedia($grade);

            if ($tersedia > $tertinggi) {
                $tertinggi = $tersedia;
                $terpilih = $grade;
            }
        }

        return $terpilih;
    }

    private function gradeStokTerendah(): Grade
    {
        $terpilih = Grade::NS1;
        $terendah = PHP_FLOAT_MAX;

        foreach (Grade::cases() as $grade) {
            $tersedia = $this->tersedia($grade);

            if ($tersedia < $terendah) {
                $terendah = $tersedia;
                $terpilih = $grade;
            }
        }

        return $terpilih;
    }
}
