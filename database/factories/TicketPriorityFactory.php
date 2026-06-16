<?php

namespace Database\Factories;

use App\Models\TicketPriority;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketPriority>
 */
class TicketPriorityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TicketPriority::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $priorities = [
            ['name' => 'Low', 'color' => '#64748b'],
            ['name' => 'Medium', 'color' => '#eab308'],
            ['name' => 'High', 'color' => '#f97316'],
            ['name' => 'Critical', 'color' => '#ef4444'],
        ];

        $priority = fake()->randomElement($priorities);

        return [
            'name' => $priority['name'] . '_' . uniqid(),
            'color' => $priority['color'],
            'is_default' => false, // Don't set as default to avoid conflicts
            'order' => fake()->numberBetween(1, 100),
        ];
    }
}
