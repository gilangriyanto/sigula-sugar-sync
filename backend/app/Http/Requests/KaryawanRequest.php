<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KaryawanRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $wajib = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'nama' => [$wajib, 'string', 'max:120'],
            'kontak' => ['nullable', 'string', 'max:40'],
            'aktif' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $data = [];

        if ($this->has('nama')) {
            $data['nama'] = trim((string) $this->input('nama'));
        }

        if ($this->has('kontak')) {
            $data['kontak'] = $this->input('kontak') ?: null;
        }

        if ($this->has('aktif')) {
            $data['aktif'] = $this->boolean('aktif');
        }

        return $data;
    }
}
