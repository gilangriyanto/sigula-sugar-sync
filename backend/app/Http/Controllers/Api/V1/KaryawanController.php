<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\KaryawanRequest;
use App\Http\Resources\KaryawanResource;
use App\Models\Karyawan;
use App\Services\MasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class KaryawanController extends Controller
{
    public function __construct(private readonly MasterDataService $master) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $karyawan = Karyawan::query()
            ->cari($request->string('q')->trim()->value())
            ->when(! $request->boolean('sertakanNonaktif'), fn ($q) => $q->aktif())
            ->orderBy('nama')
            ->get();

        return KaryawanResource::collection($karyawan);
    }

    public function store(KaryawanRequest $request): JsonResponse
    {
        $karyawan = $this->master->simpanKaryawan($request->payload(), $request->user());

        return (new KaryawanResource($karyawan))
            ->additional(['message' => 'Karyawan berhasil ditambahkan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(KaryawanRequest $request, Karyawan $karyawan): KaryawanResource
    {
        $karyawan = $this->master->ubahKaryawan($karyawan, $request->payload(), $request->user());

        return (new KaryawanResource($karyawan))->additional(['message' => 'Data karyawan berhasil diperbarui.']);
    }

    public function destroy(Request $request, Karyawan $karyawan): JsonResponse
    {
        $hasil = $this->master->hapusKaryawan($karyawan, $request->user());

        return response()->json([
            'message' => $hasil === 'nonaktif'
                ? 'Karyawan punya histori produksi, jadi dinonaktifkan (bukan dihapus) agar data gaji tetap utuh.'
                : 'Karyawan berhasil dihapus.',
        ]);
    }
}
