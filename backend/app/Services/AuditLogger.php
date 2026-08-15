<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Pencatat audit trail untuk aksi yang mengubah harga, tarif, stok, dan transaksi. */
final class AuditLogger
{
    /** @param array<string, mixed> $data */
    public function catat(string $aksi, string $deskripsi, ?Model $auditable = null, array $data = [], ?User $user = null): AuditLog
    {
        $pelaku = $user ?? Auth::user();

        return AuditLog::create([
            'user_id' => $pelaku?->getKey(),
            'aksi' => $aksi,
            'deskripsi' => $deskripsi,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'data' => $data === [] ? null : $data,
            'ip' => request()->ip(),
        ]);
    }
}
