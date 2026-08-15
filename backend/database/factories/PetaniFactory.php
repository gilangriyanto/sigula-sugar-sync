<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusPetani;
use App\Models\Petani;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Petani> */
class PetaniFactory extends Factory
{
    protected $model = Petani::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nama' => $this->faker->name(),
            'status' => StatusPetani::MEMBER->value,
            'nomor_member' => (string) $this->faker->unique()->numberBetween(201, 999),
            'kontak' => '08'.$this->faker->numerify('##-####-####'),
            'alamat' => $this->faker->address(),
        ];
    }

    public function nonMember(): static
    {
        return $this->state(fn (): array => [
            'status' => StatusPetani::NON_MEMBER->value,
            'nomor_member' => null,
        ]);
    }
}
