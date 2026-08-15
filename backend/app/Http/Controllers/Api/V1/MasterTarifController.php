<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\JenisTarif;
use App\Http\Controllers\Controller;
use App\Http\Requests\UbahTarifRequest;
use App\Services\TarifService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterTarifController extends Controller
{
    public function __construct(private readonly TarifService $tarif) {}

    public function index(Request $request): JsonResponse
    {
        $jenis = $request->filled('jenis') ? JenisTarif::tryFromAny($request->input('jenis')) : null;

        return response()->json([
            'data' => [
                'tarif' => $this->tarif->tarifSaatIni(),
                'jenis' => array_map(
                    fn (JenisTarif $j): array => [
                        'kode' => $j->value,
                        'nama' => $j->label(),
                        'nilai' => $this->tarif->tarifBerlaku($j),
                    ],
                    JenisTarif::cases(),
                ),
                'riwayat' => $this->tarif->riwayat($jenis)->all(),
            ],
        ]);
    }

    public function store(UbahTarifRequest $request): JsonResponse
    {
        $tarif = $this->tarif->ubahTarif(
            $request->jenis(),
            $request->nilai(),
            $request->input('berlakuDari'),
            $request->input('catatan'),
            $request->user(),
        );

        return response()->json([
            'message' => sprintf('%s berhasil diperbarui.', $tarif->jenis->label()),
            'data' => [
                'id' => (string) $tarif->id,
                'jenis' => $tarif->jenis->value,
                'jenisLabel' => $tarif->jenis->label(),
                'nilai' => (float) $tarif->nilai,
                'berlakuDari' => $tarif->berlaku_dari->toDateString(),
            ],
        ], 201);
    }
}
