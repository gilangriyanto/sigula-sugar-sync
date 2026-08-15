<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Grade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UbahHargaRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'grade' => ['required', Rule::in(Grade::acceptedInputs())],
            'harga' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'berlakuDari' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'harga.gt' => 'Harga per kg harus lebih dari 0.',
        ];
    }

    public function grade(): Grade
    {
        return Grade::fromAny($this->input('grade'));
    }

    public function harga(): float
    {
        return (float) $this->input('harga');
    }
}
