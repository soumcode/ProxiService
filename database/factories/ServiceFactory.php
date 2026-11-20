<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Réparation robinet',
            'Installation WC',
            'Réparation prise électrique',
            'Installation plafonnier',
            'Fabrication porte',
            'Peinture salon',
            'Pose carrelage',
            'Entretien jardin',
        ]);

        $pricingType = $this->faker->randomElement(['fixed', 'quote']);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(15),
            'pricing_type' => $pricingType,
            'base_price' => $pricingType === 'fixed'
                ? $this->faker->numberBetween(5000, 50000)
                : null,
            'estimated_duration_minutes' => $this->faker->randomElement([30, 60, 90, 120, 180]),
            'is_active' => $this->faker->boolean(95),
        ];
    }
}
