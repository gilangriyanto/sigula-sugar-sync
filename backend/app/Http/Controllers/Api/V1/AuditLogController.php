<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('perPage', 50), 1), 200);

        $rows = AuditLog::query()
            ->with('user:id,name,role')
            ->when($request->filled('aksi'), fn ($query) => $query->where('aksi', 'like', $request->string('aksi')->value().'%'))
            ->when($request->filled('userId'), fn ($query) => $query->where('user_id', $request->input('userId')))
            ->when($request->filled('dari'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('dari')))
            ->when($request->filled('sampai'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('sampai')))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $rows->getCollection()->map(static fn (AuditLog $log): array => [
                'id' => (string) $log->id,
                'aksi' => $log->aksi,
                'deskripsi' => $log->deskripsi,
                'user' => $log->user === null ? null : [
                    'id' => (string) $log->user->id,
                    'nama' => $log->user->name,
                    'role' => $log->user->role->value,
                ],
                'data' => $log->data,
                'ip' => $log->ip,
                'waktu' => $log->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }
}
