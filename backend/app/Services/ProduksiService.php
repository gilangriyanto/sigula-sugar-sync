<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Grade;
use App\Enums\KategoriStok;
use App\Enums\StatusGaji;
use App\Enums\StatusSesi;
use App\Exceptions\BusinessRuleException;
use App\Models\GajiMingguan;
use App\Models\Karyawan;
use App\Models\ProduksiKaryawan;
use App\Models\SesiTungku;
use App\Models\User;
use App\Support\Periode;
use Illuminate\Support\Facades\DB;

/**
 * Modul produksi berbasis SESI TUNGKU.
 *
 * Satu sesi = satu tungku, tepat 2 karyawan, satu kali masak yang menghasilkan
 * gula kristal DAN brondol sekaligus (brondol adalah hasil sampingan pada sesi
 * yang sama, bukan sesi terpisah). Dalam satu hari belasan tungku berjalan
 * paralel. Saat sesi diselesaikan, bahan mentah dan hasil dibagi rata ke kedua
 * karyawan dan disimpan di produksi_karyawan sebagai sumber data penggajian.
 */
final class ProduksiService
{
    public function __construct(
        private readonly StokService $stok,
        private readonly NomorGeneratorService $nomor,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param array{
     *     tanggal: string,
     *     grade: Grade,
     *     kg_bahan_mentah: float,
     *     karyawan_1_id: int|string,
     *     karyawan_2_id: int|string,
     *     kode_tungku?: string|null,
     *     catatan?: string|null
     * } $data
     */
    public function mulai(array $data, ?User $user = null): SesiTungku
    {
        $kgBahan = round((float) $data['kg_bahan_mentah'], 2);

        if ($kgBahan <= 0) {
            throw BusinessRuleException::untukField('kgBahan', 'Kg bahan mentah harus lebih dari 0.');
        }

        if ((string) $data['karyawan_1_id'] === (string) $data['karyawan_2_id']) {
            throw BusinessRuleException::untukField('karyawan2Id', 'Karyawan 1 dan Karyawan 2 tidak boleh orang yang sama.');
        }

        return DB::transaction(function () use ($data, $kgBahan, $user): SesiTungku {
            /** @var Grade $grade */
            $grade = $data['grade'];
            $tanggal = Periode::tanggal($data['tanggal']);

            $karyawan1 = Karyawan::query()->findOrFail($data['karyawan_1_id']);
            $karyawan2 = Karyawan::query()->findOrFail($data['karyawan_2_id']);

            $this->pastikanBahanCukup($grade, $kgBahan);

            $sesi = SesiTungku::create([
                'tanggal' => $tanggal->toDateString(),
                'kode_tungku' => filled($data['kode_tungku'] ?? null)
                    ? trim((string) $data['kode_tungku'])
                    : $this->nomor->kodeTungku($tanggal),
                'grade' => $grade->value,
                'kg_bahan_mentah' => $kgBahan,
                'karyawan_1_id' => $karyawan1->id,
                'karyawan_2_id' => $karyawan2->id,
                'status' => StatusSesi::SEDANG_DIPROSES->value,
                'catatan' => $data['catatan'] ?? null,
                'user_id' => $user?->getKey(),
            ]);

            $this->audit->catat(
                'produksi.mulai',
                sprintf(
                    'Sesi tungku %s dimulai: %s kg %s oleh %s & %s',
                    $sesi->kode_tungku, $kgBahan, $grade->label(), $karyawan1->nama, $karyawan2->nama
                ),
                $sesi,
                ['kg_bahan_mentah' => $kgBahan, 'grade' => $grade->value],
                $user,
            );

            return $sesi->load(['karyawan1', 'karyawan2']);
        });
    }

    /**
     * Menutup sesi: catat hasil, hitung rendemen, potong stok bahan mentah,
     * tambah stok produk jadi, dan bagi rata hasil ke 2 karyawan.
     */
    public function selesaikan(SesiTungku $sesi, float $kgKristal, float $kgBrondol, ?User $user = null): SesiTungku
    {
        $kgKristal = round($kgKristal, 2);
        $kgBrondol = round($kgBrondol, 2);

        if ($kgKristal < 0 || $kgBrondol < 0) {
            throw new BusinessRuleException('Hasil produksi tidak boleh negatif.');
        }

        if ($kgKristal + $kgBrondol <= 0) {
            throw BusinessRuleException::untukField('kgKristal', 'Total hasil produksi harus lebih dari 0.');
        }

        return DB::transaction(function () use ($sesi, $kgKristal, $kgBrondol, $user): SesiTungku {
            /** @var SesiTungku $terkunci */
            $terkunci = SesiTungku::query()->whereKey($sesi->getKey())->lockForUpdate()->firstOrFail();

            if ($terkunci->status === StatusSesi::SELESAI) {
                throw new BusinessRuleException(sprintf('Sesi tungku %s sudah berstatus selesai.', $terkunci->kode_tungku));
            }

            $kgBahan = (float) $terkunci->kg_bahan_mentah;
            $totalHasil = round($kgKristal + $kgBrondol, 2);

            // Kekekalan massa: hasil masak tidak mungkin melebihi bahan mentah.
            if ($totalHasil > $kgBahan) {
                throw BusinessRuleException::untukField('kgKristal', sprintf(
                    'Total hasil (%s kg) tidak boleh melebihi bahan mentah (%s kg).',
                    $totalHasil,
                    $kgBahan,
                ));
            }

            $terkunci->kg_kristal_total = $kgKristal;
            $terkunci->kg_brondol_total = $kgBrondol;
            $terkunci->rendemen = round($totalHasil / $kgBahan * 100, 2);
            $terkunci->status = StatusSesi::SELESAI;
            $terkunci->selesai_pada = now();
            $terkunci->save();

            $label = sprintf('tungku %s', $terkunci->kode_tungku);

            $this->stok->keluar(
                $terkunci->grade->kategoriStok(),
                $kgBahan,
                $terkunci->tanggal,
                'Produksi '.$label,
                $terkunci,
                $user,
            );

            if ($kgKristal > 0) {
                $this->stok->masuk(KategoriStok::KRISTAL, $kgKristal, $terkunci->tanggal, 'Hasil '.$label, $terkunci, $user);
            }

            if ($kgBrondol > 0) {
                $this->stok->masuk(KategoriStok::BRONDOL, $kgBrondol, $terkunci->tanggal, 'Hasil '.$label, $terkunci, $user);
            }

            $this->simpanPorsiKaryawan($terkunci);

            $this->audit->catat(
                'produksi.selesai',
                sprintf(
                    'Sesi tungku %s selesai: %s kg kristal + %s kg brondol (rendemen %s%%)',
                    $terkunci->kode_tungku, $kgKristal, $kgBrondol, $terkunci->rendemen
                ),
                $terkunci,
                [
                    'kg_bahan_mentah' => $kgBahan,
                    'kg_kristal_total' => $kgKristal,
                    'kg_brondol_total' => $kgBrondol,
                    'rendemen' => $terkunci->rendemen,
                ],
                $user,
            );

            return $terkunci->load(['karyawan1', 'karyawan2', 'porsiKaryawan']);
        });
    }

    /**
     * Membagi rata bahan mentah dan hasil ke dua karyawan.
     *
     * Porsi kedua dihitung sebagai sisa (total − porsi pertama) supaya jumlah
     * kedua porsi selalu persis sama dengan total sesi walau angkanya ganjil.
     */
    private function simpanPorsiKaryawan(SesiTungku $sesi): void
    {
        $sesi->porsiKaryawan()->delete();

        $bagi = static function (float $total): array {
            $pertama = round($total / 2, 2);

            return [$pertama, round($total - $pertama, 2)];
        };

        [$bahan1, $bahan2] = $bagi((float) $sesi->kg_bahan_mentah);
        [$kristal1, $kristal2] = $bagi((float) $sesi->kg_kristal_total);
        [$brondol1, $brondol2] = $bagi((float) $sesi->kg_brondol_total);

        $porsi = [
            [$sesi->karyawan_1_id, $bahan1, $kristal1, $brondol1],
            [$sesi->karyawan_2_id, $bahan2, $kristal2, $brondol2],
        ];

        foreach ($porsi as [$karyawanId, $bahan, $kristal, $brondol]) {
            ProduksiKaryawan::create([
                'sesi_tungku_id' => $sesi->id,
                'karyawan_id' => $karyawanId,
                'tanggal' => $sesi->tanggal->toDateString(),
                'kg_bahan_mentah_porsi' => $bahan,
                'kg_kristal_porsi' => $kristal,
                'kg_brondol_porsi' => $brondol,
            ]);
        }
    }

    /**
     * Membatalkan sesi. Untuk sesi yang sudah selesai, seluruh efek stok
     * dikembalikan lewat mutasi balik dan porsi karyawan dihapus — kecuali gaji
     * periode tersebut sudah dibayar (histori pembayaran tidak boleh berubah).
     */
    public function batalkan(SesiTungku $sesi, ?User $user = null, ?string $alasan = null): void
    {
        DB::transaction(function () use ($sesi, $user, $alasan): void {
            /** @var SesiTungku $terkunci */
            $terkunci = SesiTungku::query()->whereKey($sesi->getKey())->lockForUpdate()->firstOrFail();

            if ($terkunci->status === StatusSesi::SELESAI) {
                $this->pastikanGajiBelumDibayar($terkunci);

                $kgKristal = (float) ($terkunci->kg_kristal_total ?? 0);
                $kgBrondol = (float) ($terkunci->kg_brondol_total ?? 0);
                $label = sprintf('sesi tungku %s', $terkunci->kode_tungku);

                if ($kgKristal > 0) {
                    $this->stok->keluar(KategoriStok::KRISTAL, $kgKristal, $terkunci->tanggal, 'Pembatalan '.$label, $terkunci, $user);
                }

                if ($kgBrondol > 0) {
                    $this->stok->keluar(KategoriStok::BRONDOL, $kgBrondol, $terkunci->tanggal, 'Pembatalan '.$label, $terkunci, $user);
                }

                $this->stok->masuk(
                    $terkunci->grade->kategoriStok(),
                    (float) $terkunci->kg_bahan_mentah,
                    $terkunci->tanggal,
                    'Pengembalian bahan '.$label,
                    $terkunci,
                    $user,
                );

                $terkunci->porsiKaryawan()->delete();
            }

            $this->audit->catat(
                'produksi.batal',
                sprintf('Sesi tungku %s dibatalkan', $terkunci->kode_tungku),
                $terkunci,
                ['alasan' => $alasan, 'status_sebelumnya' => $terkunci->status->value],
                $user,
            );

            $terkunci->delete();
        });
    }

    /**
     * Ringkasan produksi satu hari (default hari ini).
     *
     * @return array{tanggal: string, jumlahSesi: int, tungkuAktif: int, kgBahan: float, kgKristal: float, kgBrondol: float, totalProduksi: float, rendemen: float|null}
     */
    public function ringkasanHarian(?string $tanggal = null): array
    {
        $tanggal = Periode::tanggal($tanggal)->toDateString();

        $sesi = SesiTungku::query()->whereDate('tanggal', $tanggal)->get();
        $selesai = $sesi->where('status', StatusSesi::SELESAI);

        $kgBahan = (float) $selesai->sum('kg_bahan_mentah');
        $kgKristal = (float) $selesai->sum('kg_kristal_total');
        $kgBrondol = (float) $selesai->sum('kg_brondol_total');

        return [
            'tanggal' => $tanggal,
            'jumlahSesi' => $sesi->count(),
            'tungkuAktif' => $sesi->where('status', StatusSesi::SEDANG_DIPROSES)->count(),
            'kgBahan' => round((float) $sesi->sum('kg_bahan_mentah'), 2),
            'kgKristal' => round($kgKristal, 2),
            'kgBrondol' => round($kgBrondol, 2),
            'totalProduksi' => round($kgKristal + $kgBrondol, 2),
            'rendemen' => $kgBahan > 0 ? round(($kgKristal + $kgBrondol) / $kgBahan * 100, 2) : null,
        ];
    }

    /**
     * Rendemen harian untuk grafik tren.
     *
     * @return array<int, array{tanggal: string, rendemen: float, kgBahan: float, kgHasil: float}>
     */
    public function trenRendemen(int $jumlahHari = 14, ?string $sampai = null): array
    {
        $akhir = Periode::tanggal($sampai);
        $awal = $akhir->subDays(max(1, $jumlahHari) - 1);

        $sesi = SesiTungku::query()
            ->selesai()
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->get(['tanggal', 'kg_bahan_mentah', 'kg_kristal_total', 'kg_brondol_total'])
            ->groupBy(fn (SesiTungku $s): string => $s->tanggal->toDateString());

        $hasil = [];

        for ($i = 0; $i < $jumlahHari; $i++) {
            $tanggal = $awal->addDays($i)->toDateString();
            $rows = $sesi->get($tanggal);

            $kgBahan = $rows ? (float) $rows->sum('kg_bahan_mentah') : 0.0;
            $kgHasil = $rows ? (float) $rows->sum('kg_kristal_total') + (float) $rows->sum('kg_brondol_total') : 0.0;

            $hasil[] = [
                'tanggal' => $tanggal,
                'kgBahan' => round($kgBahan, 2),
                'kgHasil' => round($kgHasil, 2),
                'rendemen' => $kgBahan > 0 ? round($kgHasil / $kgBahan * 100, 1) : 0.0,
            ];
        }

        return $hasil;
    }

    /**
     * Bahan mentah yang benar-benar bisa dipakai sesi baru = saldo stok dikurangi
     * kg yang masih "dipegang" sesi lain yang belum selesai. Tanpa ini, 15 tungku
     * bisa sama-sama dimulai padahal bahan hanya cukup untuk 10.
     */
    private function pastikanBahanCukup(Grade $grade, float $kgBahan): void
    {
        $saldo = $this->stok->saldo($grade->kategoriStok());

        $sedangDiproses = (float) SesiTungku::query()
            ->where('grade', $grade->value)
            ->where('status', StatusSesi::SEDANG_DIPROSES->value)
            ->sum('kg_bahan_mentah');

        $tersedia = round($saldo - $sedangDiproses, 2);

        if ($kgBahan > $tersedia) {
            throw BusinessRuleException::untukField('kgBahan', sprintf(
                'Stok bahan %s tidak mencukupi. Saldo %s kg, %s kg sedang dipakai tungku lain, sisa yang bisa dipakai %s kg.',
                $grade->label(),
                $this->format($saldo),
                $this->format($sedangDiproses),
                $this->format(max($tersedia, 0)),
            ));
        }
    }

    private function pastikanGajiBelumDibayar(SesiTungku $sesi): void
    {
        $periode = Periode::mingguKerja($sesi->tanggal);

        $sudahDibayar = GajiMingguan::query()
            ->whereIn('karyawan_id', [$sesi->karyawan_1_id, $sesi->karyawan_2_id])
            ->whereDate('periode_senin', $periode['senin']->toDateString())
            ->where('status', StatusGaji::SUDAH_DIBAYAR->value)
            ->exists();

        if ($sudahDibayar) {
            throw new BusinessRuleException(sprintf(
                'Sesi tidak bisa dibatalkan karena gaji periode %s — %s untuk karyawan terkait sudah dibayarkan.',
                Periode::tanggalIndonesia($periode['senin']),
                Periode::tanggalIndonesia($periode['jumat']),
            ));
        }
    }

    private function format(float $nilai): string
    {
        return number_format($nilai, fmod($nilai, 1.0) === 0.0 ? 0 : 2, ',', '.');
    }
}
