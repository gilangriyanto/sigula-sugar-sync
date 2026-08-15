<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Grade;
use App\Http\Controllers\Controller;
use App\Http\Requests\UbahHargaRequest;
use App\Services\HargaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterHargaController extends Controller
{
    public function __construct(private readonly HargaService $harga) {}

    /** Harga berjalan per grade + riwayat perubahannya. */
    public function index(Request $request): JsonResponse
    {
        $grade = $request->filled('grade') ? Grade::tryFromAny($request->input('grade')) : null;

        $hargaBeli = [];
        $daftarGrade = [];

        foreach (Grade::cases() as $g) {
            $berlaku = $this->harga->hargaBerlaku($g);
            $hargaBeli[$g->label()] = $berlaku?->harga_per_kg;

            $daftarGrade[] = [
                'kode' => $g->value,
                'nama' => $g->label(),
                'hargaPerKg' => $berlaku?->harga_per_kg,
                'berlakuDari' => $berlaku?->berlaku_dari->toDateString(),
            ];
        }

        return response()->json([
            'data' => [
                // Bentuk yang sama dengan state `hargaBeli` di frontend.
                'hargaBeli' => $hargaBeli,
                'grade' => $daftarGrade,
                'riwayat' => $this->harga->riwayat($grade)->all(),
            ],
        ]);
    }

    public function store(UbahHargaRequest $request): JsonResponse
    {
        $harga = $this->harga->ubahHarga(
            $request->grade(),
            $request->harga(),
            $request->input('berlakuDari'),
            $request->input('catatan'),
            $request->user(),
        );

        return response()->json([
            'message' => sprintf('Harga %s berhasil diperbarui.', $harga->grade->label()),
            'data' => [
                'id' => (string) $harga->id,
                'grade' => $harga->grade->label(),
                'gradeKode' => $harga->grade->value,
                'hargaPerKg' => (float) $harga->harga_per_kg,
                'berlakuDari' => $harga->berlaku_dari->toDateString(),
            ],
        ], 201);
    }
}
