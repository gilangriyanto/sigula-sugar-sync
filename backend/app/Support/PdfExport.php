<?php

declare(strict_types=1);

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/** Penulis file PDF untuk fitur Export Laporan. */
final class PdfExport
{
    /**
     * Batas baris untuk PDF. Dompdf menyusun seluruh dokumen di memori, jadi
     * laporan yang sangat panjang dipotong dengan keterangan di bawah tabel —
     * lebih baik daripada proses mati kehabisan memori di server.
     */
    private const BATAS_BARIS = 3000;

    /**
     * @param  array<int, string>  $kolom
     * @param  iterable<int, array<int, string|int|float|null>>  $baris
     * @param  array<int, string>  $judul
     */
    public static function unduh(string $namaFile, array $kolom, iterable $baris, array $judul = []): Response
    {
        $data = [];
        $terpotong = false;

        foreach ($baris as $row) {
            if (count($data) >= self::BATAS_BARIS) {
                $terpotong = true;
                break;
            }

            $data[] = $row;
        }

        // Laporan lebar (>6 kolom) lebih terbaca dalam orientasi landscape.
        $orientasi = count($kolom) > 6 ? 'landscape' : 'portrait';

        return Pdf::loadView('laporan.pdf', [
            'judul' => $judul,
            'kolom' => $kolom,
            'baris' => $data,
            'terpotong' => $terpotong,
            'batasBaris' => self::BATAS_BARIS,
        ])->setPaper('a4', $orientasi)->download($namaFile);
    }
}
