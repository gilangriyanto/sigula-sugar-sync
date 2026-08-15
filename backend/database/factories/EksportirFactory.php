<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Eksportir;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Eksportir> */
class EksportirFactory extends Factory
{
    protected $model = Eksportir::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nama' => 'PT '.$this->faker->company(),
            'kontak' => '021-'.$this->faker->numerify('#######'),
            'alamat' => $this->faker->address(),
            'aktif' => true,
        ];
    }
}
