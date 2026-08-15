<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\JenisTarif;
use App\Exceptions\BusinessRuleException;
use App\Models\TarifUpah;
use App\Models\User;
use App\Support\Periode;
use App\Support\TarifResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Master tarif upah & uang makan dengan histori (pola sama seperti HargaService).
 */
final class TarifService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function tarifBerlaku(JenisTarif $jenis, CarbonInterface|string|null $pada = null): float
    {
        $waktu = $pada === null ? now() : Periode::tanggal($pada)->endOfDay();

        $nilai = TarifUpah::query()
            ->where('jenis', $jenis->value)
            ->where('berlaku_dari', '<=', $waktu)
            ->orderByDesc('berlaku_dari')
            ->orderByDesc('id')
            ->value('nilai');

        if ($nilai !== null) {
            return (float) $nilai;
        }

        // Belum ada tarif yang berlaku pada tanggal itu: pakai tarif paling awal
        // yang pernah tercatat, atau default perusahaan bila tabel masih kosong.
        $paling_awal = TarifUpah::query()
            ->where('jenis', $jenis->value)
            ->orderBy('berlaku_dari')
            ->orderBy('id')
            ->value('nilai');

        return $paling_awal !== null ? (float) $paling_awal : $jenis->nilaiDefault();
    }

    /**
     * Tarif berjalan dalam bentuk yang dipakai frontend.
     *
     * @return array{kristal: float, brondol: float, uangMakan: float}
     */
    public function tarifSaatIni(): array
    {
        return [
            'kristal' => $this->tarifBerlaku(JenisTarif::KRISTAL),
            'brondol' => $this->tarifBerlaku(JenisTarif::BRONDOL),
            'uangMakan' => $this->tarifBerlaku(JenisTarif::UANG_MAKAN),
        ];
    }

    public function ubahTarif(
        JenisTarif $jenis,
        float $nilai,
        CarbonInterface|string|null $berlakuDari = null,
        ?string $catatan = null,
        ?User $user = null,
    ): TarifUpah {
        if ($nilai <= 0) {
            throw BusinessRuleException::untukField('nilai', 'Nilai tarif harus lebih dari 0.');
        }

        return DB::transaction(function () use ($jenis, $nilai, $berlakuDari, $catatan, $user): TarifUpah {
            $mulai = $berlakuDari === null ? now() : Periode::tanggal($berlakuDari)->setTimeFrom(now());
            $sebelumnya = $this->tarifBerlaku($jenis, $mulai);

            $baru = TarifUpah::create([
                'jenis' => $jenis->value,
                'nilai' => round($nilai, 2),
                'berlaku_dari' => $mulai,
                'catatan' => $catatan,
                'dibuat_oleh' => $user?->getKey(),
            ]);

            $this->audit->catat(
                'tarif.ubah',
                sprintf(
                    '%s diubah dari Rp %s menjadi Rp %s',
                    $jenis->label(),
                    number_format($sebelumnya, 0, ',', '.'),
                    number_format($nilai, 0, ',', '.'),
                ),
                $baru,
                [
                    'jenis' => $jenis->value,
                    'nilai_lama' => $sebelumnya,
                    'nilai_baru' => round($nilai, 2),
                    'berlaku_dari' => $mulai->toDateTimeString(),
                ],
                $user,
            );

            return $baru;
        });
    }

    /**
     * @return Collection<int, array{id: string, jenis: string, tanggal: string, nilaiLama: float|null, nilaiBaru: float}>
     */
    public function riwayat(?JenisTarif $jenis = null, int $limit = 100): Collection
    {
        $rows = TarifUpah::query()
            ->when($jenis !== null, fn ($q) => $q->where('jenis', $jenis->value))
            ->orderBy('jenis')
            ->orderBy('berlaku_dari')
            ->orderBy('id')
            ->get();

        $hasil = collect();
        $terakhir = [];

        foreach ($rows as $row) {
            $kunci = $row->jenis->value;

            $hasil->push([
                'id' => (string) $row->id,
                'jenis' => $row->jenis->value,
                'jenisLabel' => $row->jenis->label(),
                'tanggal' => $row->berlaku_dari->toDateString(),
                'nilaiLama' => $terakhir[$kunci] ?? null,
                'nilaiBaru' => (float) $row->nilai,
                'catatan' => $row->catatan,
            ]);

            $terakhir[$kunci] = (float) $row->nilai;
        }

        return $hasil->sortByDesc('tanggal')->values()->take($limit);
    }

    public function resolver(): TarifResolver
    {
        return TarifResolver::muatSemua();
    }
}
