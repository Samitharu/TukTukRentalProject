<?php

declare(strict_types=1);

namespace Modules\Customer\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Customer\Models\Customer;

/**
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'full_name' => fake()->name(),
            'nationality' => fake()->countryCode(),
            'locale_preference' => 'en',
        ];
    }
}
