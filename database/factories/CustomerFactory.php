<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name(), 'identification' => fake()->unique()->numerify('DEMO-C########'), 'email' => fake()->safeEmail(), 'phone' => fake()->phoneNumber(), 'address' => fake()->address(), 'active' => true];
    }
}
