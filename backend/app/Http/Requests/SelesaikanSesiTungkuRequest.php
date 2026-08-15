<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SelesaikanSesiTungkuRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            // Kg hasil untuk SATU TUNGKU (gabungan 2 karyawan), bukan per orang.
            'kgKristal' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'kgBrondol' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->kgKristal() + $this->kgBrondol() <= 0) {
                $validator->errors()->add('kgKristal', 'Isi hasil produksi tungku ini (kristal dan/atau brondol).');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kgKristal.min' => 'Kg kristal tidak boleh negatif.',
            'kgBrondol.min' => 'Kg brondol tidak boleh negatif.',
        ];
    }

    public function kgKristal(): float
    {
        return (float) ($this->input('kgKristal') ?? 0);
    }

    public function kgBrondol(): float
    {
        return (float) ($this->input('kgBrondol') ?? 0);
    }
}
