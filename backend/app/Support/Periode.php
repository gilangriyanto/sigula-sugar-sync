<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Helper periode SIGULA.
 *
 * Periode gaji perusahaan adalah Senin s.d. JUMAT (bukan Senin-Minggu),
 * dibayarkan setiap hari Jumat. Semua modul yang menyentuh periode mingguan
 * wajib lewat helper ini supaya definisinya konsisten.
 */
final class Periode
{
    public const HARI_KERJA = 5;

    public static function tanggal(CarbonInterface|string|null $tanggal = null): CarbonImmutable
    {
        if ($tanggal === null) {
            return CarbonImmutable::today();
        }

        return $tanggal instanceof CarbonInterface
            ? CarbonImmutable::parse($tanggal->toDateString())
            : CarbonImmutable::parse($tanggal)->startOfDay();
    }

    /** Senin dari minggu yang memuat tanggal tersebut (Minggu ikut minggu sebelumnya). */
    public static function senin(CarbonInterface|string|null $tanggal = null): CarbonImmutable
    {
        return self::tanggal($tanggal)->startOfWeek(CarbonInterface::MONDAY);
    }

    /** Jumat dari minggu yang memuat tanggal tersebut. */
    public static function jumat(CarbonInterface|string|null $tanggal = null): CarbonImmutable
    {
        return self::senin($tanggal)->addDays(self::HARI_KERJA - 1);
    }

    /**
     * Rentang periode gaji Senin-Jumat.
     *
     * @return array{senin: CarbonImmutable, jumat: CarbonImmutable}
     */
    public static function mingguKerja(CarbonInterface|string|null $tanggal = null): array
    {
        $senin = self::senin($tanggal);

        return [
            'senin' => $senin,
            'jumat' => $senin->addDays(self::HARI_KERJA - 1),
        ];
    }

    /**
     * Rentang satu bulan kalender.
     *
     * @return array{awal: CarbonImmutable, akhir: CarbonImmutable}
     */
    public static function bulan(CarbonInterface|string|null $tanggal = null): array
    {
        $ref = self::tanggal($tanggal);

        return [
            'awal' => $ref->startOfMonth(),
            'akhir' => $ref->endOfMonth()->startOfDay(),
        ];
    }

    /**
     * Daftar kunci bulan (Y-m) sebanyak $jumlah bulan terakhir, termasuk bulan acuan.
     *
     * @return array<int, string>
     */
    public static function bulanTerakhir(int $jumlah, CarbonInterface|string|null $sampai = null): array
    {
        $ref = self::tanggal($sampai)->startOfMonth();
        $keys = [];

        for ($i = $jumlah - 1; $i >= 0; $i--) {
            $keys[] = $ref->subMonths($i)->format('Y-m');
        }

        return $keys;
    }

    /** Label bulan singkat ala frontend, contoh "Agu 26". */
    public static function labelBulanSingkat(string $kunciBulan): string
    {
        $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        [$tahun, $angkaBulan] = array_map('intval', explode('-', $kunciBulan));

        return $bulan[$angkaBulan - 1].' '.substr((string) $tahun, 2);
    }

    /** Tanggal ditulis gaya Indonesia, contoh "8 Agustus 2026". */
    public static function tanggalIndonesia(CarbonInterface|string $tanggal): string
    {
        $bulan = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];
        $ref = self::tanggal($tanggal);

        return $ref->day.' '.$bulan[$ref->month - 1].' '.$ref->year;
    }
}
