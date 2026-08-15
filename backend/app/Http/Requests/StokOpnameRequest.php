<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\KategoriStok;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StokOpnameRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'kategori' => ['required', Rule::in(KategoriStok::acceptedInputs())],
            'stokFisik' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'alasan' => ['required', 'string', 'max:255'],
            'tanggal' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'stokFisik.min' => 'Jumlah stok fisik tidak boleh negatif.',
            'alasan.required' => 'Alasan koreksi wajib diisi.',
        ];
    }

    public function kategori(): KategoriStok
    {
        return KategoriStok::fromAny($this->input('kategori'));
    }
}
