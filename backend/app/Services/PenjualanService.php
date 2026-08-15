<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\JenisProduk;
use App\Enums\StatusPembayaran;
use App\Exceptions\BusinessRuleException;
use App\Models\Eksportir;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\User;
use App\Support\Periode;
use Illuminate\Support\Facades\DB;

/**
 * Penjualan ke eksportir.
 *
 * Satu transaksi = satu invoice yang berisi maksimal dua baris (Kristal &
 * Brondol) dengan kilogram dan harga masing-masing — bukan harga rata-rata
 * gabungan, dan bukan dua transaksi terpisah.
 */
final class PenjualanService
{
    public function __construct(
        private readonly StokService $stok,
        private readonly NomorGeneratorService $nomor,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param array{
     *     tanggal: string,
     *     eksportir_id: int|string,
     *     items: array<int, array{jenis: JenisProduk, kilogram: float, harga_per_kg: float}>,
     *     status_pembayaran?: StatusPembayaran|null,
     *     catatan?: string|null
     * } $data
     */
    public function simpan(array $data, ?User $user = null): Penjualan
    {
        $items = $this->normalisasiItem($data['items'] ?? []);

        return DB::transaction(function () use ($data, $items, $user): Penjualan {
            $this->pastikanStokCukup($items);

            $tanggal = Periode::tanggal($data['tanggal']);
            $eksportir = Eksportir::query()->findOrFail($data['eksportir_id']);
            $status = $data['status_pembayaran'] ?? StatusPembayaran::LUNAS;

            $total = round(array_sum(array_column($items, 'subtotal')), 2);

            $penjualan = Penjualan::create([
                'nomor_invoice' => $this->nomor->invoicePenjualan($tanggal),
                'tanggal' => $tanggal->toDateString(),
                'eksportir_id' => $eksportir->id,
                'total' => $total,
                'status_pembayaran' => $status->value,
                'dibayar_pada' => $status === StatusPembayaran::LUNAS ? now() : null,
                'catatan' => $data['catatan'] ?? null,
                'user_id' => $user?->getKey(),
            ]);

            foreach ($items as $item) {
                /** @var JenisProduk $jenis */
                $jenis = $item['jenis'];

                PenjualanItem::create([
                    'penjualan_id' => $penjualan->id,
                    'jenis' => $jenis->value,
                    'kilogram' => $item['kilogram'],
                    'harga_per_kg' => $item['harga_per_kg'],
                    'subtotal' => $item['subtotal'],
                ]);

                $this->stok->keluar(
                    $jenis->kategoriStok(),
                    $item['kilogram'],
                    $tanggal,
                    sprintf('Penjualan ke %s (%s)', $eksportir->nama, $penjualan->nomor_invoice),
                    $penjualan,
                    $user,
                );
            }

            $this->audit->catat(
                'penjualan.simpan',
                sprintf('Penjualan %s ke %s senilai Rp %s', $penjualan->nomor_invoice, $eksportir->nama, number_format($total, 0, ',', '.')),
                $penjualan,
                [
                    'total' => $total,
                    'items' => array_map(static fn (array $i): array => [
                        'jenis' => $i['jenis']->value,
                        'kilogram' => $i['kilogram'],
                        'harga_per_kg' => $i['harga_per_kg'],
                        'subtotal' => $i['subtotal'],
                    ], $items),
                ],
                $user,
            );

            return $penjualan->load(['eksportir', 'items']);
        });
    }

    public function batalkan(Penjualan $penjualan, ?User $user = null, ?string $alasan = null): void
    {
        DB::transaction(function () use ($penjualan, $user, $alasan): void {
            /** @var Penjualan $terkunci */
            $terkunci = Penjualan::query()->whereKey($penjualan->getKey())->lockForUpdate()->firstOrFail();
            $terkunci->load('items');

            foreach ($terkunci->items as $item) {
                $this->stok->masuk(
                    $item->jenis->kategoriStok(),
                    (float) $item->kilogram,
                    $terkunci->tanggal,
                    sprintf('Pembatalan penjualan %s', $terkunci->nomor_invoice),
                    $terkunci,
                    $user,
                );
            }

            $this->audit->catat(
                'penjualan.batal',
                sprintf('Penjualan %s dibatalkan', $terkunci->nomor_invoice),
                $terkunci,
                ['alasan' => $alasan, 'total' => (float) $terkunci->total],
                $user,
            );

            $terkunci->delete();
        });
    }

    public function ubahStatusPembayaran(Penjualan $penjualan, StatusPembayaran $status, ?User $user = null): Penjualan
    {
        return DB::transaction(function () use ($penjualan, $status, $user): Penjualan {
            $penjualan->status_pembayaran = $status;
            $penjualan->dibayar_pada = $status === StatusPembayaran::LUNAS ? now() : null;
            $penjualan->save();

            $this->audit->catat(
                'penjualan.status',
                sprintf('Status pembayaran %s diubah menjadi %s', $penjualan->nomor_invoice, $status->label()),
                $penjualan,
                ['status' => $status->value],
                $user,
            );

            return $penjualan->load(['eksportir', 'items']);
        });
    }

    /**
     * Ringkasan penjualan bulan berjalan, dipecah per jenis produk.
     *
     * @return array{rupiah: float, kgKristal: float, kgBrondol: float, rupiahKristal: float, rupiahBrondol: float, jumlahTransaksi: int}
     */
    public function ringkasanBulanIni(?string $tanggal = null): array
    {
        $bulan = Periode::bulan($tanggal);

        $penjualan = Penjualan::query()
            ->with('items')
            ->whereBetween('tanggal', [$bulan['awal']->toDateString(), $bulan['akhir']->toDateString()])
            ->get();

        $ringkasan = [
            'rupiah' => 0.0,
            'kgKristal' => 0.0,
            'kgBrondol' => 0.0,
            'rupiahKristal' => 0.0,
            'rupiahBrondol' => 0.0,
            'jumlahTransaksi' => $penjualan->count(),
        ];

        foreach ($penjualan as $trx) {
            $ringkasan['rupiah'] += (float) $trx->total;

            foreach ($trx->items as $item) {
                if ($item->jenis === JenisProduk::KRISTAL) {
                    $ringkasan['kgKristal'] += (float) $item->kilogram;
                    $ringkasan['rupiahKristal'] += (float) $item->subtotal;

                    continue;
                }

                $ringkasan['kgBrondol'] += (float) $item->kilogram;
                $ringkasan['rupiahBrondol'] += (float) $item->subtotal;
            }
        }

        return array_map(static fn ($v) => is_float($v) ? round($v, 2) : $v, $ringkasan);
    }

    /**
     * Pengecekan stok per baris sebelum ada perubahan apa pun, supaya pesan
     * error menunjuk field yang benar di form (kristal.kg / brondol.kg).
     * StokService tetap melakukan pengecekan final saat mutasi dijalankan.
     *
     * @param  array<int, array{jenis: JenisProduk, kilogram: float, harga_per_kg: float, subtotal: float}>  $items
     */
    private function pastikanStokCukup(array $items): void
    {
        foreach ($items as $item) {
            /** @var JenisProduk $jenis */
            $jenis = $item['jenis'];
            $tersedia = $this->stok->saldo($jenis->kategoriStok());

            if ($item['kilogram'] > $tersedia) {
                throw BusinessRuleException::untukField($jenis->value.'.kg', sprintf(
                    'Stok %s hanya %s kg, tidak cukup untuk menjual %s kg.',
                    $jenis->label(),
                    number_format($tersedia, fmod($tersedia, 1.0) === 0.0 ? 0 : 2, ',', '.'),
                    number_format($item['kilogram'], fmod($item['kilogram'], 1.0) === 0.0 ? 0 : 2, ',', '.'),
                ));
            }
        }
    }

    /**
     * Validasi & normalisasi baris invoice: minimal satu baris, kg/harga positif,
     * dan tidak boleh ada dua baris untuk jenis yang sama.
     *
     * @param  array<int, array{jenis: JenisProduk|string, kilogram: float|int|string, harga_per_kg: float|int|string}>  $items
     * @return array<int, array{jenis: JenisProduk, kilogram: float, harga_per_kg: float, subtotal: float}>
     */
    private function normalisasiItem(array $items): array
    {
        $hasil = [];

        foreach ($items as $item) {
            $jenis = $item['jenis'] instanceof JenisProduk ? $item['jenis'] : JenisProduk::fromAny($item['jenis']);
            $kilogram = round((float) $item['kilogram'], 2);
            $harga = round((float) $item['harga_per_kg'], 2);

            if ($kilogram <= 0) {
                throw BusinessRuleException::untukField(
                    $jenis->value.'.kg',
                    sprintf('Kilogram %s harus lebih dari 0.', $jenis->label())
                );
            }

            if ($harga <= 0) {
                throw BusinessRuleException::untukField(
                    $jenis->value.'.harga',
                    sprintf('Harga jual %s harus lebih dari 0.', $jenis->label())
                );
            }

            if (isset($hasil[$jenis->value])) {
                throw new BusinessRuleException(sprintf('Baris %s hanya boleh diisi satu kali dalam satu invoice.', $jenis->label()));
            }

            $hasil[$jenis->value] = [
                'jenis' => $jenis,
                'kilogram' => $kilogram,
                'harga_per_kg' => $harga,
                'subtotal' => round($kilogram * $harga, 2),
            ];
        }

        if ($hasil === []) {
            throw new BusinessRuleException('Minimal salah satu baris (Kristal atau Brondol) harus diisi.');
        }

        return array_values($hasil);
    }
}
