<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Karyawan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Karyawan> */
class KaryawanFactory extends Factory
{
    protected $model = Karyawan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nama' => $this->faker->name(),
            'kontak' => '08'.$this->faker->numerify('##-####-####'),
            'aktif' => true,
        ];
    }
}
