<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\KategoriBiaya;
use App\Http\Controllers\Controller;
use App\Http\Requests\BiayaOperasionalRequest;
use App\Http\Resources\BiayaOperasionalResource;
use App\Models\BiayaOperasional;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BiayaOperasionalController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('perPage', 50), 1), 200);
        $kategori = $request->filled('kategori') ? KategoriBiaya::tryFromAny($request->input('kategori')) : null;

        $rows = BiayaOperasional::query()
            ->rentang($request->input('dari'), $request->input('sampai'))
            ->when($kategori !== null, fn ($query) => $query->where('kategori', $kategori->value))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return BiayaOperasionalResource::collection($rows)->additional([
            'ringkasan' => [
                'total' => round((float) BiayaOperasional::query()
                    ->rentang($request->input('dari'), $request->input('sampai'))
                    ->when($kategori !== null, fn ($query) => $query->where('kategori', $kategori->value))
                    ->sum('jumlah'), 2),
            ],
        ]);
    }

    public function store(BiayaOperasionalRequest $request): JsonResponse
    {
        $biaya = BiayaOperasional::create([
            ...$request->payload(),
            'user_id' => $request->user()?->getKey(),
        ]);

        $this->audit->catat(
            'biaya.simpan',
            sprintf('Biaya %s ditambahkan: Rp %s', $biaya->keterangan, number_format((float) $biaya->jumlah, 0, ',', '.')),
            $biaya,
            [],
            $request->user(),
        );

        return (new BiayaOperasionalResource($biaya))
            ->additional(['message' => 'Biaya operasional ditambahkan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(BiayaOperasionalRequest $request, BiayaOperasional $biaya): BiayaOperasionalResource
    {
        $biaya->update($request->payload());

        $this->audit->catat('biaya.ubah', sprintf('Biaya %s diperbarui', $biaya->keterangan), $biaya, [], $request->user());

        return (new BiayaOperasionalResource($biaya->refresh()))->additional(['message' => 'Biaya operasional diperbarui.']);
    }

    public function destroy(Request $request, BiayaOperasional $biaya): JsonResponse
    {
        $this->audit->catat('biaya.hapus', sprintf('Biaya %s dihapus', $biaya->keterangan), $biaya, [], $request->user());
        $biaya->delete();

        return response()->json(['message' => 'Biaya operasional dihapus.']);
    }
}
