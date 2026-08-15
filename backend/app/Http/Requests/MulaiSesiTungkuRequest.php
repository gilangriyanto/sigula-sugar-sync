<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Grade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MulaiSesiTungkuRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $karyawanAda = Rule::exists('karyawan', 'id')->whereNull('deleted_at');

        return [
            'tanggal' => ['required', 'date'],
            'kodeTungku' => ['nullable', 'string', 'max:30'],
            'grade' => ['required', Rule::in(Grade::acceptedInputs())],
            'kgBahan' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'karyawan1Id' => ['required', $karyawanAda],
            // Satu tungku selalu dikerjakan tepat 2 orang yang berbeda.
            'karyawan2Id' => ['required', 'different:karyawan1Id', $karyawanAda],
            'catatan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kgBahan.gt' => 'Kg bahan mentah harus lebih dari 0.',
            'karyawan1Id.required' => 'Karyawan 1 wajib dipilih.',
            'karyawan2Id.required' => 'Karyawan 2 wajib dipilih.',
            'karyawan2Id.different' => 'Karyawan 1 dan Karyawan 2 tidak boleh orang yang sama.',
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'tanggal' => (string) $this->input('tanggal'),
            'kode_tungku' => $this->input('kodeTungku') ?: null,
            'grade' => Grade::fromAny($this->input('grade')),
            'kg_bahan_mentah' => (float) $this->input('kgBahan'),
            'karyawan_1_id' => $this->input('karyawan1Id'),
            'karyawan_2_id' => $this->input('karyawan2Id'),
            'catatan' => $this->input('catatan') ?: null,
        ];
    }
}
