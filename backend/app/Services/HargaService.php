<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Grade;
use App\Exceptions\BusinessRuleException;
use App\Models\GradeHarga;
use App\Models\User;
use App\Support\Periode;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Master harga beli per grade dengan histori.
 *
 * Aturan pokok: harga TIDAK PERNAH di-update. Setiap perubahan menambah baris
 * baru dengan berlaku_dari, sehingga transaksi lama tetap terhubung ke harga
 * yang benar-benar berlaku saat itu.
 */
final class HargaService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Record harga yang berlaku untuk grade pada waktu tertentu (default: sekarang). */
    public function hargaBerlaku(Grade $grade, CarbonInterface|string|null $pada = null): ?GradeHarga
    {
        $waktu = $pada === null
            ? now()
            : Periode::tanggal($pada)->endOfDay();

        return GradeHarga::query()
            ->where('grade', $grade->value)
            ->where('berlaku_dari', '<=', $waktu)
            ->orderByDesc('berlaku_dari')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Harga berjalan seluruh grade.
     *
     * @return array<string, float|null> dikunci label frontend: ['NS 1' => 14500.0, ...]
     */
    public function hargaSaatIni(): array
    {
        $hasil = [];

        foreach (Grade::cases() as $grade) {
            $hasil[$grade->label()] = $this->hargaBerlaku($grade)?->harga_per_kg;
        }

        return $hasil;
    }

    public function ubahHarga(
        Grade $grade,
        float $harga,
        CarbonInterface|string|null $berlakuDari = null,
        ?string $catatan = null,
        ?User $user = null,
    ): GradeHarga {
        if ($harga <= 0) {
            throw BusinessRuleException::untukField('harga', 'Harga per kg harus lebih dari 0.');
        }

        return DB::transaction(function () use ($grade, $harga, $berlakuDari, $catatan, $user): GradeHarga {
            $mulai = $berlakuDari === null
                ? now()
                : Periode::tanggal($berlakuDari)->setTimeFrom(now());

            $sebelumnya = $this->hargaBerlaku($grade, $mulai);

            $baru = GradeHarga::create([
                'grade' => $grade->value,
                'harga_per_kg' => round($harga, 2),
                'berlaku_dari' => $mulai,
                'catatan' => $catatan,
                'dibuat_oleh' => $user?->getKey(),
            ]);

            $this->audit->catat(
                'harga.ubah',
                sprintf(
                    'Harga %s diubah dari %s menjadi %s per kg',
                    $grade->label(),
                    $sebelumnya === null ? '-' : 'Rp '.number_format((float) $sebelumnya->harga_per_kg, 0, ',', '.'),
                    'Rp '.number_format($harga, 0, ',', '.'),
                ),
                $baru,
                [
                    'grade' => $grade->value,
                    'harga_lama' => $sebelumnya?->harga_per_kg,
                    'harga_baru' => round($harga, 2),
                    'berlaku_dari' => $mulai->toDateTimeString(),
                ],
                $user,
            );

            return $baru;
        });
    }

    /**
     * Riwayat perubahan harga lengkap dengan harga lama (diturunkan dari record
     * sebelumnya pada grade yang sama).
     *
     * @return Collection<int, array{id: string, grade: string, tanggal: string, hargaLama: float|null, hargaBaru: float, catatan: string|null}>
     */
    public function riwayat(?Grade $grade = null, int $limit = 100): Collection
    {
        $rows = GradeHarga::query()
            ->when($grade !== null, fn ($q) => $q->where('grade', $grade->value))
            ->orderBy('grade')
            ->orderBy('berlaku_dari')
            ->orderBy('id')
            ->get();

        $hasil = collect();
        $terakhir = [];

        foreach ($rows as $row) {
            $kunci = $row->grade->value;

            $hasil->push([
                'id' => (string) $row->id,
                'grade' => $row->grade->label(),
                'gradeKode' => $row->grade->value,
                'tanggal' => $row->berlaku_dari->toDateString(),
                'hargaLama' => $terakhir[$kunci] ?? null,
                'hargaBaru' => (float) $row->harga_per_kg,
                'catatan' => $row->catatan,
            ]);

            $terakhir[$kunci] = (float) $row->harga_per_kg;
        }

        return $hasil
            ->sortByDesc('tanggal')
            ->values()
            ->take($limit);
    }
}
