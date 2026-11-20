<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Plomberie',
            'Électricité',
            'Menuiserie',
            'Peinture',
            'Maçonnerie',
            'Climatisation',
            'Jardinage',
            'Nettoyage',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(10),
            'icon' => $this->faker->randomElement(['wrench', 'bolt', 'hammer', 'palette', 'leaf']),
            'is_active' => $this->faker->boolean(90),
        ];
    }
}
