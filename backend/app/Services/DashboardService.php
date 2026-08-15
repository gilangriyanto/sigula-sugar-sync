<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\KategoriStok;
use App\Enums\StatusSesi;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\SesiTungku;
use App\Models\User;
use App\Support\Periode;

/** Ringkasan operasional untuk halaman Dashboard. */
final class DashboardService
{
    public function __construct(
        private readonly StokService $stok,
        private readonly ProduksiService $produksi,
        private readonly LaporanService $laporan,
    ) {}

    /**
     * Blok keuangan hanya disertakan untuk role yang berhak melihatnya
     * (staff gudang & staff produksi tidak boleh melihat angka laba).
     *
     * @return array<string, mixed>
     */
    public function ringkasan(?User $user = null): array
    {
        $hariIni = Periode::tanggal();
        $bulan = Periode::bulan($hariIni);
        $saldo = $this->stok->semuaSaldo();

        $data = [
            'tanggal' => $hariIni->toDateString(),
            'stok' => [
                'bahanMentah' => [
                    'NS 1' => $saldo[KategoriStok::NS1->value],
                    'NS 2' => $saldo[KategoriStok::NS2->value],
                    'Kecap' => $saldo[KategoriStok::KECAP->value],
                    'total' => round(
                        $saldo[KategoriStok::NS1->value] + $saldo[KategoriStok::NS2->value] + $saldo[KategoriStok::KECAP->value],
                        2
                    ),
                ],
                'kristal' => $saldo[KategoriStok::KRISTAL->value],
                'brondol' => $saldo[KategoriStok::BRONDOL->value],
            ],
            'produksiHariIni' => $this->produksi->ringkasanHarian($hariIni->toDateString()),
            'rendemenBulanIni' => $this->rendemenBulanIni(),
        ];

        if ($user === null || $user->punyaAkses('lihat-keuangan')) {
            $labaRugi = $this->laporan->labaRugi($bulan['awal']->toDateString(), $bulan['akhir']->toDateString());

            $data['keuangan'] = [
                'pendapatanBulanIni' => $labaRugi['pendapatan'],
                'labaBulanIni' => $labaRugi['labaBersih'],
                'hppBulanIni' => $labaRugi['hpp']['total'],
                'biayaOperasionalBulanIni' => $labaRugi['biayaOperasional'],
                'margin' => $labaRugi['margin'],
            ];
            $data['tren'] = $this->laporan->trenBulanan(6, $hariIni->toDateString());
        }

        $data['aktivitasTerbaru'] = $this->aktivitasTerbaru($user);

        return $data;
    }

    private function rendemenBulanIni(): float
    {
        $bulan = Periode::bulan();

        $agregat = SesiTungku::query()
            ->selesai()
            ->whereBetween('tanggal', [$bulan['awal']->toDateString(), $bulan['akhir']->toDateString()])
            ->selectRaw('COALESCE(SUM(kg_bahan_mentah), 0) as bahan')
            ->selectRaw('COALESCE(SUM(kg_kristal_total), 0) as kristal')
            ->selectRaw('COALESCE(SUM(kg_brondol_total), 0) as brondol')
            ->first();

        $bahan = (float) ($agregat->bahan ?? 0);

        if ($bahan <= 0) {
            return 0.0;
        }

        return round(((float) $agregat->kristal + (float) $agregat->brondol) / $bahan * 100, 2);
    }

    /**
     * Lima transaksi terakhir lintas modul yang boleh dilihat role tersebut.
     *
     * @return array<int, array{id: string, tanggal: string, modul: string, keterangan: string, nilai: string|null}>
     */
    private function aktivitasTerbaru(?User $user, int $limit = 5): array
    {
        $boleh = static fn (string $ability): bool => $user === null || $user->punyaAkses($ability);
        $items = [];

        if ($boleh('lihat-pembelian')) {
            foreach (Pembelian::query()->with('petani')->latest('tanggal')->latest('id')->limit($limit)->get() as $row) {
                $items[] = [
                    'id' => 'beli-'.$row->id,
                    'tanggal' => $row->tanggal->toDateString(),
                    'urutan' => $row->id,
                    'modul' => 'Pembelian',
                    'keterangan' => sprintf('%s — %s %s kg', $row->petani?->nama ?? '-', $row->grade->label(), $this->angka((float) $row->kilogram)),
                    'nilai' => 'Rp '.number_format((float) $row->total, 0, ',', '.'),
                ];
            }
        }

        if ($boleh('lihat-penjualan')) {
            foreach (Penjualan::query()->with('eksportir')->latest('tanggal')->latest('id')->limit($limit)->get() as $row) {
                $items[] = [
                    'id' => 'jual-'.$row->id,
                    'tanggal' => $row->tanggal->toDateString(),
                    'urutan' => $row->id,
                    'modul' => 'Penjualan',
                    'keterangan' => sprintf('%s — %s', $row->eksportir?->nama ?? '-', $row->nomor_invoice),
                    'nilai' => 'Rp '.number_format((float) $row->total, 0, ',', '.'),
                ];
            }
        }

        if ($boleh('lihat-produksi')) {
            foreach (SesiTungku::query()->latest('tanggal')->latest('id')->limit($limit)->get() as $row) {
                $items[] = [
                    'id' => 'sesi-'.$row->id,
                    'tanggal' => $row->tanggal->toDateString(),
                    'urutan' => $row->id,
                    'modul' => 'Produksi',
                    'keterangan' => sprintf(
                        '%s — %s %s kg (%s)',
                        $row->kode_tungku,
                        $row->grade->label(),
                        $this->angka((float) $row->kg_bahan_mentah),
                        $row->status->label(),
                    ),
                    'nilai' => $row->status === StatusSesi::SELESAI
                        ? $this->angka((float) $row->kg_kristal_total).' kg kristal'
                        : null,
                ];
            }
        }

        usort($items, static function (array $a, array $b): int {
            return [$b['tanggal'], $b['urutan']] <=> [$a['tanggal'], $a['urutan']];
        });

        return array_map(
            static fn (array $item): array => collect($item)->except('urutan')->all(),
            array_slice($items, 0, $limit)
        );
    }

    private function angka(float $nilai): string
    {
        return number_format($nilai, fmod($nilai, 1.0) === 0.0 ? 0 : 1, ',', '.');
    }
}
