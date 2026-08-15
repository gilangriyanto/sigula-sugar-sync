<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\JenisProduk;
use App\Enums\StatusPembayaran;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Satu invoice, dua baris opsional (Kristal & Brondol) dengan kg + harga
 * masing-masing. Bentuk payload sengaja dibuat sama persis dengan model data
 * di frontend supaya tidak perlu lapisan konversi.
 */
class PenjualanRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date'],
            'eksportirId' => ['required', Rule::exists('eksportir', 'id')->whereNull('deleted_at')],
            'kristal' => ['nullable', 'array'],
            'kristal.kg' => ['required_with:kristal', 'numeric', 'gt:0', 'max:9999999'],
            'kristal.harga' => ['required_with:kristal', 'numeric', 'gt:0', 'max:99999999'],
            'brondol' => ['nullable', 'array'],
            'brondol.kg' => ['required_with:brondol', 'numeric', 'gt:0', 'max:9999999'],
            'brondol.harga' => ['required_with:brondol', 'numeric', 'gt:0', 'max:99999999'],
            'statusPembayaran' => ['nullable', Rule::in(StatusPembayaran::acceptedInputs())],
            'catatan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->barisAktif('kristal') === null && $this->barisAktif('brondol') === null) {
                $validator->errors()->add('kristal', 'Minimal salah satu baris (Kristal atau Brondol) harus diisi.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kristal.kg.gt' => 'Kilogram kristal harus lebih dari 0.',
            'kristal.harga.gt' => 'Harga jual kristal harus lebih dari 0.',
            'brondol.kg.gt' => 'Kilogram brondol harus lebih dari 0.',
            'brondol.harga.gt' => 'Harga jual brondol harus lebih dari 0.',
            'eksportirId.required' => 'Eksportir wajib dipilih.',
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $items = [];

        foreach ([JenisProduk::KRISTAL, JenisProduk::BRONDOL] as $jenis) {
            $baris = $this->barisAktif($jenis->value);

            if ($baris === null) {
                continue;
            }

            $items[] = [
                'jenis' => $jenis,
                'kilogram' => (float) $baris['kg'],
                'harga_per_kg' => (float) $baris['harga'],
            ];
        }

        return [
            'tanggal' => (string) $this->input('tanggal'),
            'eksportir_id' => $this->input('eksportirId'),
            'items' => $items,
            'status_pembayaran' => $this->filled('statusPembayaran')
                ? StatusPembayaran::fromAny($this->input('statusPembayaran'))
                : StatusPembayaran::LUNAS,
            'catatan' => $this->input('catatan') ?: null,
        ];
    }

    /** @return array{kg: mixed, harga: mixed}|null */
    private function barisAktif(string $kunci): ?array
    {
        $baris = $this->input($kunci);

        if (! is_array($baris) || ! isset($baris['kg'], $baris['harga'])) {
            return null;
        }

        return ['kg' => $baris['kg'], 'harga' => $baris['harga']];
    }
}
