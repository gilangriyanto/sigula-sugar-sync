<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Penerjemah parameter periode pada laporan & export.
 *
 * Dipakai bersama oleh LaporanController dan ExportController supaya filter
 * "Bulan Ini / Bulan Lalu / Custom Range" berperilaku persis sama di keduanya.
 */
final class RentangPeriode
{
    /**
     * Aturan validasi standar untuk parameter periode.
     *
     * @return array<string, array<int, string>>
     */
    public static function aturanValidasi(): array
    {
        return [
            'periode' => ['nullable', 'in:bulan_ini,bulan_lalu,custom'],
            'dari' => ['nullable', 'date', 'required_if:periode,custom'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari', 'required_if:periode,custom'],
        ];
    }

    /**
     * Mengembalikan rentang tanggal [dari, sampai] dalam format Y-m-d.
     *
     * Bila `dari` dan `sampai` sama-sama diisi, keduanya dipakai apa adanya
     * walaupun `periode` tidak disetel ke "custom".
     *
     * @return array{0: string, 1: string}
     */
    public static function dariRequest(Request $request): array
    {
        $periode = (string) $request->input('periode', 'bulan_ini');

        if ($periode === 'custom' || ($request->filled('dari') && $request->filled('sampai'))) {
            return [(string) $request->input('dari'), (string) $request->input('sampai')];
        }

        $acuan = $periode === 'bulan_lalu'
            ? Periode::tanggal()->subMonthNoOverflow()
            : Periode::tanggal();

        $bulan = Periode::bulan($acuan);

        return [$bulan['awal']->toDateString(), $bulan['akhir']->toDateString()];
    }

    /** Label periode gaya Indonesia untuk dicetak di kepala laporan. */
    public static function label(string $dari, string $sampai): string
    {
        return Periode::tanggalIndonesia($dari).' s.d. '.Periode::tanggalIndonesia($sampai);
    }
}
