<?php

namespace Database\Factories;

use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderProfile>
 */
class ProviderProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cities = ['Abidjan', 'Cocody', 'Yopougon', 'Abobo', 'Adjamé', 'Marcory', 'Treichville'];
        $neighborhoods = ['Centre', 'Nord', 'Sud', 'Est', 'Ouest', 'Riviera', 'Plateau', 'Zone 4', 'Angré', 'Deux Plateaux'];

        return [
            'user_id' => User::factory()->state(['role' => 'prestataire']),
            'business_name' => $this->faker->optional(0.7)->company,
            'bio' => $this->faker->optional(0.8)->paragraph(2),
            'phone' => $this->faker->optional(0.9)->phoneNumber,
            'years_experience' => $this->faker->optional(0.7)->numberBetween(1, 30),
            'city' => $this->faker->randomElement($cities),
            'neighborhood' => $this->faker->randomElement($neighborhoods),
            'address' => $this->faker->optional(0.6)->streetAddress,
            'latitude' => $this->faker->latitude(5.2, 5.5),
            'longitude' => $this->faker->longitude(-4.2, -3.9),
            'is_available' => $this->faker->boolean(70),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
        ];
    }

    /**
     * Profil approuvé.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }

    /**
     * Profil en attente.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }
}
