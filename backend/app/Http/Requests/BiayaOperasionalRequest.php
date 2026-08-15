<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\KategoriBiaya;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BiayaOperasionalRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $wajib = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'tanggal' => [$wajib, 'date'],
            'keterangan' => [$wajib, 'string', 'max:255'],
            'kategori' => [$wajib, Rule::in(KategoriBiaya::acceptedInputs())],
            'jumlah' => [$wajib, 'numeric', 'gt:0', 'max:999999999999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'jumlah.gt' => 'Jumlah harus lebih dari 0.',
            'keterangan.required' => 'Keterangan wajib diisi.',
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $data = [];

        if ($this->has('tanggal')) {
            $data['tanggal'] = (string) $this->input('tanggal');
        }

        if ($this->has('keterangan')) {
            $data['keterangan'] = trim((string) $this->input('keterangan'));
        }

        if ($this->has('kategori')) {
            $data['kategori'] = KategoriBiaya::fromAny($this->input('kategori'))->value;
        }

        if ($this->has('jumlah')) {
            $data['jumlah'] = round((float) $this->input('jumlah'), 2);
        }

        return $data;
    }
}
