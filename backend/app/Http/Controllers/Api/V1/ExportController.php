<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Grade;
use App\Enums\JenisMutasi;
use App\Enums\KategoriBiaya;
use App\Enums\KategoriStok;
use App\Enums\StatusSesi;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\LaporanExportService;
use App\Support\CsvExport;
use App\Support\PdfExport;
use App\Support\RentangPeriode;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export laporan ke CSV (bisa langsung dibuka di Excel).
 *
 * Setiap endpoint memakai filter yang sama dengan halaman daftarnya, dan
 * tunduk pada gate modul masing-masing — staff gudang tidak bisa mengekspor
 * laporan keuangan maupun penggajian.
 */
class ExportController extends Controller
{
    /** Format file yang didukung. */
    private const ATURAN_FORMAT = ['format' => ['nullable', 'in:csv,xlsx,pdf']];

    public function __construct(
        private readonly LaporanExportService $export,
        private readonly AuditLogger $audit,
    ) {}

    public function labaRugi(Request $request): Response
    {
        $request->validate(RentangPeriode::aturanValidasi() + self::ATURAN_FORMAT);
        [$dari, $sampai] = RentangPeriode::dariRequest($request);

        return $this->kirim($request, 'laba-rugi', $this->export->labaRugi($dari, $sampai));
    }

    public function pembelian(Request $request): Response
    {
        $request->validate(RentangPeriode::aturanValidasi() + self::ATURAN_FORMAT + [
            'grade' => ['nullable', Rule::in(Grade::acceptedInputs())],
            'petaniId' => ['nullable', 'integer'],
        ]);
        [$dari, $sampai] = RentangPeriode::dariRequest($request);

        return $this->kirim($request, 'pembelian', $this->export->pembelian(
            $dari,
            $sampai,
            $request->filled('grade') ? Grade::tryFromAny($request->input('grade')) : null,
            $request->input('petaniId'),
        ));
    }

    public function penjualan(Request $request): Response
    {
        $request->validate(RentangPeriode::aturanValidasi() + self::ATURAN_FORMAT + [
            'eksportirId' => ['nullable', 'integer'],
        ]);
        [$dari, $sampai] = RentangPeriode::dariRequest($request);

        return $this->kirim($request, 'penjualan', $this->export->penjualan(
            $dari,
            $sampai,
            $request->input('eksportirId'),
        ));
    }

    public function produksi(Request $request): Response
    {
        $request->validate(RentangPeriode::aturanValidasi() + self::ATURAN_FORMAT + [
            'status' => ['nullable', Rule::in(StatusSesi::acceptedInputs())],
        ]);
        [$dari, $sampai] = RentangPeriode::dariRequest($request);

        return $this->kirim($request, 'produksi', $this->export->produksi(
            $dari,
            $sampai,
            $request->filled('status') ? StatusSesi::tryFromAny($request->input('status')) : null,
        ));
    }

    public function penggajian(Request $request): Response
    {
        $request->validate(['tanggal' => ['nullable', 'date']] + self::ATURAN_FORMAT);

        return $this->kirim($request, 'penggajian', $this->export->penggajian($request->input('tanggal')));
    }

    public function kartuStok(Request $request): Response
    {
        $request->validate(RentangPeriode::aturanValidasi() + self::ATURAN_FORMAT + [
            'kategori' => ['nullable', Rule::in(KategoriStok::acceptedInputs())],
            'jenis' => ['nullable', Rule::in(JenisMutasi::acceptedInputs())],
        ]);
        [$dari, $sampai] = RentangPeriode::dariRequest($request);

        return $this->kirim($request, 'kartu-stok', $this->export->kartuStok(
            $dari,
            $sampai,
            $request->filled('kategori') ? KategoriStok::tryFromAny($request->input('kategori')) : null,
            $request->filled('jenis') ? JenisMutasi::tryFromAny($request->input('jenis')) : null,
        ));
    }

    public function biaya(Request $request): Response
    {
        $request->validate(RentangPeriode::aturanValidasi() + self::ATURAN_FORMAT + [
            'kategori' => ['nullable', Rule::in(KategoriBiaya::acceptedInputs())],
        ]);
        [$dari, $sampai] = RentangPeriode::dariRequest($request);

        return $this->kirim($request, 'biaya-operasional', $this->export->biaya(
            $dari,
            $sampai,
            $request->filled('kategori') ? KategoriBiaya::tryFromAny($request->input('kategori')) : null,
        ));
    }

    /**
     * @param  array{namaFile: string, judul: array<int, string>, kolom: array<int, string>, baris: iterable}  $laporan
     */
    private function kirim(Request $request, string $jenis, array $laporan): Response
    {
        $format = (string) $request->input('format', 'csv');
        $namaFile = preg_replace('/\.csv$/', '.'.$format, $laporan['namaFile']) ?? $laporan['namaFile'];

        // Siapa mengekspor data apa ikut tercatat — laporan memuat angka sensitif.
        $this->audit->catat(
            'laporan.export',
            sprintf('Export laporan %s (%s)', $jenis, $namaFile),
            null,
            ['jenis' => $jenis, 'format' => $format, 'filter' => $request->query()],
            $request->user(),
        );

        return match ($format) {
            'xlsx' => XlsxExport::unduh($namaFile, $laporan['kolom'], $laporan['baris'], $laporan['judul']),
            'pdf' => PdfExport::unduh($namaFile, $laporan['kolom'], $laporan['baris'], $laporan['judul']),
            default => CsvExport::unduh($namaFile, $laporan['kolom'], $laporan['baris'], $laporan['judul']),
        };
    }
}
