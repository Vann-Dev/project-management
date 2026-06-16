<?php

namespace Database\Factories;

use App\Models\Epic;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class EpicFactory extends Factory
{
    protected $model = Epic::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('now', '+1 month');
        $endsAt = fake()->dateTimeBetween($startsAt, '+3 months');

        return [
            'name' => fake()->sentence(4),
            'project_id' => Project::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'parent_id' => null,
        ];
    }

    public function withParent(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => Epic::factory(),
        ]);
    }
}
