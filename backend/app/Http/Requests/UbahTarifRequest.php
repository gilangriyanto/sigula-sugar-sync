<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\JenisTarif;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UbahTarifRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'jenis' => ['required', Rule::in([...JenisTarif::acceptedInputs(), 'uangMakan'])],
            'nilai' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'berlakuDari' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nilai.gt' => 'Nilai tarif harus lebih dari 0.',
        ];
    }

    public function jenis(): JenisTarif
    {
        return JenisTarif::fromAny($this->input('jenis'));
    }

    public function nilai(): float
    {
        return (float) $this->input('nilai');
    }
}
