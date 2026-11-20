<?php

namespace Database\Factories;

use App\Models\ProviderProfile;
use App\Models\ServiceQuote;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceQuote>
 */
class ServiceQuoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'provider_profile_id' => ProviderProfile::factory()->approved(),
            'amount' => $this->faker->numberBetween(5000, 100000),
            'description' => $this->faker->paragraph(2),
            'status' => 'pending',
            'responded_at' => null,
        ];
    }

    /**
     * Devis accepté.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'responded_at' => now(),
        ]);
    }

    /**
     * Devis rejeté.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'responded_at' => now(),
        ]);
    }
}
