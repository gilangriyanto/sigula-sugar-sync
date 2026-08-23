<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Services\LaporanService;
use App\Services\RingkasanAiService;
use App\Support\RentangPeriode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class LaporanController extends Controller
{
    public function __construct(
        private readonly LaporanService $laporan,
        private readonly RingkasanAiService $ringkasanAi,
    ) {}

    /**
     * Laporan laba rugi.
     *
     * periode = bulan_ini | bulan_lalu | custom (butuh dari & sampai).
     */
    public function labaRugi(Request $request): JsonResponse
    {
        $request->validate(RentangPeriode::aturanValidasi());

        [$dari, $sampai] = RentangPeriode::dariRequest($request);

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

    /**
     * Ringkasan naratif laporan yang ditulis AI (Laravel AI SDK + Claude).
     *
     * Angka tidak dihitung oleh model — seluruhnya diambil dari transaksi nyata
     * lalu dikirim sebagai fakta di dalam prompt.
     */
    public function ringkasanAi(Request $request): JsonResponse
    {
        $request->validate(RentangPeriode::aturanValidasi() + [
            'segarkan' => ['nullable', 'boolean'],
        ]);

        [$dari, $sampai] = RentangPeriode::dariRequest($request);

        try {
            return response()->json([
                'data' => $this->ringkasanAi->untukPeriode(
                    $dari,
                    $sampai,
                    $request->boolean('segarkan'),
                    $request->user(),
                ),
            ]);
        } catch (BusinessRuleException $e) {
            throw $e;   // kunci API belum diisi — pesannya sudah ramah pengguna
        } catch (Throwable $e) {
            // Kegagalan panggilan model tidak boleh tampil sebagai error 500 mentah.
            Log::error('Ringkasan AI gagal', ['pesan' => $e->getMessage(), 'periode' => [$dari, $sampai]]);

            throw new BusinessRuleException(RingkasanAiService::pesanGagal($e), [], 502);
        }
    }
}
