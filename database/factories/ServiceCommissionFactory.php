<?php

namespace Database\Factories;

use App\Models\ServiceCommission;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCommission>
 */
class ServiceCommissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grossAmount = $this->faker->numberBetween(5000, 100000);
        $commissionRate = (float) config('proxiservices.commission_rate', 10.0);
        $commissionAmount = round($grossAmount * $commissionRate / 100, 2);
        $providerAmount = round($grossAmount - $commissionAmount, 2);

        return [
            'service_request_id' => ServiceRequest::factory()->completed(),
            'gross_amount' => $grossAmount,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'provider_amount' => $providerAmount,
            'collection_status' => $this->faker->randomElement(['pending', 'collected']),
            'calculated_at' => now(),
            'is_reversed' => false,
            'reversed_at' => null,
            'reversal_reason' => null,
        ];
    }

    /**
     * Commission neutralisée.
     */
    public function reversed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_reversed' => true,
            'reversed_at' => now(),
            'reversal_reason' => 'Paiement remboursé par GeniusPay.',
        ]);
    }
}
