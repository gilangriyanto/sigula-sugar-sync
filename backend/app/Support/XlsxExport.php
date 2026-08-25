<?php

declare(strict_types=1);

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Penulis file Excel (.xlsx) untuk fitur Export Laporan. */
final class XlsxExport
{
    private const WARNA_HEADER = 'FF9C6B1F';   // gold/amber, warna utama SIGULA

    /**
     * @param  array<int, string>  $kolom
     * @param  iterable<int, array<int, string|int|float|null>>  $baris
     * @param  array<int, string>  $judul
     */
    public static function unduh(string $namaFile, array $kolom, iterable $baris, array $judul = []): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan');

        $nomorBaris = 1;
        $jumlahKolom = max(count($kolom), 1);
        $kolomTerakhir = self::hurufKolom($jumlahKolom);

        // Kepala laporan
        foreach ($judul as $index => $teks) {
            $sheet->setCellValue("A{$nomorBaris}", $teks);
            $sheet->mergeCells("A{$nomorBaris}:{$kolomTerakhir}{$nomorBaris}");
            $sheet->getStyle("A{$nomorBaris}")->getFont()
                ->setBold($index <= 1)
                ->setSize($index === 1 ? 14 : 11);
            $nomorBaris++;
        }

        if ($judul !== []) {
            $nomorBaris++;
        }

        // Header tabel
        $barisHeader = $nomorBaris;
        foreach ($kolom as $i => $judulKolom) {
            $sheet->setCellValue(self::hurufKolom($i + 1).$nomorBaris, $judulKolom);
        }

        $sheet->getStyle("A{$barisHeader}:{$kolomTerakhir}{$barisHeader}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::WARNA_HEADER]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $nomorBaris++;

        // Isi tabel
        $barisPertamaData = $nomorBaris;
        foreach ($baris as $row) {
            foreach (array_values($row) as $i => $nilai) {
                $sel = self::hurufKolom($i + 1).$nomorBaris;
                $angka = self::keAngka($nilai);

                if ($angka !== null) {
                    $sheet->setCellValue($sel, $angka);
                    $sheet->getStyle($sel)->getNumberFormat()->setFormatCode('#,##0.00');

                    continue;
                }

                $sheet->setCellValueExplicit($sel, (string) $nilai, DataType::TYPE_STRING);
            }
            $nomorBaris++;
        }

        // Garis tipis pada area tabel + lebar kolom otomatis
        if ($nomorBaris > $barisPertamaData) {
            $sheet->getStyle("A{$barisHeader}:{$kolomTerakhir}".($nomorBaris - 1))
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        for ($i = 1; $i <= $jumlahKolom; $i++) {
            $sheet->getColumnDimension(self::hurufKolom($i))->setAutoSize(true);
        }

        $sheet->freezePane('A'.$barisPertamaData);

        return response()->streamDownload(
            static function () use ($spreadsheet): void {
                (new Xlsx($spreadsheet))->save('php://output');
                $spreadsheet->disconnectWorksheets();
            },
            $namaFile,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache',
            ],
        );
    }

    /**
     * Mengembalikan angka asli dari string berformat Indonesia ("1450000,00").
     *
     * Sengaja memakai pola ketat (hanya digit + koma + 2 desimal) supaya teks
     * biasa seperti "TOTAL" atau nama orang tidak ikut dikonversi. Nilai perlu
     * dikembalikan sebagai angka asli agar bisa dijumlah/di-pivot di Excel —
     * kalau ditulis sebagai teks, rumus SUM tidak jalan.
     */
    private static function keAngka(string|int|float|null $nilai): ?float
    {
        if (is_int($nilai) || is_float($nilai)) {
            return (float) $nilai;
        }

        if ($nilai === null || $nilai === '') {
            return null;
        }

        return preg_match('/^-?\d+,\d{2}$/', $nilai) === 1
            ? (float) str_replace(',', '.', $nilai)
            : null;
    }

    private static function hurufKolom(int $nomor): string
    {
        return Coordinate::stringFromColumnIndex($nomor);
    }
}
