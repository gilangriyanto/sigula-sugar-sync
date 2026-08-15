<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Backup database harian (lihat jadwal di routes/console.php).
 *
 * Mendukung MySQL/MariaDB (mysqldump), PostgreSQL (pg_dump), dan SQLite
 * (salin file). File lama otomatis dibersihkan sesuai --simpan.
 */
class BackupDatabase extends Command
{
    protected $signature = 'sigula:backup-db
        {--simpan=14 : Jumlah hari backup yang dipertahankan}
        {--path= : Folder tujuan (default storage/app/backups)}';

    protected $description = 'Membuat backup database SIGULA dan menghapus backup lama';

    public function handle(): int
    {
        $koneksi = config('database.default');
        $config = config("database.connections.{$koneksi}");
        $folder = $this->option('path') ?: storage_path('app/backups');

        File::ensureDirectoryExists($folder);

        $stempel = now()->format('Y-m-d_His');

        $hasil = match ($config['driver']) {
            'mysql', 'mariadb' => $this->dumpMysql($config, "{$folder}/sigula_{$stempel}.sql"),
            'pgsql' => $this->dumpPostgres($config, "{$folder}/sigula_{$stempel}.sql"),
            'sqlite' => $this->salinSqlite($config, "{$folder}/sigula_{$stempel}.sqlite"),
            default => null,
        };

        if ($hasil === null) {
            $this->error("Driver database [{$config['driver']}] belum didukung command backup.");

            return self::FAILURE;
        }

        $this->info("Backup tersimpan: {$hasil} (".$this->ukuran($hasil).')');
        $this->bersihkanBackupLama($folder);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $config */
    private function dumpMysql(array $config, string $tujuan): ?string
    {
        $perintah = [
            'mysqldump',
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--quick',
            '--routines',
            '--default-character-set=utf8mb4',
            $config['database'],
        ];

        return $this->jalankan($perintah, $tujuan, ['MYSQL_PWD' => (string) $config['password']]);
    }

    /** @param array<string, mixed> $config */
    private function dumpPostgres(array $config, string $tujuan): ?string
    {
        $perintah = [
            'pg_dump',
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--username='.$config['username'],
            '--no-owner',
            $config['database'],
        ];

        return $this->jalankan($perintah, $tujuan, ['PGPASSWORD' => (string) $config['password']]);
    }

    /** @param array<string, mixed> $config */
    private function salinSqlite(array $config, string $tujuan): ?string
    {
        $sumber = $config['database'];

        if (! is_string($sumber) || ! File::exists($sumber)) {
            $this->error('File database SQLite tidak ditemukan.');

            return null;
        }

        File::copy($sumber, $tujuan);

        return $tujuan;
    }

    /**
     * @param  array<int, string>  $perintah
     * @param  array<string, string>  $env
     */
    private function jalankan(array $perintah, string $tujuan, array $env): ?string
    {
        $proses = new Process($perintah, timeout: 900, env: $env);
        $handle = fopen($tujuan, 'wb');

        if ($handle === false) {
            $this->error("Tidak bisa menulis ke {$tujuan}.");

            return null;
        }

        $proses->run(function (string $tipe, string $data) use ($handle): void {
            if ($tipe === Process::OUT) {
                fwrite($handle, $data);

                return;
            }

            $this->warn(trim($data));
        });

        fclose($handle);

        if (! $proses->isSuccessful()) {
            File::delete($tujuan);
            $this->error('Backup gagal: '.trim($proses->getErrorOutput()));

            return null;
        }

        return $tujuan;
    }

    private function bersihkanBackupLama(string $folder): void
    {
        $simpanHari = max((int) $this->option('simpan'), 1);
        $batas = now()->subDays($simpanHari)->getTimestamp();
        $dihapus = 0;

        foreach (File::files($folder) as $file) {
            if ($file->getMTime() < $batas) {
                File::delete($file->getPathname());
                $dihapus++;
            }
        }

        if ($dihapus > 0) {
            $this->line("{$dihapus} backup lama (> {$simpanHari} hari) dihapus.");
        }
    }

    private function ukuran(string $file): string
    {
        $bytes = File::size($file);

        return $bytes > 1048576
            ? round($bytes / 1048576, 2).' MB'
            : round($bytes / 1024, 2).' KB';
    }
}
