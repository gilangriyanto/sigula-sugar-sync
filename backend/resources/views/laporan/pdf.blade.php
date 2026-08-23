<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul[1] ?? 'Laporan SIGULA' }}</title>
    <style>
        @page { margin: 18mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #3B2A1A; }
        .kop { text-align: center; margin-bottom: 14px; }
        .kop .perusahaan { font-size: 13px; font-weight: bold; color: #9C6B1F; }
        .kop .nama-laporan { font-size: 11px; font-weight: bold; margin-top: 2px; }
        .kop .periode { font-size: 9px; color: #6b5b4a; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #9C6B1F; color: #fff; font-size: 8.5px; padding: 5px 4px; text-align: left; }
        td { padding: 4px; border-bottom: 1px solid #e5ded3; font-size: 8.5px; }
        tr:nth-child(even) td { background: #FBF3E3; }
        .angka { text-align: right; }
        .catatan { margin-top: 12px; font-size: 8px; color: #8a7965; font-style: italic; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0;
                  font-size: 7.5px; color: #8a7965; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">
        Dokumen dibuat otomatis oleh SIGULA — PT Nira Sari Murni
    </div>

    <div class="kop">
        @foreach ($judul as $i => $teks)
            <div class="{{ $i === 0 ? 'perusahaan' : ($i === 1 ? 'nama-laporan' : 'periode') }}">{{ $teks }}</div>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($kolom as $judulKolom)
                    <th>{{ $judulKolom }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($baris as $row)
                <tr>
                    @foreach ($row as $sel)
                        {{-- Angka berformat Indonesia dirata-kanan agar mudah dibaca --}}
                        <td class="{{ preg_match('/^-?\d+,\d{2}$/', (string) $sel) ? 'angka' : '' }}">{{ $sel }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($terpotong)
        <p class="catatan">
            Ditampilkan {{ number_format($batasBaris, 0, ',', '.') }} baris pertama.
            Gunakan format Excel atau CSV, atau persempit rentang tanggalnya, untuk data lengkap.
        </p>
    @endif
</body>
</html>
