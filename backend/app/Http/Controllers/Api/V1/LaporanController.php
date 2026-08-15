<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LaporanService;
use App\Support\Periode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    public function __construct(private readonly LaporanService $laporan) {}

    /**
     * Laporan laba rugi.
     *
     * periode = bulan_ini | bulan_lalu | custom (butuh dari & sampai).
     */
    public function labaRugi(Request $request): JsonResponse
    {
        $request->validate([
            'periode' => ['nullable', 'in:bulan_ini,bulan_lalu,custom'],
            'dari' => ['nullable', 'date', 'required_if:periode,custom'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari', 'required_if:periode,custom'],
        ]);

        [$dari, $sampai] = $this->rentang($request);

        return response()->json([
            'data' => $this->laporan->labaRugi($dari, $sampai),
        ]);
    }

    public function tren(Request $request): JsonResponse
    {
        $request->validate(['bulan' => ['nullable', 'integer', 'min:1', 'max:36']]);

        return response()->json([
            'data' => $this->laporan->trenBulanan($request->integer('bulan', 6), $request->input('sampai')),
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function rentang(Request $request): array
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
}
