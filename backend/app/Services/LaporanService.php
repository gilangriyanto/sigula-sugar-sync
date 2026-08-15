<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BiayaOperasional;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Support\Periode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Laporan keuangan.
 *
 * Pendapatan        = total penjualan ke eksportir
 * HPP               = pembelian bahan + total gaji karyawan (termasuk uang makan)
 * Biaya Operasional = input manual biaya lain-lain
 * Laba Bersih       = Pendapatan − HPP − Biaya Operasional
 *
 * Seluruh angka diturunkan dari transaksi riil, tidak ada nilai hardcode.
 */
final class LaporanService
{
    public function __construct(private readonly PenggajianService $penggajian) {}

    /**
     * @return array{
     *     periode: array{dari: string, sampai: string},
     *     pendapatan: float,
     *     hpp: array{bahan: float, gaji: array{upahKristal: float, upahBrondol: float, uangMakan: float, total: float}, total: float},
     *     biayaOperasional: float,
     *     labaBersih: float,
     *     margin: float
     * }
     */
    public function labaRugi(string $dari, string $sampai): array
    {
        $pendapatan = (float) Penjualan::query()->whereBetween('tanggal', [$dari, $sampai])->sum('total');
        $bahan = (float) Pembelian::query()->whereBetween('tanggal', [$dari, $sampai])->sum('total');
        $gaji = $this->penggajian->totalGajiPeriode($dari, $sampai);
        $biaya = (float) BiayaOperasional::query()->whereBetween('tanggal', [$dari, $sampai])->sum('jumlah');

        $hpp = round($bahan + $gaji['total'], 2);
        $laba = round($pendapatan - $hpp - $biaya, 2);

        return [
            'periode' => ['dari' => $dari, 'sampai' => $sampai],
            'pendapatan' => round($pendapatan, 2),
            'hpp' => [
                'bahan' => round($bahan, 2),
                'gaji' => $gaji,
                'total' => $hpp,
            ],
            'biayaOperasional' => round($biaya, 2),
            'labaBersih' => $laba,
            'margin' => $pendapatan > 0 ? round($laba / $pendapatan * 100, 2) : 0.0,
        ];
    }

    /**
     * Tren bulanan untuk grafik (default 6 bulan terakhir).
     *
     * @return array<int, array{
     *     bulan: string, label: string, pendapatan: float, pembelian: float,
     *     gaji: float, biayaOperasional: float, totalBiaya: float, laba: float, margin: float
     * }>
     */
    public function trenBulanan(int $jumlahBulan = 6, ?string $sampaiBulan = null): array
    {
        $jumlahBulan = max(1, min($jumlahBulan, 36));
        $kunciBulan = Periode::bulanTerakhir($jumlahBulan, $sampaiBulan);

        $awal = CarbonImmutable::parse($kunciBulan[0].'-01')->startOfMonth()->toDateString();
        $akhir = CarbonImmutable::parse(end($kunciBulan).'-01')->endOfMonth()->toDateString();

        $penjualan = $this->totalPerBulan(
            Penjualan::query()->whereBetween('tanggal', [$awal, $akhir])->get(['tanggal', 'total']),
            'total'
        );
        $pembelian = $this->totalPerBulan(
            Pembelian::query()->whereBetween('tanggal', [$awal, $akhir])->get(['tanggal', 'total']),
            'total'
        );
        $biaya = $this->totalPerBulan(
            BiayaOperasional::query()->whereBetween('tanggal', [$awal, $akhir])->get(['tanggal', 'jumlah']),
            'jumlah'
        );
        $gaji = $this->penggajian->totalGajiPerBulan($awal, $akhir);

        $hasil = [];

        foreach ($kunciBulan as $bulan) {
            $pendapatanBulan = round($penjualan[$bulan] ?? 0.0, 2);
            $pembelianBulan = round($pembelian[$bulan] ?? 0.0, 2);
            $gajiBulan = round($gaji[$bulan]['total'] ?? 0.0, 2);
            $biayaBulan = round($biaya[$bulan] ?? 0.0, 2);
            $totalBiaya = round($pembelianBulan + $gajiBulan + $biayaBulan, 2);
            $laba = round($pendapatanBulan - $totalBiaya, 2);

            $hasil[] = [
                'bulan' => $bulan,
                'label' => Periode::labelBulanSingkat($bulan),
                'pendapatan' => $pendapatanBulan,
                'pembelian' => $pembelianBulan,
                'gaji' => $gajiBulan,
                'biayaOperasional' => $biayaBulan,
                'totalBiaya' => $totalBiaya,
                'laba' => $laba,
                'margin' => $pendapatanBulan > 0 ? round($laba / $pendapatanBulan * 100, 1) : 0.0,
            ];
        }

        return $hasil;
    }

    /**
     * @param  Collection<int, Model>  $rows
     * @return array<string, float>
     */
    private function totalPerBulan($rows, string $kolom): array
    {
        $hasil = [];

        foreach ($rows as $row) {
            $bulan = $row->tanggal->format('Y-m');
            $hasil[$bulan] = ($hasil[$bulan] ?? 0.0) + (float) $row->{$kolom};
        }

        return $hasil;
    }
}
