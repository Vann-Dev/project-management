<?php

namespace Database\Factories;

use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TicketType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = [
            ['name' => 'Bug', 'icon' => 'heroicon-o-bug-ant', 'color' => '#ef4444'],
            ['name' => 'Task', 'icon' => 'heroicon-o-clipboard-document-list', 'color' => '#3b82f6'],
            ['name' => 'Feature', 'icon' => 'heroicon-o-sparkles', 'color' => '#8b5cf6'],
            ['name' => 'Improvement', 'icon' => 'heroicon-o-arrow-trending-up', 'color' => '#10b981'],
        ];

        $type = fake()->randomElement($types);

        return [
            'name' => $type['name'] . '_' . uniqid(),
            'icon' => $type['icon'],
            'color' => $type['color'],
            'is_default' => false, // Don't set as default to avoid conflicts
            'order' => fake()->numberBetween(1, 100),
        ];
    }
}
