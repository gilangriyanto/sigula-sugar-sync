<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penulis CSV untuk fitur Export Laporan.
 *
 * Pilihan format sengaja disesuaikan dengan Excel berlokal Indonesia:
 * - pemisah kolom titik koma (;), karena list separator locale id-ID adalah ";"
 * - desimal memakai koma, tanpa pemisah ribuan (aman diparse sebagai angka)
 * - diawali BOM UTF-8 supaya huruf beraksen tidak berubah jadi karakter aneh
 *
 * Baris ditulis lewat generator dan dikirim sebagai streamed response, jadi
 * export ribuan baris tidak menahan seluruh data di memori.
 */
final class CsvExport
{
    private const PEMISAH = ';';

    private const BOM = "\xEF\xBB\xBF";

    /**
     * @param  array<int, string>  $kolom  header tabel
     * @param  iterable<int, array<int, string|int|float|null>>  $baris
     * @param  array<int, string>  $judul  baris keterangan di atas tabel
     */
    public static function unduh(string $namaFile, array $kolom, iterable $baris, array $judul = []): StreamedResponse
    {
        return response()->streamDownload(
            static function () use ($kolom, $baris, $judul): void {
                $out = fopen('php://output', 'w');
                fwrite($out, self::BOM);

                foreach ($judul as $teks) {
                    self::tulis($out, [$teks]);
                }

                if ($judul !== []) {
                    self::tulis($out, ['']);
                }

                self::tulis($out, $kolom);

                foreach ($baris as $row) {
                    self::tulis($out, $row);
                }

                fclose($out);
            },
            $namaFile,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache',
            ],
        );
    }

    /** Angka dengan koma desimal tanpa pemisah ribuan, mis. 1450000,00 */
    public static function angka(float|int|null $nilai, int $desimal = 2): string
    {
        return number_format((float) ($nilai ?? 0), $desimal, ',', '');
    }

    /** Nama file yang aman: huruf kecil, tanpa spasi, berakhiran rentang tanggal. */
    public static function namaFile(string $laporan, string $dari, string $sampai): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($laporan)) ?? 'laporan';

        return sprintf('%s_%s_sd_%s.csv', trim($slug, '-'), $dari, $sampai);
    }

    /**
     * @param  resource  $out
     * @param  array<int, string|int|float|null>  $baris
     */
    private static function tulis($out, array $baris): void
    {
        // $escape dikirim eksplisit: pada PHP 8.4 nilai default-nya sudah deprecated.
        fputcsv($out, $baris, self::PEMISAH, '"', '', PHP_EOL);
    }
}
