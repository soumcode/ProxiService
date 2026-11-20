<?php

namespace Database\Factories;

use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\ServiceReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceReview>
 */
class ServiceReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory()->completed(),
            'client_id' => User::factory()->state(['role' => 'client']),
            'provider_profile_id' => ProviderProfile::factory()->approved(),
            'rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->optional(0.8)->paragraph(2),
            'is_visible' => true,
        ];
    }

    /**
     * Avis masqué.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible' => false,
        ]);
    }
}
