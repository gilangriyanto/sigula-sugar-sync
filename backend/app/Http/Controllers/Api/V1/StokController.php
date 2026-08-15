<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\JenisMutasi;
use App\Enums\KategoriStok;
use App\Http\Controllers\Controller;
use App\Http\Requests\StokOpnameRequest;
use App\Http\Resources\KartuStokResource;
use App\Models\KartuStok;
use App\Services\StokService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StokController extends Controller
{
    /** Ambang batas indikator "stok menipis" pada kartu stok bahan mentah. */
    private const AMBANG_MENIPIS = 1500.0;

    public function __construct(private readonly StokService $stok) {}

    /** Posisi stok seluruh kategori. */
    public function index(): JsonResponse
    {
        $saldo = $this->stok->semuaSaldo();

        $kartu = static fn (KategoriStok $k, float $nilai): array => [
            'kode' => $k->value,
            'nama' => $k->label(),
            'label' => $k->labelPanjang(),
            'saldo' => $nilai,
            'status' => $k->isBahanMentah() && $nilai < self::AMBANG_MENIPIS ? 'menipis' : 'aman',
        ];

        $bahanMentah = array_map(
            static fn (KategoriStok $k): array => $kartu($k, $saldo[$k->value]),
            KategoriStok::bahanMentah(),
        );

        return response()->json([
            'data' => [
                // Bentuk ringkas ala state `stok` di frontend: { "NS 1": 1200, ... }
                'saldo' => collect(KategoriStok::cases())
                    ->mapWithKeys(static fn (KategoriStok $k): array => [$k->label() => $saldo[$k->value]])
                    ->all(),
                'bahanMentah' => $bahanMentah,
                'totalBahanMentah' => round(array_sum(array_column($bahanMentah, 'saldo')), 2),
                'produkJadi' => array_map(
                    static fn (KategoriStok $k): array => $kartu($k, $saldo[$k->value]),
                    KategoriStok::produkJadi(),
                ),
                'ambangMenipis' => self::AMBANG_MENIPIS,
            ],
        ]);
    }

    /** Kartu stok (histori keluar-masuk). */
    public function kartuStok(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('perPage', 50), 1), 200);
        $q = $request->string('q')->trim()->value();

        $rows = KartuStok::query()
            ->kategori($request->filled('kategori') ? KategoriStok::tryFromAny($request->input('kategori')) : null)
            ->jenis($request->filled('jenis') ? JenisMutasi::tryFromAny($request->input('jenis')) : null)
            ->rentang($request->input('dari'), $request->input('sampai'))
            ->when($q !== '', fn ($query) => $query->where('keterangan', 'like', '%'.$q.'%'))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return KartuStokResource::collection($rows);
    }

    /** Koreksi manual stok fisik vs sistem. */
    public function opname(StokOpnameRequest $request): JsonResponse
    {
        $mutasi = $this->stok->opname(
            $request->kategori(),
            (float) $request->input('stokFisik'),
            (string) $request->input('alasan'),
            $request->input('tanggal'),
            $request->user(),
        );

        return response()->json([
            'message' => 'Stok opname tercatat.',
            'data' => new KartuStokResource($mutasi),
        ], 201);
    }
}
