<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pesan Validasi Bahasa Indonesia
|--------------------------------------------------------------------------
| Hanya memuat rule yang dipakai SIGULA. Rule lain otomatis jatuh ke
| APP_FALLBACK_LOCALE (en).
*/

return [
    'accepted' => ':attribute wajib disetujui.',
    'after' => ':attribute harus tanggal setelah :date.',
    'after_or_equal' => ':attribute harus tanggal setelah atau sama dengan :date.',
    'array' => ':attribute harus berupa data terstruktur.',
    'before' => ':attribute harus tanggal sebelum :date.',
    'before_or_equal' => ':attribute harus tanggal sebelum atau sama dengan :date.',
    'boolean' => ':attribute hanya boleh bernilai ya atau tidak.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => ':attribute bukan tanggal yang valid.',
    'date_equals' => ':attribute harus tanggal yang sama dengan :date.',
    'date_format' => ':attribute tidak sesuai format :format.',
    'different' => ':attribute dan :other tidak boleh sama.',
    'digits' => ':attribute harus terdiri dari :digits angka.',
    'digits_between' => ':attribute harus terdiri dari :min sampai :max angka.',
    'distinct' => ':attribute berisi nilai yang duplikat.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'exists' => ':attribute yang dipilih tidak ditemukan.',
    'filled' => ':attribute wajib diisi.',
    'gt' => [
        'array' => ':attribute harus lebih dari :value item.',
        'file' => ':attribute harus lebih besar dari :value kilobyte.',
        'numeric' => ':attribute harus lebih dari :value.',
        'string' => ':attribute harus lebih panjang dari :value karakter.',
    ],
    'gte' => [
        'array' => ':attribute harus :value item atau lebih.',
        'numeric' => ':attribute harus lebih dari atau sama dengan :value.',
        'string' => ':attribute harus minimal :value karakter.',
    ],
    'in' => 'Pilihan :attribute tidak valid.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'lt' => [
        'numeric' => ':attribute harus kurang dari :value.',
    ],
    'lte' => [
        'numeric' => ':attribute harus kurang dari atau sama dengan :value.',
    ],
    'max' => [
        'array' => ':attribute tidak boleh lebih dari :max item.',
        'file' => ':attribute tidak boleh lebih dari :max kilobyte.',
        'numeric' => ':attribute tidak boleh lebih dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],
    'min' => [
        'array' => ':attribute harus minimal :min item.',
        'file' => ':attribute harus minimal :min kilobyte.',
        'numeric' => ':attribute harus minimal :min.',
        'string' => ':attribute harus minimal :min karakter.',
    ],
    'not_in' => 'Pilihan :attribute tidak valid.',
    'numeric' => ':attribute harus berupa angka.',
    'present' => ':attribute wajib ada.',
    'prohibited' => ':attribute tidak boleh diisi.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'required_if' => ':attribute wajib diisi bila :other bernilai :value.',
    'required_unless' => ':attribute wajib diisi kecuali :other bernilai :values.',
    'required_with' => ':attribute wajib diisi bila :values ada.',
    'required_without' => ':attribute wajib diisi bila :values tidak ada.',
    'same' => ':attribute dan :other harus sama.',
    'size' => [
        'array' => ':attribute harus berisi :size item.',
        'numeric' => ':attribute harus bernilai :size.',
        'string' => ':attribute harus :size karakter.',
    ],
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah digunakan.',
    'uploaded' => ':attribute gagal diunggah.',

    'custom' => [
        'password' => [
            'required' => 'Password wajib diisi.',
        ],
    ],

    /*
    | Nama field yang ditampilkan pada pesan error, disesuaikan dengan
    | istilah yang dipakai di layar SIGULA.
    */
    'attributes' => [
        'email' => 'Email',
        'password' => 'Password',
        'nama' => 'Nama',
        'status' => 'Status',
        'nomorMember' => 'Nomor member',
        'kontak' => 'Kontak',
        'alamat' => 'Alamat',
        'tanggal' => 'Tanggal',
        'petaniId' => 'Petani',
        'eksportirId' => 'Eksportir',
        'grade' => 'Grade',
        'kg' => 'Kilogram',
        'harga' => 'Harga per kg',
        'kgBahan' => 'Kg bahan mentah',
        'kgKristal' => 'Kg kristal',
        'kgBrondol' => 'Kg brondol',
        'kodeTungku' => 'Kode tungku',
        'karyawan1Id' => 'Karyawan 1',
        'karyawan2Id' => 'Karyawan 2',
        'kristal' => 'Baris kristal',
        'kristal.kg' => 'Kilogram kristal',
        'kristal.harga' => 'Harga jual kristal',
        'brondol' => 'Baris brondol',
        'brondol.kg' => 'Kilogram brondol',
        'brondol.harga' => 'Harga jual brondol',
        'kategori' => 'Kategori',
        'keterangan' => 'Keterangan',
        'jumlah' => 'Jumlah',
        'nilai' => 'Nilai tarif',
        'jenis' => 'Jenis',
        'stokFisik' => 'Jumlah stok fisik',
        'alasan' => 'Alasan',
        'berlakuDari' => 'Tanggal berlaku',
        'statusPembayaran' => 'Status pembayaran',
        'catatan' => 'Catatan',
        'dari' => 'Tanggal awal',
        'sampai' => 'Tanggal akhir',
        'periode' => 'Periode',
    ],
];
