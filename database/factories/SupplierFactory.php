<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->company(), 'identification' => fake()->unique()->numerify('DEMO-S########'), 'email' => fake()->safeEmail(), 'phone' => fake()->phoneNumber(), 'address' => fake()->address(), 'active' => true];
    }
}
