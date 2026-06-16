<?php

namespace Database\Factories;

use App\Models\TicketStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketStatus>
 */
class TicketStatusFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TicketStatus::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $names = ['Todo', 'In Progress', 'Review', 'Done', 'Blocked'];
        $colors = ['#64748b', '#3b82f6', '#f59e0b', '#10b981', '#ef4444'];

        $index = array_rand($names);

        return [
            'name' => $names[$index] . '_' . uniqid(),
            'color' => $colors[$index],
            'is_default' => false, // Don't set as default to avoid conflicts
            'order' => fake()->numberBetween(1, 100),
        ];
    }
}
