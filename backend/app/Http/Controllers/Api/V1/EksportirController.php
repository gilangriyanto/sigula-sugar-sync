<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EksportirRequest;
use App\Http\Resources\EksportirResource;
use App\Models\Eksportir;
use App\Services\MasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EksportirController extends Controller
{
    public function __construct(private readonly MasterDataService $master) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $eksportir = Eksportir::query()
            ->when(! $request->boolean('sertakanNonaktif'), fn ($q) => $q->aktif())
            ->orderBy('nama')
            ->get();

        return EksportirResource::collection($eksportir);
    }

    public function store(EksportirRequest $request): JsonResponse
    {
        $eksportir = $this->master->simpanEksportir($request->payload(), $request->user());

        return (new EksportirResource($eksportir))
            ->additional(['message' => 'Eksportir berhasil ditambahkan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(EksportirRequest $request, Eksportir $eksportir): EksportirResource
    {
        $eksportir = $this->master->ubahEksportir($eksportir, $request->payload(), $request->user());

        return (new EksportirResource($eksportir))->additional(['message' => 'Data eksportir berhasil diperbarui.']);
    }

    public function destroy(Request $request, Eksportir $eksportir): JsonResponse
    {
        $this->master->hapusEksportir($eksportir, $request->user());

        return response()->json(['message' => 'Eksportir berhasil dihapus.']);
    }
}
