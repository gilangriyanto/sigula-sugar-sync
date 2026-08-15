<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\JenisMutasi;
use App\Enums\KategoriStok;
use App\Exceptions\BusinessRuleException;
use App\Models\KartuStok;
use App\Models\StokSaldo;
use App\Models\User;
use App\Support\Periode;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Satu-satunya pintu masuk perubahan stok.
 *
 * Pola yang dipakai: saldo berjalan disimpan di stok_saldo (dikunci saat
 * diubah) dan setiap pergerakan dicatat di kartu_stok sebagai log append-only
 * lengkap dengan saldo_setelah untuk keperluan audit & rekonsiliasi.
 */
final class StokService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function saldo(KategoriStok $kategori): float
    {
        return (float) (StokSaldo::query()->where('kategori', $kategori->value)->value('saldo_kg') ?? 0.0);
    }

    /**
     * Saldo seluruh kategori, selalu berisi 5 kategori walau belum ada mutasi.
     *
     * @return array<string, float> dikunci nilai enum, contoh ['ns1' => 1200.0, ...]
     */
    public function semuaSaldo(): array
    {
        $tersimpan = StokSaldo::query()->pluck('saldo_kg', 'kategori');
        $hasil = [];

        foreach (KategoriStok::cases() as $kategori) {
            $hasil[$kategori->value] = (float) ($tersimpan[$kategori->value] ?? 0.0);
        }

        return $hasil;
    }

    public function masuk(
        KategoriStok $kategori,
        float $jumlah,
        CarbonInterface|string $tanggal,
        string $keterangan,
        ?Model $referensi = null,
        ?User $user = null,
    ): KartuStok {
        return $this->catat(JenisMutasi::MASUK, $kategori, $jumlah, $tanggal, $keterangan, $referensi, $user);
    }

    public function keluar(
        KategoriStok $kategori,
        float $jumlah,
        CarbonInterface|string $tanggal,
        string $keterangan,
        ?Model $referensi = null,
        ?User $user = null,
    ): KartuStok {
        return $this->catat(JenisMutasi::KELUAR, $kategori, $jumlah, $tanggal, $keterangan, $referensi, $user);
    }

    /**
     * Koreksi manual hasil hitung fisik gudang. Selisih nol tidak menghasilkan
     * mutasi apa pun; selisih apa pun selalu tercatat di kartu stok (tidak ada
     * jalur diam-diam mengubah saldo).
     */
    public function opname(
        KategoriStok $kategori,
        float $stokFisik,
        string $alasan,
        CarbonInterface|string|null $tanggal = null,
        ?User $user = null,
    ): ?KartuStok {
        if ($stokFisik < 0) {
            throw BusinessRuleException::untukField('stokFisik', 'Jumlah stok fisik tidak boleh negatif.');
        }

        $tanggal ??= Periode::tanggal();

        return DB::transaction(function () use ($kategori, $stokFisik, $alasan, $tanggal, $user): ?KartuStok {
            $saldo = $this->kunciSaldo($kategori);
            $selisih = round($stokFisik - (float) $saldo->saldo_kg, 2);

            if (abs($selisih) < 0.005) {
                throw BusinessRuleException::untukField(
                    'stokFisik',
                    sprintf('Tidak ada selisih dengan stok sistem (%s kg).', rtrim(rtrim(number_format((float) $saldo->saldo_kg, 2, '.', ''), '0'), '.'))
                );
            }

            $mutasi = $this->catat(
                $selisih > 0 ? JenisMutasi::MASUK : JenisMutasi::KELUAR,
                $kategori,
                abs($selisih),
                $tanggal,
                'Stok opname: '.$alasan,
                null,
                $user,
            );

            $this->audit->catat(
                'stok.opname',
                sprintf('Stok opname %s: koreksi %s kg', $kategori->label(), ($selisih > 0 ? '+' : '−').abs($selisih)),
                $mutasi,
                [
                    'kategori' => $kategori->value,
                    'saldo_sistem' => (float) $saldo->saldo_kg,
                    'stok_fisik' => $stokFisik,
                    'selisih' => $selisih,
                    'alasan' => $alasan,
                ],
                $user,
            );

            return $mutasi;
        });
    }

    /**
     * Inti seluruh mutasi: kunci saldo, validasi agar tidak minus, simpan saldo
     * baru, lalu tulis satu baris kartu stok. Wajib dipanggil dari dalam
     * transaction supaya saldo dan kartu stok tidak pernah beda isi.
     */
    public function catat(
        JenisMutasi $jenis,
        KategoriStok $kategori,
        float $jumlah,
        CarbonInterface|string $tanggal,
        string $keterangan,
        ?Model $referensi = null,
        ?User $user = null,
    ): KartuStok {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Mutasi stok harus dijalankan di dalam database transaction.');
        }

        $jumlah = round($jumlah, 2);

        if ($jumlah <= 0) {
            throw BusinessRuleException::untukField('jumlah', 'Jumlah mutasi stok harus lebih dari 0.');
        }

        $saldo = $this->kunciSaldo($kategori);
        $saldoBaru = round((float) $saldo->saldo_kg + ($jenis->faktor() * $jumlah), 2);

        if ($saldoBaru < 0) {
            throw BusinessRuleException::untukField('kilogram', sprintf(
                'Stok %s tidak mencukupi. Tersedia %s kg, dibutuhkan %s kg.',
                $kategori->label(),
                $this->format((float) $saldo->saldo_kg),
                $this->format($jumlah),
            ));
        }

        $saldo->saldo_kg = $saldoBaru;
        $saldo->save();

        return KartuStok::create([
            'tanggal' => Periode::tanggal($tanggal)->toDateString(),
            'kategori' => $kategori->value,
            'jenis' => $jenis->value,
            'jumlah_kg' => $jumlah,
            'saldo_setelah' => $saldoBaru,
            'keterangan' => $keterangan,
            'referensi_type' => $referensi?->getMorphClass(),
            'referensi_id' => $referensi?->getKey(),
            'user_id' => $user?->getKey(),
        ]);
    }

    private function kunciSaldo(KategoriStok $kategori): StokSaldo
    {
        $saldo = StokSaldo::query()->where('kategori', $kategori->value)->lockForUpdate()->first();

        if ($saldo === null) {
            StokSaldo::query()->insertOrIgnore([
                'kategori' => $kategori->value,
                'saldo_kg' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $saldo = StokSaldo::query()->where('kategori', $kategori->value)->lockForUpdate()->firstOrFail();
        }

        return $saldo;
    }

    private function format(float $nilai): string
    {
        return number_format($nilai, fmod($nilai, 1.0) === 0.0 ? 0 : 2, ',', '.');
    }
}
