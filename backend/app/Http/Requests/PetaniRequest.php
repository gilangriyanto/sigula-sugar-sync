<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\StatusPetani;
use App\Models\Petani;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PetaniRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Petani|null $petani */
        $petani = $this->route('petani');

        return [
            'nama' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::in(StatusPetani::acceptedInputs())],
            'nomorMember' => [
                'nullable',
                'string',
                'regex:/^\d{3}$/',
                Rule::unique('petani', 'nomor_member')->ignore($petani?->getKey())->whereNull('deleted_at'),
            ],
            'kontak' => ['nullable', 'string', 'max:40'],
            'alamat' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nomorMember.regex' => 'Nomor member harus 3 digit angka, contoh 231.',
            'nomorMember.unique' => 'Nomor member sudah dipakai petani lain.',
        ];
    }

    /**
     * @return array{nama: string, status: StatusPetani, nomor_member: string|null, kontak: string|null, alamat: string|null}
     */
    public function payload(): array
    {
        return [
            'nama' => trim((string) $this->input('nama')),
            'status' => StatusPetani::fromAny($this->input('status')),
            'nomor_member' => $this->input('nomorMember') ?: null,
            'kontak' => $this->input('kontak') ?: null,
            'alamat' => $this->input('alamat') ?: null,
        ];
    }
}
