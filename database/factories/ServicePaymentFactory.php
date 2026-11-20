<?php

namespace Database\Factories;

use App\Models\ServicePayment;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePayment>
 */
class ServicePaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->numberBetween(5000, 100000);

        return [
            'service_request_id' => ServiceRequest::factory(),
            'client_id' => User::factory()->state(['role' => 'client']),
            'provider' => 'geniuspay',
            'provider_reference' => 'GP-'.$this->faker->unique()->uuid,
            'provider_transaction_id' => $this->faker->optional(0.7)->uuid,
            'amount' => $amount,
            'currency' => 'XOF',
            'fees' => $this->faker->optional(0.5)->randomFloat(2, 0, $amount * 0.03),
            'net_amount' => $this->faker->optional(0.5)->randomFloat(2, $amount * 0.97, $amount),
            'payment_method' => $this->faker->optional(0.6)->randomElement(['wave', 'orange_money', 'mtn', 'moov']),
            'status' => 'pending',
            'checkout_url' => 'https://geniuspay.ci/checkout/'.$this->faker->uuid,
            'metadata' => null,
            'failure_reason' => null,
            'paid_at' => null,
            'environment' => config('services.geniuspay.environment', 'sandbox'),
        ];
    }

    /**
     * Paiement complété.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'paid_at' => now(),
            'failure_reason' => null,
        ]);
    }

    /**
     * Paiement échoué.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'failure_reason' => 'Statut communiqué par GeniusPay : failed',
            'paid_at' => null,
        ]);
    }
}
