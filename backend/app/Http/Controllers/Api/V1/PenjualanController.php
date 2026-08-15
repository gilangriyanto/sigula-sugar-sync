<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\JenisProduk;
use App\Enums\StatusPembayaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\PenjualanRequest;
use App\Http\Resources\PenjualanResource;
use App\Models\Penjualan;
use App\Services\PenjualanService;
use App\Support\Periode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PenjualanController extends Controller
{
    public function __construct(private readonly PenjualanService $penjualan) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('perPage', 25), 1), 200);
        $q = $request->string('q')->trim()->value();

        $rows = Penjualan::query()
            ->with(['eksportir', 'items'])
            ->rentang($request->input('dari'), $request->input('sampai'))
            ->when($request->filled('eksportirId'), fn ($query) => $query->where('eksportir_id', $request->input('eksportirId')))
            ->when($q !== '', fn ($query) => $query->where(function ($sub) use ($q): void {
                $sub->where('nomor_invoice', 'like', '%'.$q.'%')
                    ->orWhereHas('eksportir', fn ($e) => $e->where('nama', 'like', '%'.$q.'%'));
            }))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return PenjualanResource::collection($rows)->additional([
            'ringkasan' => $this->penjualan->ringkasanBulanIni(),
        ]);
    }

    public function store(PenjualanRequest $request): JsonResponse
    {
        $penjualan = $this->penjualan->simpan($request->payload(), $request->user());

        return (new PenjualanResource($penjualan))
            ->additional([
                'message' => 'Transaksi penjualan tersimpan.',
                'invoice' => $this->invoice($penjualan),
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Penjualan $penjualan): PenjualanResource
    {
        $penjualan->load(['eksportir', 'items']);

        return (new PenjualanResource($penjualan))->additional(['invoice' => $this->invoice($penjualan)]);
    }

    public function ubahStatus(Request $request, Penjualan $penjualan): PenjualanResource
    {
        $validated = $request->validate([
            'statusPembayaran' => ['required', Rule::in(StatusPembayaran::acceptedInputs())],
        ]);

        $penjualan = $this->penjualan->ubahStatusPembayaran(
            $penjualan,
            StatusPembayaran::fromAny($validated['statusPembayaran']),
            $request->user(),
        );

        return (new PenjualanResource($penjualan))->additional(['message' => 'Status pembayaran diperbarui.']);
    }

    public function destroy(Request $request, Penjualan $penjualan): JsonResponse
    {
        $this->penjualan->batalkan($penjualan, $request->user(), $request->input('alasan'));

        return response()->json(['message' => 'Transaksi penjualan dibatalkan dan stok dikembalikan.']);
    }

    /** Data siap cetak invoice dengan rincian 2 baris. */
    private function invoice(Penjualan $penjualan): array
    {
        $penjualan->loadMissing(['eksportir', 'items']);

        return [
            'nomor' => $penjualan->nomor_invoice,
            'tanggal' => Periode::tanggalIndonesia($penjualan->tanggal),
            'eksportir' => $penjualan->eksportir?->nama,
            // Kristal selalu ditampilkan lebih dulu, lalu brondol.
            'baris' => $penjualan->items
                ->sortBy(static fn ($item): int => $item->jenis === JenisProduk::KRISTAL ? 0 : 1)
                ->map(static fn ($item): array => [
                    'jenis' => $item->jenis->labelPanjang(),
                    'kilogram' => (float) $item->kilogram,
                    'hargaPerKg' => (float) $item->harga_per_kg,
                    'subtotal' => (float) $item->subtotal,
                ])
                ->values()
                ->all(),
            'total' => (float) $penjualan->total,
            'statusPembayaran' => $penjualan->status_pembayaran->label(),
        ];
    }
}
