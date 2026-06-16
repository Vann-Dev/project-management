<?php

namespace Database\Factories;

use App\Models\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectStatusFactory extends Factory
{
    protected $model = ProjectStatus::class;

    public function definition()
    {
        return [
            'name' => fake()->randomElement(['Active', 'Planning', 'On Hold', 'In Progress', 'Completed']),
            'color' => fake()->hexColor(),
            'is_default' => false,
        ];
    }

    public function default()
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
