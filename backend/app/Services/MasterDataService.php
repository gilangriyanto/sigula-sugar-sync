<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusPetani;
use App\Exceptions\BusinessRuleException;
use App\Models\Eksportir;
use App\Models\Karyawan;
use App\Models\Penjualan;
use App\Models\Petani;
use App\Models\ProduksiKaryawan;
use App\Models\SesiTungku;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** CRUD master data (petani, karyawan, eksportir) lengkap dengan audit trail. */
final class MasterDataService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{nama: string, status: StatusPetani, nomor_member?: string|null, kontak?: string|null, alamat?: string|null}  $data
     */
    public function simpanPetani(array $data, ?User $user = null): Petani
    {
        return DB::transaction(function () use ($data, $user): Petani {
            $petani = Petani::create($this->atributPetani($data));

            $this->audit->catat('petani.simpan', sprintf('Petani %s ditambahkan', $petani->nama), $petani, [], $user);

            return $petani;
        });
    }

    /** @param array{nama: string, status: StatusPetani, nomor_member?: string|null, kontak?: string|null, alamat?: string|null} $data */
    public function ubahPetani(Petani $petani, array $data, ?User $user = null): Petani
    {
        return DB::transaction(function () use ($petani, $data, $user): Petani {
            $sebelum = $petani->only(['nama', 'status', 'nomor_member', 'kontak', 'alamat']);
            $petani->update($this->atributPetani($data, $petani));

            $this->audit->catat(
                'petani.ubah',
                sprintf('Data petani %s diperbarui', $petani->nama),
                $petani,
                ['sebelum' => $sebelum],
                $user,
            );

            return $petani->refresh();
        });
    }

    public function hapusPetani(Petani $petani, ?User $user = null): void
    {
        if ($petani->pembelian()->exists()) {
            throw new BusinessRuleException(
                sprintf('Petani %s tidak bisa dihapus karena sudah punya transaksi pembelian.', $petani->nama)
            );
        }

        DB::transaction(function () use ($petani, $user): void {
            $this->audit->catat('petani.hapus', sprintf('Petani %s dihapus', $petani->nama), $petani, [], $user);
            $petani->delete();
        });
    }

    /** @param array{nama: string, kontak?: string|null, aktif?: bool} $data */
    public function simpanKaryawan(array $data, ?User $user = null): Karyawan
    {
        return DB::transaction(function () use ($data, $user): Karyawan {
            $karyawan = Karyawan::create([
                'nama' => $data['nama'],
                'kontak' => $data['kontak'] ?? null,
                'aktif' => $data['aktif'] ?? true,
            ]);

            $this->audit->catat('karyawan.simpan', sprintf('Karyawan %s ditambahkan', $karyawan->nama), $karyawan, [], $user);

            return $karyawan;
        });
    }

    /** @param array{nama?: string, kontak?: string|null, aktif?: bool} $data */
    public function ubahKaryawan(Karyawan $karyawan, array $data, ?User $user = null): Karyawan
    {
        return DB::transaction(function () use ($karyawan, $data, $user): Karyawan {
            $karyawan->update($data);

            $this->audit->catat('karyawan.ubah', sprintf('Data karyawan %s diperbarui', $karyawan->nama), $karyawan, [], $user);

            return $karyawan->refresh();
        });
    }

    /**
     * Karyawan yang pernah masuk sesi tungku tidak dihapus permanen — cukup
     * dinonaktifkan supaya histori produksi & gaji tetap bisa ditelusuri.
     */
    public function hapusKaryawan(Karyawan $karyawan, ?User $user = null): string
    {
        $pernahProduksi = ProduksiKaryawan::query()->where('karyawan_id', $karyawan->id)->exists()
            || SesiTungku::query()
                ->where('karyawan_1_id', $karyawan->id)
                ->orWhere('karyawan_2_id', $karyawan->id)
                ->exists();

        return DB::transaction(function () use ($karyawan, $user, $pernahProduksi): string {
            if ($pernahProduksi) {
                $karyawan->update(['aktif' => false]);
                $this->audit->catat(
                    'karyawan.nonaktif',
                    sprintf('Karyawan %s dinonaktifkan (punya histori produksi)', $karyawan->nama),
                    $karyawan, [], $user,
                );

                return 'nonaktif';
            }

            $this->audit->catat('karyawan.hapus', sprintf('Karyawan %s dihapus', $karyawan->nama), $karyawan, [], $user);
            $karyawan->delete();

            return 'hapus';
        });
    }

    /** @param array{nama: string, kontak?: string|null, alamat?: string|null, aktif?: bool} $data */
    public function simpanEksportir(array $data, ?User $user = null): Eksportir
    {
        return DB::transaction(function () use ($data, $user): Eksportir {
            $eksportir = Eksportir::create([
                'nama' => $data['nama'],
                'kontak' => $data['kontak'] ?? null,
                'alamat' => $data['alamat'] ?? null,
                'aktif' => $data['aktif'] ?? true,
            ]);

            $this->audit->catat('eksportir.simpan', sprintf('Eksportir %s ditambahkan', $eksportir->nama), $eksportir, [], $user);

            return $eksportir;
        });
    }

    /** @param array{nama?: string, kontak?: string|null, alamat?: string|null, aktif?: bool} $data */
    public function ubahEksportir(Eksportir $eksportir, array $data, ?User $user = null): Eksportir
    {
        return DB::transaction(function () use ($eksportir, $data, $user): Eksportir {
            $eksportir->update($data);

            $this->audit->catat('eksportir.ubah', sprintf('Data eksportir %s diperbarui', $eksportir->nama), $eksportir, [], $user);

            return $eksportir->refresh();
        });
    }

    public function hapusEksportir(Eksportir $eksportir, ?User $user = null): void
    {
        if (Penjualan::query()->where('eksportir_id', $eksportir->id)->exists()) {
            throw new BusinessRuleException(
                sprintf('Eksportir %s tidak bisa dihapus karena sudah punya transaksi penjualan.', $eksportir->nama)
            );
        }

        DB::transaction(function () use ($eksportir, $user): void {
            $this->audit->catat('eksportir.hapus', sprintf('Eksportir %s dihapus', $eksportir->nama), $eksportir, [], $user);
            $eksportir->delete();
        });
    }

    /**
     * Nomor member 3 digit berikutnya. Dipanggil di dalam transaction dan
     * dilindungi unique index pada kolom nomor_member.
     */
    public function nomorMemberBerikutnya(): string
    {
        // Dihitung di PHP (bukan CAST di SQL) supaya portabel MySQL/PostgreSQL/SQLite.
        $terpakai = Petani::query()
            ->withTrashed()
            ->whereNotNull('nomor_member')
            ->pluck('nomor_member')
            ->map(static fn (string $nomor): int => (int) $nomor);

        $berikutnya = max(($terpakai->max() ?? 0) + 1, 201);

        if ($berikutnya > 999) {
            throw new BusinessRuleException('Nomor member 3 digit sudah habis (maksimal 999).');
        }

        return str_pad((string) $berikutnya, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array{nama: string, status: StatusPetani, nomor_member?: string|null, kontak?: string|null, alamat?: string|null}  $data
     * @return array<string, mixed>
     */
    private function atributPetani(array $data, ?Petani $existing = null): array
    {
        $status = $data['status'];
        $nomor = $data['nomor_member'] ?? null;

        if ($status === StatusPetani::MEMBER) {
            // Nomor dipertahankan bila petani sudah punya, di-generate bila kosong.
            $nomor = filled($nomor) ? $nomor : ($existing?->nomor_member ?: $this->nomorMemberBerikutnya());
        } else {
            // Non-member tidak menyimpan nomor member sama sekali.
            $nomor = null;
        }

        return [
            'nama' => $data['nama'],
            'status' => $status->value,
            'nomor_member' => $nomor,
            'kontak' => $data['kontak'] ?? null,
            'alamat' => $data['alamat'] ?? null,
        ];
    }
}
