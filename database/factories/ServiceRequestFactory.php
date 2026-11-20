<?php

namespace Database\Factories;

use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cities = ['Abidjan', 'Cocody', 'Yopougon', 'Abobo', 'Adjamé', 'Marcory'];
        $neighborhoods = ['Centre', 'Nord', 'Sud', 'Riviera', 'Plateau', 'Zone 4'];

        return [
            'client_id' => User::factory()->state(['role' => 'client']),
            'provider_profile_id' => ProviderProfile::factory()->approved(),
            'service_id' => Service::factory(),
            'description' => $this->faker->paragraph(2),
            'city' => $this->faker->randomElement($cities),
            'neighborhood' => $this->faker->randomElement($neighborhoods),
            'address' => $this->faker->optional(0.6)->streetAddress,
            'scheduled_at' => $this->faker->optional(0.3)->dateTimeBetween('now', '+7 days'),
            'is_urgent' => $this->faker->boolean(20),
            'status' => 'pending',
        ];
    }

    /**
     * Demande acceptée.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
        ]);
    }

    /**
     * Demande en cours.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
        ]);
    }

    /**
     * Demande en attente de confirmation.
     */
    public function awaitingConfirmation(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'awaiting_confirmation',
        ]);
    }

    /**
     * Demande terminée.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
